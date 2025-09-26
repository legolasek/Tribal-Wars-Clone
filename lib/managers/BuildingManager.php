<?php

class BuildingManager {
    private $conn;
    private $buildingConfigManager;

    public function __construct($db_connection, BuildingConfigManager $buildingConfigManager) {
        $this->conn = $db_connection;
        $this->buildingConfigManager = $buildingConfigManager;
    }

    /**
     * Oblicza produkcję surowca przez dany budynek na danym poziomie na godzinę.
     * Zwraca 0 jeśli budynek nie produkuje lub nie ma danych.
     */
    public function getHourlyProduction($building_internal_name, $level) {
        if ($level == 0) return 0;
        $config = $this->buildingConfigManager->getBuildingConfig($building_internal_name);
        
        if (!$config || !$config['production_type'] || $config['production_initial'] === null || $config['production_factor'] === null) {
            return 0;
        }
        
        return floor($this->buildingConfigManager->calculateProduction($building_internal_name, $level));
    }

    /**
     * Oblicza pojemność magazynu na danym poziomie.
     * Na razie używamy stałej z config, ale w przyszłości to może zależeć od poziomu magazynu.
     * Ta funkcja jest placeholderem, jeśli chcemy by pojemność magazynu była dynamiczna.
     */
    public function getWarehouseCapacityByLevel($warehouse_level) {
         if ($warehouse_level <= 0) {
             return defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000;
         }
         
         $capacity = $this->buildingConfigManager->calculateWarehouseCapacity($warehouse_level);
         
         return $capacity ?? (defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000);
    }

    public function getBuildingDisplayName($internal_name) {
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        return $config ? $config['name_pl'] : 'Nieznany budynek';
    }

    public function getBuildingMaxLevel($internal_name) {
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        return $config ? (int)$config['max_level'] : 0;
    }

    /**
     * Oblicza koszt rozbudowy budynku do następnego poziomu.
     * Zwraca tablicę ['wood' => koszt, 'clay' => koszt, 'iron' => koszt] lub null jeśli błąd.
     */
    public function getBuildingUpgradeCost($internal_name, $next_level) {
        if ($next_level <= 0) return null;
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);

        if (!$config || $next_level > $config['max_level']) {
            return null;
        }

