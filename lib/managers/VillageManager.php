<?php

/**
 * Klasa zarządzająca wioskami
 * Inspirowana starymi klasami z modelu VeryOldTemplate
 */
class VillageManager
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Pobiera podstawowe informacje o wiosce
     */
    public function getVillageInfo($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM villages 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village_info = $result->fetch_assoc();
        $stmt->close();

        return $village_info;
    }

    /**
     * Alias dla getVillageInfo - dla zachowania kompatybilności z istniejącym kodem
     */
    public function getVillageDetails($village_id)
    {
        return $this->getVillageInfo($village_id);
    }

    /**
     * Aktualizuje surowce w wiosce na podstawie czasu produkcji.
     * Deleguje właściwą logikę do ResourceManager.
     */
    public function updateResources($village_id): bool
    {
        // Pobierz informacje o wiosce
        $village = $this->getVillageInfo($village_id);
        if (!$village) {
            return false; // Wioska nie znaleziona
        }
        
        // Sprawdź, czy minęła wystarczająca ilość czasu na produkcję
        $last_update = strtotime($village['last_resource_update']);
        $elapsed_seconds = time() - $last_update;

        if ($elapsed_seconds <= 0) {
            return false; // Nic się nie zmieniło
        }

        // Wymagany BuildingManager dla ResourceManager, ponieważ oblicza produkcję
        require_once __DIR__ . '/BuildingManager.php';
        // BuildingManager potrzebuje BuildingConfigManager
        require_once __DIR__ . '/BuildingConfigManager.php';

        // Tworzenie instancji Managerów wewnątrz, co jest prostsze w obecnym kodzie,
        // ale docelowo lepsze byłoby wstrzykiwanie zależności do VillageManager.
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);

        require_once __DIR__ . '/ResourceManager.php';
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Deleguj aktualizację do ResourceManager
        // ResourceManager::updateVillageResources wymaga pełnej tablicy wioski
        $updated_village = $resourceManager->updateVillageResources($village);
        
        // updateVillageResources już zapisuje zmiany do bazy, więc tutaj tylko zwracamy status.
        return true; // Zakładamy, że updateVillageResources się powiodło
    }

    /**
     * Pobiera wszystkie budynki w wiosce
     */
    public function getVillageBuildings($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT vb.id, vb.level, 
                   bt.id AS building_type_id, bt.internal_name, bt.name_pl
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buildings = [];
        while ($row = $result->fetch_assoc()) {
            // Przygotuj dane budynku, w tym status rozbudowy z kolejki
            $building = $row;
            $building['is_upgrading'] = false; // Domyślnie nie buduje się
            $building['queue_finish_time'] = null;
            $building['queue_level_after'] = null;

            // Sprawdź kolejkę budowy dla tego konkretnego budynku village_buildings.id
            $stmt_queue = $this->conn->prepare("
                SELECT level, finish_time
                FROM building_queue
                WHERE village_building_id = ?
                LIMIT 1
            ");
            $stmt_queue->bind_param("i", $building['id']);
            $stmt_queue->execute();
            $queue_result = $stmt_queue->get_result();
            $queue_item = $queue_result->fetch_assoc();
            $stmt_queue->close();

            if ($queue_item) {
                $building['is_upgrading'] = true;
                $building['queue_finish_time'] = strtotime($queue_item['finish_time']);
                $building['queue_level_after'] = (int)$queue_item['level'];
            }
            
            $buildings[$building['internal_name']] = $building; // Używamy internal_name jako klucza
        }
        $stmt->close();

        return $buildings;
    }

    /**
     * Przetwarza wszystkie zakończone zadania dla danej wioski (budowa, rekrutacja, badania)
     * Ta metoda powinna być wywoływana np. na początku game.php
     * Zwraca tablicę komunikatów do wyświetlenia.
     */
    public function processCompletedTasksForVillage(int $village_id): array
    {
        $messages = [];
        
        // 1. Zaktualizuj surowce (na wypadek, gdyby gracz był offline)
        // Delegowane do ResourceManager poprzez VillageManager::updateResources
        $this->updateResources($village_id); // Ta metoda teraz wywołuje ResourceManager

        // Wymagane Manager'y
        require_once __DIR__ . '/BuildingManager.php';
        require_once __DIR__ . '/UnitManager.php';
        require_once __DIR__ . '/ResearchManager.php';
        require_once __DIR__ . '/BuildingConfigManager.php';
        require_once __DIR__ . '/ResourceManager.php';

        // Tworzenie instancji (docelowo wstrzykiwanie)
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager); // BuildingManager potrzebuje config
        $unitManager = new UnitManager($this->conn); // UnitManager może potrzebować config/BuildingManager
        $researchManager = new ResearchManager($this->conn); // ResearchManager może potrzebować config/UnitManager/BuildingManager
        // BattleManager również, jeśli przetwarzanie ataków będzie w tej funkcji
        // require_once __DIR__ . '/managers/BattleManager.php'; // Zaktualizowana ścieżka
        // $battleManager = new BattleManager($this->conn, $this); // BattleManager potrzebuje UnitConfigManager i VillageManager

        // 2. Zakończ ukończone zadania budowy
        // Przenieś logikę z game.php do BuildingManager (np. processBuildingQueue)
        // Metoda checkCompletedBuildings jest już w VillageManager, ale jej logika
        // bezpośrednio aktualizuje DB i nie pasuje do nowej kolejki budowy (building_queue)
        // Zmieniamy jej logikę, aby przetwarzała building_queue
        $completed_buildings = $this->processBuildingQueue($village_id); // Nowa/zmodyfikowana metoda w VillageManager lub BuildingManager
        foreach ($completed_buildings as $item) {
             $messages[] = "<p class='success-message'>Ukończono rozbudowę <b>" . htmlspecialchars($item['name_pl']) . "</b> do poziomu " . $item['level'] . "!</p>";
        }

        // 3. Zakończ ukończone zadania rekrutacji jednostek
        // Przenieś logikę z game.php do UnitManager (np. processRecruitmentQueue)
        $recruitmentUpdate = $unitManager->processRecruitmentQueue($village_id); // Metoda już istnieje
         if (!empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['completed_queues'] as $queue) {
                $messages[] = "<p class='success-message'>Ukończono rekrutację " . $queue['count'] . " jednostek '" . htmlspecialchars($queue['unit_name']) . "'!</p>";
            }
        }
         if (!empty($recruitmentUpdate['updated_queues']) && empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['updated_queues'] as $update) {
                 $messages[] = "<p class='success-message'>Wyprodukowano " . $update['units_finished'] . " jednostek '" . htmlspecialchars($update['unit_name']) . "'. Kontynuacja rekrutacji...</p>";
            }
        }

        // 4. Zakończ ukończone badania
        // Przenieś logikę z game.php do ResearchManager (np. processResearchQueue)
        $researchUpdate = $researchManager->processResearchQueue($village_id); // Metoda już istnieje
         if (!empty($researchUpdate['completed_research'])) {
            foreach ($researchUpdate['completed_research'] as $research) {
                $messages[] = "<p class='success-message'>Ukończono badanie <b>" . htmlspecialchars($research['research_name']) . "</b> do poziomu " . $research['level'] . "!</p>";
            }
        }

        // 5. Przetwarzanie zakończonych ataków
        // Logika z game.php powinna zostać przeniesiona do BattleManager
        // BattleManager::processCompletedAttacks(); // Ta metoda prawdopodobnie działa globalnie
        // Tutaj można by pobrać komunikaty dotyczące ataków związanych z wioską użytkownika
        // Wymagałoby to, aby BattleManager zwracał te informacje lub zapisywał je np. w tabeli powiadomień/raportów.

        // Po przetworzeniu wszystkich zadań, upewnij się, że dane wioski w pamięci są aktualne
        // (Niekoniecznie potrzebne, jeśli game.php pobiera dane wioski ponownie po tym wywołaniu)

        // Zwróć zebrane komunikaty
        return $messages;
    }

    /**
     * Nowa/zmodyfikowana metoda do przetwarzania kolejki budowy (building_queue)
     * Przeniesiona logika z game.php, ale z aktualizacją poziomów i usuwaniem z kolejki.
     * Zwraca listę ukończonych zadań budowy.
     */
    public function processBuildingQueue(int $village_id): array
    {
        $completed_queue_items = [];
        
        // Pobierz zakończone budowy z kolejki budowy
        $stmt_check_finished = $this->conn->prepare("SELECT bq.id, bq.village_building_id, bq.level, bt.name_pl FROM building_queue bq JOIN building_types bt ON bq.building_type_id = bt.id WHERE bq.village_id = ? AND bq.finish_time <= NOW()");
        $stmt_check_finished->bind_param("i", $village_id);
        $stmt_check_finished->execute();
        $result_finished = $stmt_check_finished->get_result();
        
        // Rozpocznij transakcję dla przetwarzania zakończonych budów
        $this->conn->begin_transaction();

        try {
            while ($finished_building_queue_item = $result_finished->fetch_assoc()) {
                // Zaktualizuj poziom budynku w village_buildings
                $stmt_update_vb_level = $this->conn->prepare("UPDATE village_buildings SET level = ? WHERE id = ?");
                $stmt_update_vb_level->bind_param("ii", $finished_building_queue_item['level'], $finished_building_queue_item['village_building_id']);
                if (!$stmt_update_vb_level->execute()) {
                    throw new Exception("Błąd aktualizacji poziomu budynku po zakończeniu budowy dla village_building_id " . $finished_building_queue_item['village_building_id'] . ".");
                }
                $stmt_update_vb_level->close();

                // Usuń zadanie z kolejki budowy
                $stmt_delete_queue_item = $this->conn->prepare("DELETE FROM building_queue WHERE id = ?");
                $stmt_delete_queue_item->bind_param("i", $finished_building_queue_item['id']);
                if (!$stmt_delete_queue_item->execute()) {
                     throw new Exception("Błąd usuwania zadania z kolejki po zakończeniu budowy dla id " . $finished_building_queue_item['id'] . ".");
                }
                $stmt_delete_queue_item->close();
                
                $completed_queue_items[] = $finished_building_queue_item; // Dodaj do listy ukończonych
            }

            // Zatwierdź transakcję
            $this->conn->commit();

        } catch (Exception $e) {
            // Wycofaj transakcję w przypadku błędu
            $this->conn->rollback();
             error_log("Błąd w processBuildingQueue dla wioski {$village_id}: " . $e->getMessage());
            // Możesz zwrócić pustą tablicę lub zgłosić błąd
            throw $e; // Rzuć wyjątek dalej
        } finally {
             $result_finished->free(); // Zwolnij pamięć
            $stmt_check_finished->close(); // Zamknij zapytanie
        }

        return $completed_queue_items;
    }

    /**
     * Aktualizuje populację w wiosce na podstawie poziomów budynków
     * Ta metoda jest wywoływana po zakończeniu budowy budynku (np. farmy)
     */
    public function updateVillagePopulation(int $village_id): bool
    {
        // Wymagany BuildingConfigManager do obliczenia pojemności farmy
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Tworzenie instancji (docelowo wstrzykiwanie)

        // Pobierz poziom farmy
        $farm_level = 0;
        $farm_stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'farm'
        ");
        $farm_stmt->bind_param("i", $village_id);
        $farm_stmt->execute();
        $farm_result = $farm_stmt->get_result();
        if ($farm_row = $farm_result->fetch_assoc()) {
            $farm_level = $farm_row['level'];
        }
        $farm_stmt->close();

        // Oblicz maksymalną populację z farmy
        $max_population = $buildingConfigManager->calculateFarmCapacity($farm_level);
        
        // Docelowo, populacja wioski powinna być sumą zużytej populacji przez budynki i jednostki.
        // Na razie, aktualizujemy tylko max_population (farm_capacity) w tabeli villages.

        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET farm_capacity = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $max_population, $village_id);
        $result = $update_stmt->execute();
        $update_stmt->close();

        return $result;
    }

    /**
     * Tworzy nową wioskę dla użytkownika z podstawowymi budynkami.
     * Używa transakcji dla atomowości.
     */
    public function createVillage($user_id, $name = '', $x = null, $y = null)
    {
        // Pobierz nazwę użytkownika
        $stmt_user = $this->conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();
        $username = $user ? $user['username'] : 'Gracz';
        
        // Unikalna nazwa wioski
        $village_name = $name ?: ("Wioska " . $username);
        
        // Losuj wolne koordynaty w centrum mapy (np. 40-60)
        $found = false;
        $tries = 0;
        do {
            $x_try = $x ?? rand(40, 60);
            $y_try = $y ?? rand(40, 60);
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE x_coord = ? AND y_coord = ?");
            $stmt->bind_param("ii", $x_try, $y_try);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($cnt == 0) { $found = true; $x = $x_try; $y = $y_try; }
            $tries++;
        } while (!$found && $tries < 100);
        if (!$found) return ['success'=>false,'message'=>'Brak wolnych pól w centrum mapy!'];
        
        // Startowe surowce i budynki
        $worldId = defined('INITIAL_WORLD_ID') ? INITIAL_WORLD_ID : 1;
        $wood = defined('INITIAL_WOOD') ? INITIAL_WOOD : 500;
        $clay = defined('INITIAL_CLAY') ? INITIAL_CLAY : 500;
        $iron = defined('INITIAL_IRON') ? INITIAL_IRON : 500;
        $warehouse = defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000;
        $population = defined('INITIAL_POPULATION') ? INITIAL_POPULATION : 1;
        
        $stmt = $this->conn->prepare("INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisiiiiiii", $user_id, $worldId, $village_name, $x, $y, $wood, $clay, $iron, $warehouse, $population);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Błąd podczas tworzenia wioski: ' . $stmt->error];
        }
        $village_id = $stmt->insert_id;
        $stmt->close();
        
        // Startowe budynki: ratusz, tartak, cegielnia, huta, magazyn (wszystko poziom 1)
        $basic_buildings = [
            'main_building' => 1,
            'sawmill' => 1,
            'clay_pit' => 1,
            'iron_mine' => 1,
            'warehouse' => 1,
            'farm' => 1
        ];
        foreach ($basic_buildings as $building_name => $level) {
            $this->createBuildingInVillage($village_id, $building_name, $level);
        }
        $this->updateVillagePopulation($village_id);
        // Przelicz punkty gracza
        if (method_exists($this, 'recalculatePlayerPoints')) {
            $this->recalculatePlayerPoints($user_id);
        }
        return [
            'success' => true, 
            'message' => 'Wioska została utworzona pomyślnie!', 
            'village_id' => $village_id
        ];
    }

    /**
     * Tworzy domyślne budynki dla nowej wioski.
     * @param int $village_id ID nowo utworzonej wioski.
     * @return bool Sukces operacji.
     */
    private function createInitialBuildings(int $village_id): bool
    {
         // Wymagany BuildingConfigManager do pobrania typów budynków
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Tworzenie instancji (docelowo wstrzykiwanie)

        // Pobierz listę wszystkich typów budynków z bazy
        $buildingTypes = $buildingConfigManager->getAllBuildingConfigs(); // Potrzebna publiczna metoda

        if (empty($buildingTypes)) {
            error_log("Błąd: Brak typów budynków w bazie danych podczas tworzenia wioski.");
            return false; // Nie można utworzyć budynków, jeśli brak typów
        }

        $insert_stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level)
            VALUES (?, ?, ?)
        ");
        
        foreach ($buildingTypes as $type) {
             // Dla nowej wioski, wszystkie budynki zaczynają na poziomie 0 lub 1 (Ratusz)
             $initial_level = ($type['internal_name'] === 'main_building') ? 1 : 0;
             // Znajdź building_type_id na podstawie internal_name
             $building_type_id = $type['id']; // Zakładamy, że id jest w konfiguracji

            $insert_stmt->bind_param("iii", $village_id, $building_type_id, $initial_level);
            if (!$insert_stmt->execute()) {
                error_log("Błąd dodawania budynku '{$type['internal_name']}' do wioski {$village_id}: " . $insert_stmt->error);
                $insert_stmt->close();
                return false; // Błąd dodawania budynku
            }
        }
        
        $insert_stmt->close();

        return true;
    }


    /**
     * Tworzy pojedynczy budynek w wiosce z określonym poziomem.
     * Używane głównie podczas inicjalizacji wioski.
     * @deprecated Preferuj createInitialBuildings.
     */
    private function createBuildingInVillage($village_id, $building_internal_name, $level = 0)
    {
        // Pobierz ID typu budynku
        $type_stmt = $this->conn->prepare("
            SELECT id 
            FROM building_types 
            WHERE internal_name = ?
        ");
        $type_stmt->bind_param("s", $building_internal_name);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        
        if ($type_result->num_rows === 0) {
            return false;
        }
        
        $building_type_id = $type_result->fetch_assoc()['id'];
        $type_stmt->close();
        
        // Dodaj budynek do wioski
        $stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $village_id, $building_type_id, $level);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Zmienia nazwę wioski.
     */
    public function renameVillage($village_id, $user_id, $new_name)
    {
        // Sprawdź czy wioska należy do użytkownika
        $stmt = $this->conn->prepare("
            SELECT id 
            FROM villages 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $village_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Nie masz dostępu do tej wioski.'];
        }
        $stmt->close();
        
        // Zmień nazwę wioski
        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET name = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $new_name, $village_id);
        
        if (!$update_stmt->execute()) {
            return ['success' => false, 'message' => 'Błąd podczas zmiany nazwy: ' . $update_stmt->error];
        }
        $update_stmt->close();
        
        return ['success' => true, 'message' => 'Nazwa wioski została zmieniona.'];
    }

    /**
     * Pobiera listę wszystkich wiosek danego użytkownika.
     * @return array Tablica wiosek lub pusta tablica.
     */
    public function getUserVillages(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $villages = [];
        while ($row = $result->fetch_assoc()) {
            $villages[] = $row;
        }
        $stmt->close();

        return $villages;
    }

     /**
     * Pobiera tylko ID wszystkich wiosek danego użytkownika.
     * Potrzebne do sprawdzania ataków.
     * @return array Tablica ID wiosek lub pusta tablica.
     */
    public function getUserVillageIds(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $village_ids = [];
        while ($row = $result->fetch_assoc()) {
            $village_ids[] = (int)$row['id'];
        }
        $stmt->close();

        return $village_ids;
    }

    /**
     * Pobiera pierwszą (lub domyślną) wioskę użytkownika.
     * Używana np. po zalogowaniu.
     * @return array|null Tablica danych wioski lub null, jeśli brak wiosek.
     */
    public function getFirstVillage(int $user_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update
            FROM villages
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village = $result->fetch_assoc();
        $stmt->close();

        return $village;
    }

    /**
     * Pobiera aktualną produkcję surowca na godzinę dla danej wioski.
     * @deprecated Preferuj ResourceManager->getHourlyProductionRate
     */
    public function getResourceProduction($village_id, $resource_type)
    {
        // Ta metoda jest przestarzała. Jej funkcjonalność powinna być obsługiwana przez ResourceManager.
        // Tymczasowa implementacja, aby naprawić błąd ArgumentCountError.
        
        require_once __DIR__ . '/BuildingConfigManager.php'; // Poprawiona ścieżka
        require_once __DIR__ . '/BuildingManager.php'; // Poprawiona ścieżka
        require_once __DIR__ . '/ResourceManager.php'; // Poprawiona ścieżka

        // Tworzenie instancji potrzebnych managerów (tymczasowe rozwiązanie)
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Użyj ResourceManager do pobrania produkcji
        return $resourceManager->getHourlyProductionRate($village_id, $resource_type);
    }

    /**
     * Pobiera aktualny (pierwszy w kolejce) element budowy dla danej wioski.
     * Zwraca tablicę z danymi zadania budowy lub null, jeśli kolejka jest pusta.
     */
    public function getBuildingQueueItem(int $village_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_building_id, bq.building_type_id, bq.level, bq.start_time, bq.finish_time, 
                   bt.internal_name, bt.name_pl
            FROM building_queue bq
            JOIN building_types bt ON bq.building_type_id = bt.id
            WHERE bq.village_id = ?
            ORDER BY bq.start_time ASC
            LIMIT 1
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        return $item;
    }
} 