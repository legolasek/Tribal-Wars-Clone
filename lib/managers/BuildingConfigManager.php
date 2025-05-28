<?php

class BuildingConfigManager {
    private $conn;
    private $buildingConfigs = []; // Cache na konfiguracje budynków

    public function __construct($conn) {
        $this->conn = $conn;
        // Opcjonalnie: załadować wszystkie konfiguracje przy inicjalizacji dla lepszej wydajności
        // $this->loadAllBuildingConfigs();
    }

    // Metoda do ładowania wszystkich konfiguracji budynków do cache
    private function loadAllBuildingConfigs() {
        $sql = "SELECT * FROM building_types";
        $result = $this->conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->buildingConfigs[$row['internal_name']] = $row;
            }
            $result->free();
        }
    }

    // Metoda do pobierania konfiguracji pojedynczego typu budynku
    // Preferuje cache, ale pobierze z bazy jeśli brak w cache
    public function getBuildingConfig(string $internalName): ?array {
        if (isset($this->buildingConfigs[$internalName])) {
            return $this->buildingConfigs[$internalName];
        }

        $stmt = $this->conn->prepare("SELECT *, population_cost FROM building_types WHERE internal_name = ? LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("s", $internalName);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        $stmt->close();

        if ($config) {
            $this->buildingConfigs[$internalName] = $config; // Cache the result
            return $config;
        }

        return null; // Building type not found
    }

    /**
     * Pobiera wszystkie konfiguracje budynków.
     * Używa cache, ładuje z bazy jeśli cache jest pusty.
     * @return array Tablica z konfiguracjami budynków, kluczowana internal_name.
     */
    public function getAllBuildingConfigs(): array
    {
        if (empty($this->buildingConfigs)) {
            $this->loadAllBuildingConfigs();
        }
        return $this->buildingConfigs;
    }

    // Metoda do obliczania kosztu budowy/rozbudowy budynku do następnego poziomu
    public function calculateUpgradeCost(string $internalName, int $currentLevel): ?array {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        // Koszt dla poziomu $currentLevel + 1
        $nextLevel = $currentLevel + 1;
        $costWood = round($config['cost_wood_initial'] * ($config['cost_factor'] ** $currentLevel));
        $costClay = round($config['cost_clay_initial'] * ($config['cost_factor'] ** $currentLevel));
        $costIron = round($config['cost_iron_initial'] * ($config['cost_factor'] ** $currentLevel));

        return [
            'wood' => $costWood,
            'clay' => $costClay,
            'iron' => $costIron
        ];
    }

    // Metoda do obliczania czasu budowy/rozbudowy budynku do następnego poziomu
    // Uwzględnia bonus ratusza (Main Building)
    public function calculateUpgradeTime(string $internalName, int $currentLevel, int $mainBuildingLevel = 0): ?int {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        // Bazowy czas dla poziomu $currentLevel + 1
        $nextLevel = $currentLevel + 1;
        $baseTime = round($config['base_build_time_initial'] * ($config['build_time_factor'] ** $currentLevel));

        // Oblicz bonus Ratusza
        $mainBuildingConfig = $this->getBuildingConfig('main_building');
        $timeReductionFactor = $mainBuildingConfig['bonus_time_reduction_factor'] ?? 1.0;
        
        // Bonus Ratusza jest zwykle wykładniczy lub procentowy
        // Przyjmujemy uproszczony model: czas * (współczynnik_redukcji ^ poziom_ratusza)
        // Jeśli współczynnik to np. 0.95, to na poziomie 1 Ratusza czas * 0.95, na poziomie 2 czas * 0.95^2, itd.
        $reducedTime = $baseTime * ($timeReductionFactor ** $mainBuildingLevel);

        // Minimalny czas budowy (np. 1 sekunda)
        return max(1, round($reducedTime));
    }

     // Metoda do obliczania produkcji surowca dla danego typu budynku i poziomu
    public function calculateProduction(string $internalName, int $level): ?float {
        $config = $this->getBuildingConfig($internalName);

        if (!$config || $config['production_type'] === NULL) {
            return null; // Nie produkuje zasobów
        }

        // Produkcja dla danego poziomu
        $production = $config['production_initial'] * ($config['production_factor'] ** ($level - 1));
        
        return $production;
    }
    
    // Metoda do obliczania pojemności magazynu dla danego poziomu
    public function calculateWarehouseCapacity(int $level): ?int {
         $config = $this->getBuildingConfig('warehouse');
         
         if (!$config) {
             return null; // Warehouse config not found
         }
         
         // Pojemność dla danego poziomu (initial + (factor ^ (level - 1))) - może być różnie liczona, dostosuj do gry docelowej
         // Przyjmuję model jak w Plemionach - initial * (factor ^ level)
         $capacity = $config['production_initial'] * ($config['production_factor'] ** $level);
         
         return round($capacity);
    }
    
    // Metoda do obliczania maksymalnej populacji dla danego poziomu Farmy
    public function calculateFarmCapacity(int $level): ?int {
        $config = $this->getBuildingConfig('farm');
        
        if (!$config) {
            return null; // Farm config not found
        }
        
        // Pojemność Farmy
        $capacity = $config['production_initial'] * ($config['production_factor'] ** ($level - 1));
        
        return round($capacity);
    }

    /**
     * Oblicza koszt populacji dla rozbudowy budynku do następnego poziomu.
     * @param string $internalName Wewnętrzna nazwa budynku.
     * @param int $currentLevel Obecny poziom budynku.
     * @return int|null Koszt populacji lub null jeśli błąd.
     */
    public function calculatePopulationCost(string $internalName, int $currentLevel): ?int {
        $config = $this->getBuildingConfig($internalName);

        if (!$config || !isset($config['population_cost'])) {
            return null; // Building config not found or no population_cost defined
        }

        // Koszt populacji dla następnego poziomu (currentLevel + 1)
        // Zakładamy, że population_cost to koszt na poziom
        return (int)$config['population_cost'];
    }

     // Metoda do pobierania wymagań dla danego typu budynku
    public function getBuildingRequirements(string $internalName): array {
        $config = $this->getBuildingConfig($internalName);
        
        if (!$config) {
            return []; // Building config not found
        }
        
        $buildingTypeId = $config['id'];
        
        $stmt = $this->conn->prepare("SELECT required_building, required_level FROM building_requirements WHERE building_type_id = ?");
         if ($stmt === false) {
            error_log("Prepare failed for requirements: " . $this->conn->error);
            return [];
         }
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $requirements = [];
        while ($row = $result->fetch_assoc()) {
            $requirements[] = $row;
        }
        
        $stmt->close();
        
        return $requirements;
    }

    /**
     * Pobiera informacje o produkcji lub pojemności budynku na danym poziomie.
     * @param string $internalName Wewnętrzna nazwa budynku.
     * @param int $level Poziom budynku.
     * @return array|null Tablica z typem ('production' lub 'capacity') i wartością, lub null jeśli brak.
     */
    public function getProductionOrCapacityInfo(string $internalName, int $level): ?array {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        if ($config['production_type'] !== null) {
            $production = $this->calculateProduction($internalName, $level);
            if ($production !== null) {
                return [
                    'type' => 'production',
                    'amount_per_hour' => $production * 3600, // Convert per second to per hour
                    'resource_type' => $config['production_type']
                ];
            }
        }

        if ($internalName === 'warehouse') {
            $capacity = $this->calculateWarehouseCapacity($level);
            if ($capacity !== null) {
                return [
                    'type' => 'capacity',
                    'amount' => $capacity
                ];
            }
        } elseif ($internalName === 'farm') {
             $capacity = $this->calculateFarmCapacity($level);
             if ($capacity !== null) {
                 return [
                     'type' => 'capacity',
                     'amount' => $capacity
                 ];
             }
        }

        return null; // No production or capacity info for this building
    }
}

?>