        return $this->buildingConfigManager->calculateUpgradeCost($internal_name, $next_level - 1);
    }

    /**
     * Oblicza czas potrzebny na rozbudowę budynku do następnego poziomu (w sekundach).
     * Czas zależy od poziomu Ratusza.
     */
    public function getBuildingUpgradeTime($internal_name, $next_level, $main_building_level) {
        if ($next_level <= 0) return null;
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        if (!$config || $next_level > $config['max_level']) {
            return null;
        }

        return $this->buildingConfigManager->calculateUpgradeTime($internal_name, $next_level - 1, $main_building_level);
    }

    public function getBuildingInfo($internal_name) {
        return $this->buildingConfigManager->getBuildingConfig($internal_name);
    }

    public function getBuildingInfoById($building_type_id) {
         error_log("WARNING: BuildingManager::getBuildingInfoById is used. Prefer getBuildingInfo by internal_name.");
        $stmt = $this->conn->prepare("SELECT * FROM building_types WHERE id = ? LIMIT 1");
         if ($stmt === false) {
             error_log("Prepare failed for getBuildingInfoById: " . $this->conn->error);
             return null;
         }
        $stmt->bind_param("i", $building_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        $stmt->close();
        return $config;

    }
    
    /**
     * Alias dla getBuildingUpgradeCost dla zachowania zgodności
     */
    public function getUpgradeCosts($internal_name, $next_level) {
        return $this->getBuildingUpgradeCost($internal_name, $next_level);
    }
    
    /**
     * Alias dla getBuildingUpgradeTime dla zachowania zgodności
     */
    public function getUpgradeTimeInSeconds($internal_name, $next_level, $main_building_level) {
        return $this->getBuildingUpgradeTime($internal_name, $next_level, $main_building_level);
    }
    
    /**
     * Sprawdza czy spełnione są wymagania dotyczące innych budynków do rozbudowy danego budynku
     * Inspirowane starą wersją - metoda check_needed z klasy builds
     */
    public function checkBuildingRequirements($internal_name, $village_id) {
        $requirements = $this->buildingConfigManager->getBuildingRequirements($internal_name);

        if (empty($requirements)) {
            return ['success' => true, 'message' => 'Brak dodatkowych wymagań dotyczących budynków.'];
        }

        $stmt = $this->conn->prepare("
            SELECT bt.internal_name, vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $village_buildings = [];
        while ($row = $result->fetch_assoc()) {
            $village_buildings[$row['internal_name']] = $row['level'];
        }
        $stmt->close();
        
        foreach ($requirements as $req) {
            $requiredBuildingName = $req['required_building'];
            $requiredLevel = $req['required_level'];

            $currentLevel = $village_buildings[$requiredBuildingName] ?? 0;

            if ($currentLevel < $requiredLevel) {
                $requiredBuildingDisplayName = $this->buildingConfigManager->getBuildingConfig($requiredBuildingName)['name_pl'] ?? $requiredBuildingName;
                return [
                    'success' => false,
                    'message' => "Wymaga: " . htmlspecialchars($requiredBuildingDisplayName) . " na poziomie " . $requiredLevel . ". Twoj obecny poziom: " . $currentLevel
                ];
            }
        }

        return ['success' => true, 'message' => 'Wymagania spełnione.'];
    }

    public function getBuildingLevel(int $villageId, string $internalName): int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        
        if ($stmt === false) {
             error_log("Prepare failed for getBuildingLevel: " . $this->conn->error);
             return 0;
        }

        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $level = (int)$result->fetch_assoc()['level'];
            $stmt->close();
            return $level;
        }
        
        $stmt->close();
        return 0;
    }

    public function canUpgradeBuilding(int $villageId, string $internalName): array
    {
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
             return ['success' => false, 'message' => 'Nieznany typ budynku.'];
        }
        
        if ($currentLevel >= $config['max_level']) {
            return ['success' => false, 'message' => 'Osiągnięto maksymalny poziom dla tego budynku.'];
        }
        
        $stmt_queue = $this->conn->prepare("SELECT COUNT(*) as count FROM building_queue WHERE village_id = ?");
         if ($stmt_queue === false) {
             error_log("Prepare failed for queue check: " . $this->conn->error);
             return ['success' => false, 'message' => 'Błąd serwera podczas sprawdzania kolejki.'];
        }
        $stmt_queue->bind_param("i", $villageId);
        $stmt_queue->execute();
        $queue_result = $stmt_queue->get_result()->fetch_assoc();
        $stmt_queue->close();
        
        if ($queue_result['count'] > 0) {
            return ['success' => false, 'message' => 'W tej wiosce trwa już inna rozbudowa.'];
        }

        $nextLevel = $currentLevel + 1;
        $upgradeCosts = $this->buildingConfigManager->calculateUpgradeCost($internalName, $currentLevel);
        
        if (!$upgradeCosts) {
             return ['success' => false, 'message' => 'Nie można obliczyć kosztów rozbudowy.'];
        }

        $stmt_resources = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
         if ($stmt_resources === false) {
              error_log("Prepare failed for resource check: " . $this->conn->error);
              return ['success' => false, 'message' => 'Błąd serwera podczas pobierania zasobów.'];
         }
        $stmt_resources->bind_param("i", $villageId);
        $stmt_resources->execute();
        $resources = $stmt_resources->get_result()->fetch_assoc();
        $stmt_resources->close();
        
        if (!$resources) {
             return ['success' => false, 'message' => 'Nie można pobrać zasobów wioski.'];
        }

        if ($resources['wood'] < $upgradeCosts['wood'] || 
            $resources['clay'] < $upgradeCosts['clay'] || 
            $resources['iron'] < $upgradeCosts['iron']) {
            return ['success' => false, 'message' => 'Brak wystarczających surowców.'];
        }
        
        $requirementsCheck = $this->checkBuildingRequirements($internalName, $villageId);
        if (!$requirementsCheck['success']) {
            return $requirementsCheck;
        }
        
        return ['success' => true, 'message' => 'Można rozbudować.'];
    }
    
    public function addBuildingToQueue(int $villageId, string $internalName): array
    {
        // Check if there's already an item in the queue for this village
        if ($this->isAnyBuildingInQueue($villageId)) {
            return ['success' => false, 'message' => 'W kolejce budowy tej wioski znajduje się już inne zadanie.'];
        }

        // Get current building level
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        $nextLevel = $currentLevel + 1;

        // Get building config to check max level
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
             return ['success' => false, 'message' => 'Nieznany typ budynku.'];
        }
        
        if ($currentLevel >= $config['max_level']) {
            return ['success' => false, 'message' => 'Osiągnięto maksymalny poziom dla tego budynku.'];
        }

        // Check if requirements are met (although this should be checked before calling this method, good to double-check)
        $requirementsCheck = $this->checkBuildingRequirements($internalName, $villageId);
         if (!$requirementsCheck['success']) {
            return $requirementsCheck; // Return the specific requirement message
        }

        // Calculate upgrade time (need Main Building level)
        $mainBuildingLevel = $this->getBuildingLevel($villageId, 'main_building');
        $upgradeTimeSeconds = $this->buildingConfigManager->calculateUpgradeTime($internalName, $currentLevel, $mainBuildingLevel); // calculateUpgradeTime uses currentLevel

        if ($upgradeTimeSeconds === null) {
             return ['success' => false, 'message' => 'Nie można obliczyć czasu rozbudowy.'];
        }

        $finishTime = date('Y-m-d H:i:s', time() + $upgradeTimeSeconds);

        // Get the village_building_id for the specific building in this village
        $villageBuilding = $this->getVillageBuilding($villageId, $internalName);
        if (!$villageBuilding) {
             return ['success' => false, 'message' => 'Nie znaleziono budynku w wiosce.'];
        }
        $villageBuildingId = $villageBuilding['village_building_id'] ?? null; // Ensure this is the correct column name
        
        // Need building_type_id for the queue table
        $buildingTypeId = $config['id'] ?? null;
        if ($villageBuildingId === null || $buildingTypeId === null) {
             error_log("Missing village_building_id ($villageBuildingId) or building_type_id ($buildingTypeId) for village $villageId, building $internalName");
             return ['success' => false, 'message' => 'Wewnętrzny błąd serwera (missing IDs).'];
        }

        // Add to building_queue table
        $stmt = $this->conn->prepare("INSERT INTO building_queue (village_id, village_building_id, building_type_id, level, start_time, finish_time) VALUES (?, ?, ?, ?, NOW(), ?)");
        if ($stmt === false) {
            error_log("Prepare failed for addBuildingToQueue INSERT: " . $this->conn->error);
            return ['success' => false, 'message' => 'Błąd serwera podczas dodawania do kolejki.'];
        }
        // Bind parameters: i (village_id), i (village_building_id), i (building_type_id), i (level), s (finish_time)
        $stmt->bind_param("iiiis", $villageId, $villageBuildingId, $buildingTypeId, $nextLevel, $finishTime);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Rozbudowa dodana do kolejki.'];
        } else {
             error_log("Execute failed for addBuildingToQueue INSERT: " . $stmt->error);
            $stmt->close();
            return ['success' => false, 'message' => 'Błąd bazy danych podczas dodawania do kolejki.'];
        }
    }

    /**
     * Pobiera dane konkretnego budynku w wiosce (np. jego poziom).
     * @return array|null Tablica z danymi budynku lub null jeśli brak.
     */
    public function getVillageBuilding(int $villageId, string $internalName): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT vb.village_id, vb.building_type_id, vb.level, bt.internal_name, bt.name_pl, bt.production_type
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ? LIMIT 1
        ");

        if ($stmt === false) {
             error_log("Prepare failed for getVillageBuilding: " . $this->conn->error);
             return null;
        }

        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result();

        $building = $result->fetch_assoc();
        $stmt->close();

        return $building;
    }

    /**
     * Pobiera dane konkretnego budynku w wiosce na podstawie jego ID w tabeli village_buildings.
     * Używane do pobrania szczegółów przed akcją (np. rozbudowa).
     * Weryfikuje, czy budynek należy do danej wioski i ma podany internal_name.
     * @return array|null Tablica z danymi budynku (vb.id, vb.level, bt.internal_name, bt.name_pl, bt.description_pl, ...), lub null jeśli brak.
     */
    public function getVillageBuildingDetailsById(int $villageBuildingId, int $villageId, string $internalName): ?array
    {
         $stmt = $this->conn->prepare("
             SELECT vb.id, vb.level, 
                    bt.internal_name, bt.name_pl, bt.description_pl, 
                    bt.production_type, bt.production_initial, bt.production_factor, 
                    bt.max_level, bt.id AS building_type_id
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.id = ? AND vb.village_id = ? AND bt.internal_name = ? LIMIT 1
         ");

         if ($stmt === false) {
             error_log("Prepare failed for getVillageBuildingDetailsById: " . $this->conn->error);
             return null;
         }

         $stmt->bind_param("iis", $villageBuildingId, $villageId, $internalName);
         $stmt->execute();
         $result = $stmt->get_result();

         $building = $result->fetch_assoc();
         $stmt->close();

         return $building;
    }

    /**
     * Sprawdza czy w kolejce budowy dla danej wioski znajduje się jakiekolwiek zadanie.
     * @return bool True jeśli kolejka nie jest pusta, false w przeciwnym razie.
     */
    public function isAnyBuildingInQueue(int $villageId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM building_queue WHERE village_id = ? LIMIT 1");
         if ($stmt === false) {
             error_log("Prepare failed for isAnyBuildingInQueue: " . $this->conn->error);
             return false;
        }

        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        $stmt->close();

        return (int)($row[0] ?? 0) > 0;
    }

    /**
     * Pobiera pojedynczy element z kolejki budowy dla danego budynku w wiosce.
     * Przydatne do sprawdzania statusu rozbudowy.
     * @return array|null Dane zadania budowy lub null jeśli brak.
     */
    public function getBuildingQueueItem(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_building_id, bq.level, bq.finish_time, bt.name_pl, bt.internal_name
            FROM building_queue bq
            JOIN building_types bt ON bq.building_type_id = bt.id
            WHERE bq.village_id = ?
            ORDER BY bq.finish_time ASC LIMIT 1
        ");
         if ($stmt === false) {
             error_log("Prepare failed for getBuildingQueueItem: " . $this->conn->error);
             return null;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item;
    }

    /**
     * Pobiera wszystkie dane budynków dla widoku wioski,
     * łącząc konfigurację, poziom w wiosce, status kolejki
     * oraz informacje o rozbudowie.
     *
     * @param int $villageId ID wioski.
     * @param int $mainBuildingLevel Poziom Ratusza w tej wiosce (potrzebny do obliczeń czasu rozbudowy).
     * @return array Tablica danych budynków gotowa do wyświetlenia.
     */
    public function getVillageBuildingsViewData(int $villageId, int $mainBuildingLevel): array
    {
        $buildingsViewData = [];

        // 1. Pobierz poziomy wszystkich budynków dla danej wioski
        $villageBuildingsLevels = $this->getVillageBuildingsLevels($villageId); // Zakładamy istnienie takiej metody lub implementujemy ją poniżej

        // 2. Pobierz aktualny element kolejki budowy dla tej wioski
        $queueItem = $this->getBuildingQueueItem($villageId);

        // 3. Pobierz konfiguracje wszystkich budynków
        $allBuildingConfigs = $this->buildingConfigManager->getAllBuildingConfigs();

        // 4. Połącz dane i przygotuj strukturę dla widoku
        foreach ($allBuildingConfigs as $config) {
            $internal_name = $config['internal_name'];
            $current_level = $villageBuildingsLevels[$internal_name] ?? 0;
            $max_level = (int)($config['max_level'] ?? 0);

            // Sprawdź, czy budynek jest aktualnie w kolejce rozbudowy
            $is_upgrading = false;
            $queue_finish_time = null;
            $queue_level_after = null;

            if ($queueItem && $queueItem['village_id'] == $villageId && $queueItem['building_internal_name'] == $internal_name) {
                $is_upgrading = true;
                $queue_finish_time = $queueItem['finish_time'];
                // Zakładamy, że poziom w kolejce to zawsze obecny poziom + 1
                $queue_level_after = $current_level + 1;
            }

            // Przygotuj dane o następnej rozbudowie, jeśli możliwa
            $next_level = $current_level + 1;
            $upgrade_costs = null;
            $upgrade_time_seconds = null;
            $can_upgrade = false;
            $upgrade_not_available_reason = '';

            if (!$is_upgrading && $current_level < $max_level) {
                // Sprawdź tylko wymagania budynków i brak kolejki globalnej
                // Check if canUpgradeBuilding needs villageId and internalName only
                $can_upgrade_check = $this->canUpgradeBuilding($villageId, $internal_name); // Ta metoda już sprawdza wymagania budynków i globalną kolejkę
                $can_upgrade = $can_upgrade_check['success'];
                $upgrade_not_available_reason = $can_upgrade_check['message'];
                
                // Sprawdzenie zasobów zostanie wykonane na froncie lub w AJAX handlerze
                // Tutaj obliczamy tylko koszt i czas, jeśli rozbudowa jest technicznie możliwa
                if ($can_upgrade) {
                     $upgrade_costs = $this->buildingConfigManager->calculateUpgradeCost($internal_name, $current_level);
                     $upgrade_time_seconds = $this->buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $mainBuildingLevel);
                }
            }

            $buildingsViewData[$internal_name] = [
                'internal_name' => $internal_name,
                'name_pl' => $config['name_pl'] ?? $internal_name,
                'level' => (int)$current_level,
                'description_pl' => $config['description_pl'] ?? 'Brak opisu.',
                'max_level' => (int)$max_level,
                'is_upgrading' => $is_upgrading,
                'queue_finish_time' => $queue_finish_time,
                'queue_level_after' => $queue_level_after,
                'next_level' => $next_level,
                'upgrade_costs' => $upgrade_costs, // null if not upgradable or upgrading
                'upgrade_time_seconds' => $upgrade_time_seconds, // null if not upgradable or upgrading
                'can_upgrade' => $can_upgrade, // Based on requirements and global queue
                'upgrade_not_available_reason' => $upgrade_not_available_reason,
                 // Dodaj inne potrzebne dane konfiguracyjne, np. production_type, population_cost
                'production_type' => $config['production_type'] ?? null,
                'population_cost' => $config['population_cost'] ?? null, // Koszt populacji na TYM poziomie
                'next_level_population_cost' => $this->buildingConfigManager->calculatePopulationCost($internal_name, $next_level) // Koszt populacji na następnym poziomie
            ];
        }
        
        // Sortowanie budynków (domyślnie po internal_name)
        ksort($buildingsViewData);

        return $buildingsViewData;
    }

     /**
      * Helper method to get levels of all buildings for a village.
      *
      * @param int $villageId ID wioski.
      * @return array Assoc array of building_internal_name => level.
      */
     private function getVillageBuildingsLevels(int $villageId): array
     {
         $levels = [];
         $stmt = $this->conn->prepare("
             SELECT bt.internal_name, vb.level
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.village_id = ?
         ");

         if ($stmt === false) {
              error_log("BuildingManager::getVillageBuildingsLevels prepare failed: " . $this->conn->error);
              return $levels; // Return empty array on error
         }

         $stmt->bind_param("i", $villageId);
         $stmt->execute();
         $result = $stmt->get_result();

         while ($row = $result->fetch_assoc()) {
             $levels[$row['internal_name']] = (int)$row['level'];
         }

         $stmt->close();

         return $levels;
     }

    /**
     * Oblicza bonus do obrony przyznawany przez mur na danym poziomie.
     * Bonus jest wykładniczy, np. 1.04^poziom_muru.
     * @param int $wall_level Poziom muru.
     * @return float Mnożnik bonusu do obrony.
     */
    public function getWallDefenseBonus(int $wall_level): float
    {
        if ($wall_level <= 0) {
            return 1.0; // Brak bonusu
        }

        // Przykładowa formuła: 4% bonusu na każdy poziom, składany wykładniczo
        $base_factor = 1.04;

        return pow($base_factor, $wall_level);
    }

    /**
     * Ustawia poziom budynku w wiosce.
     * @param int $villageId ID wioski.
     * @param string $internalName Wewnętrzna nazwa budynku.
     * @param int $newLevel Nowy poziom budynku.
     * @return bool True jeśli operacja się powiodła, false w przeciwnym razie.
     */
    public function setBuildingLevel(int $villageId, string $internalName, int $newLevel): bool
    {
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
            return false; // Nieznany budynek
        }

        $buildingTypeId = $config['id'];
        $maxLevel = $config['max_level'];

        // Poziom nie może być ujemny ani większy od maksymalnego
        if ($newLevel < 0 || $newLevel > $maxLevel) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE village_buildings SET level = ?
             WHERE village_id = ? AND building_type_id = ?"
        );

        if ($stmt === false) {
            error_log("Prepare failed for setBuildingLevel: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("iii", $newLevel, $villageId, $buildingTypeId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
