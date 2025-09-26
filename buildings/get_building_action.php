<?php
require '../init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Rozpocznij buforowanie wyjścia

try {
    header('Content-Type: application/json');

    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingManager.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'AjaxResponse.php'; // Include AjaxResponse

    // Sprawdź, czy użytkownik jest zalogowany
    if (!isset($_SESSION['user_id'])) {
        ob_clean(); // Wyczyść bufor
        echo json_encode(['error' => 'Użytkownik niezalogowany.']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Użyj globalnego połączenia $conn z init.php
    // $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    // $conn = $database->getConnection();

    if (!$conn) {
        ob_clean();
        echo json_encode(['error' => 'Nie udało się połączyć z bazą danych (z init.php?).']);
        exit();
    }

    // Pobierz ID wioski zalogowanego użytkownika
    $stmt_village = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
    $stmt_village->bind_param("i", $user_id);
    $stmt_village->execute();
    $result_village = $stmt_village->get_result();
    $village = $result_village->fetch_assoc();
    $stmt_village->close();

    if (!$village) {
        // $database->closeConnection(); // Usunięte - połączenie globalne
        ob_clean();
        echo json_encode(['error' => 'Nie znaleziono wioski dla użytkownika.']);
        exit();
    }
    $village_id = $village['id'];

    // Pobierz poziom Ratusza dla obliczeń czasu budowy
    $main_building_level = 0;
    $stmt_mb_level = $conn->prepare("SELECT vb.level FROM village_buildings vb JOIN building_types bt ON vb.building_type_id = bt.id WHERE vb.village_id = ? AND bt.internal_name = 'main_building' LIMIT 1");
    $stmt_mb_level->bind_param("i", $village_id);
    $stmt_mb_level->execute();
    $mb_result = $stmt_mb_level->get_result()->fetch_assoc();
    if ($mb_result) {
        $main_building_level = (int)$mb_result['level'];
    }
    $stmt_mb_level->close();

    // Pobierz building_id (village_buildings.id) i building_type (building_types.internal_name) z zapytania GET
    $village_building_id = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
    $building_type = isset($_GET['building_type']) ? $_GET['building_type'] : '';

    if ($village_building_id <= 0 || empty($building_type)) {
        // $database->closeConnection(); // Usunięte - połączenie globalne
        ob_clean();
        echo json_encode(['error' => 'Nieprawidłowe dane żądania.']);
        exit();
    }

    // Pobierz szczegóły budynku z bazy danych, upewniając się, że należy do wioski użytkownika
     $stmt_building = $conn->prepare("
         SELECT vb.id, vb.level, bt.internal_name, bt.name_pl, bt.description_pl, bt.production_type, bt.production_initial, bt.production_factor, bt.max_level, bt.id AS building_type_id
         FROM village_buildings vb
         JOIN building_types bt ON vb.building_type_id = bt.id
         WHERE vb.id = ? AND vb.village_id = ? AND bt.internal_name = ? LIMIT 1
     ");
     $stmt_building->bind_param("iis", $village_building_id, $village_id, $building_type);
     $stmt_building->execute();
     $result_building = $stmt_building->get_result();
     $building_details = $result_building->fetch_assoc();
     $stmt_building->close();

     if (!$building_details) {
         // $database->closeConnection(); // Usunięte - połączenie globalne
         ob_clean();
         echo json_encode(['error' => 'Nie znaleziono budynku o podanych parametrach w Twojej wiosce.']);
         exit();
     }

    $buildingManager = new BuildingManager($conn);
    $response_data = [];

    // Usunięto zbędne wywołanie getBuildingDetails - podstawowe dane są już w $building_details
    // $buildingDetails = $buildingManager->getBuildingDetails($building_details['building_type_id'], $building_details['level']);

    $response = [
        'building_type_id' => $building_details['building_type_id'],
        'name_pl' => $building_details['name_pl'],
        'level' => $building_details['level'],
        'description_pl' => $building_details['description_pl'],
        'action_type' => 'upgrade', // Domyślna akcja to rozbudowa
        'additional_info_html' => '', // Dodatkowe info w HTML
    ];

    // Sprawdź typ budynku i ustaw odpowiednią akcję oraz dodatkowe dane
    switch ($building_details['building_type_id']) {
        case 1: // Ratusz
            $response['action_type'] = 'manage_village';
            // Pobierz populację wioski
            $stmt_pop = $conn->prepare("SELECT population, name FROM villages WHERE id = ? LIMIT 1"); // Added name
            $stmt_pop->bind_param("i", $village_id);
            $stmt_pop->execute();
            $pop_result = $stmt_pop->get_result()->fetch_assoc();
            $stmt_pop->close();
            $population = $pop_result ? (int)$pop_result['population'] : 0;
            $village_name = $pop_result ? $pop_result['name'] : 'Wioska'; // Get village name

            // Pobierz liczbę wiosek gracza
            $stmt_villages_count = $conn->prepare("SELECT COUNT(*) as cnt FROM villages WHERE user_id = ?");
            $stmt_villages_count->bind_param("i", $user_id);
            $stmt_villages_count->execute();
            $villages_count_result = $stmt_villages_count->get_result()->fetch_assoc();
            $stmt_villages_count->close();
            $villages_count = $villages_count_result ? (int)$villages_count_result['cnt'] : 1;

            // --- MENU ROZBUDOWY BUDYNKÓW ---
            $stmt_buildings = $conn->prepare("
                SELECT vb.id, vb.level, bt.name_pl, bt.internal_name, bt.max_level
                FROM village_buildings vb
                JOIN building_types bt ON vb.building_type_id = bt.id
                WHERE vb.village_id = ?
                ORDER BY bt.id
            ");
            $stmt_buildings->bind_param("i", $village_id);
            $stmt_buildings->execute();
            $buildings_result = $stmt_buildings->get_result();

            $buildings_data = [];
            while ($b = $buildings_result->fetch_assoc()) {
                 $buildings_data[] = [
                     'id' => $b['id'],
                     'level' => (int)$b['level'],
                     'name_pl' => $b['name_pl'],
                     'internal_name' => $b['internal_name'],
                     'max_level' => (int)$b['max_level']
                 ];
            }
            $stmt_buildings->close();

            // Add current village resources and capacities for overview (assuming they are fetched earlier)
            // If not fetched earlier, need to fetch them here
             if (!isset($villageData)) {
                  $villageManager = new VillageManager($conn);
                 $villageData = $villageManager->getVillageInfo($village_id);
             }

             $currentResourcesAndCapacity = [
                  'wood' => $villageData['wood'] ?? 0,
                  'clay' => $villageData['clay'] ?? 0,
                  'iron' => $villageData['iron'] ?? 0,
                  'population' => $villageData['population'] ?? 0,
                  'warehouse_capacity' => $villageData['warehouse_capacity'] ?? 0,
                  'farm_capacity' => $villageData['farm_capacity'] ?? 0
             ];


            // Return data as JSON
            AjaxResponse::success([
                'village_name' => $village_name,
                'main_building_level' => $building_details['level'],
                'population' => $population,
                'villages_count' => $villages_count,
                 'buildings_list' => $buildings_data,
                 'resources_capacity' => $currentResourcesAndCapacity // Add resources/capacity info
            ]);
            break;
        case 2: // Koszary
            $response['action_type'] = 'recruit_barracks';

            // Pobierz dane o jednostkach dostępnych w Koszarach
            require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $barracksUnits = $unitManager->getUnitTypes('barracks');
            
            // Pobierz aktualne jednostki w wiosce
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Pobierz kolejkę rekrutacji
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'barracks');
            
            // Przygotuj dane jednostek do JSON
            $availableUnitsData = [];
            if (!empty($barracksUnits)) {
                 require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitConfigManager.php';
                 $unitConfigManager = new UnitConfigManager($conn);

                foreach ($barracksUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Sprawdź wymagania
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Wymagany poziom budynku: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Oblicz czas rekrutacji
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    
                    $availableUnitsData[] = [
                        'internal_name' => $unitInternal,
                        'name_pl' => $unit['name_pl'],
                        'description_pl' => $unit['description_pl'],
                        'cost_wood' => $unit['cost_wood'],
                        'cost_clay' => $unit['cost_clay'],
                        'cost_iron' => $unit['cost_iron'],
                        'population_cost' => $unit['population_cost'] ?? 0, // Dodaj population_cost
                        'recruit_time_seconds' => $recruitTime,
                        'attack' => $unit['attack'],
                        'defense' => $unit['defense'],
                        'owned' => $villageUnits[$unitInternal] ?? 0,
                        'can_recruit' => $canRecruit,
                        'disable_reason' => $disableReason
                    ];
                }
            }

            // Przygotuj dane kolejki rekrutacji do JSON
            $recruitmentQueueData = [];
            if (!empty($recruitmentQueue)) {
                foreach ($recruitmentQueue as $queue) {
                     $recruitmentQueueData[] = [
                         'id' => $queue['id'],
                         'unit_id' => $queue['unit_id'],
                         'unit_internal_name' => $queue['unit_internal_name'],
                         'unit_name_pl' => $queue['unit_name'], // Assuming unit_name is available
                         'count' => $queue['count'],
                         'count_finished' => $queue['count_finished'],
                         'started_at' => strtotime($queue['started_at']), // Convert to Unix timestamp
                         'finish_at' => strtotime($queue['finish_at']), // Convert to Unix timestamp
                         'time_remaining' => $queue['time_remaining'], // Should be calculated seconds
                         'building_internal_name' => $queue['building_internal_name'], // barracks or stable
                     ];
                }
            }

            // Zwróć dane w formacie JSON
            AjaxResponse::success([
                'building_name_pl' => $building_details['name_pl'],
                'building_level' => $building_details['level'],
                'available_units' => $availableUnitsData,
                'recruitment_queue' => $recruitmentQueueData
            ]);
            break;
        case 3: // Stajnia
            $response['action_type'] = 'recruit_stable';
            
            // Pobierz dane o jednostkach dostępnych w Stajni
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $stableUnits = $unitManager->getUnitTypes('stable');
            
            // Pobierz aktualne jednostki w wiosce
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Pobierz kolejkę rekrutacji
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'stable');
            
            // Przygotuj dane jednostek do JSON
            $availableUnitsData = [];
            if (!empty($stableUnits)) {
                 require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitConfigManager.php';
                 $unitConfigManager = new UnitConfigManager($conn);

                foreach ($stableUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Sprawdź wymagania
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Wymagany poziom budynku: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Oblicz czas rekrutacji
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    
                    $availableUnitsData[] = [
                        'internal_name' => $unitInternal,
                        'name_pl' => $unit['name_pl'],
                        'description_pl' => $unit['description_pl'],
                        'cost_wood' => $unit['cost_wood'],
                        'cost_clay' => $unit['cost_clay'],
                        'cost_iron' => $unit['cost_iron'],
                        'population_cost' => $unit['population_cost'] ?? 0, // Dodaj population_cost
                        'recruit_time_seconds' => $recruitTime,
                        'attack' => $unit['attack'],
                        'defense' => $unit['defense'],
                        'owned' => $villageUnits[$unitInternal] ?? 0,
                        'can_recruit' => $canRecruit,
                        'disable_reason' => $disableReason
                    ];
                }
            }

            // Przygotuj dane kolejki rekrutacji do JSON
            $recruitmentQueueData = [];
            if (!empty($recruitmentQueue)) {
                foreach ($recruitmentQueue as $queue) {
                     $recruitmentQueueData[] = [
                         'id' => $queue['id'],
                         'unit_id' => $queue['unit_id'],
                         'unit_internal_name' => $queue['unit_internal_name'],
                         'unit_name_pl' => $queue['unit_name'], // Assuming unit_name is available
                         'count' => $queue['count'],
                         'count_finished' => $queue['count_finished'],
                         'started_at' => strtotime($queue['started_at']), // Convert to Unix timestamp
                         'finish_at' => strtotime($queue['finish_at']), // Convert to Unix timestamp
                         'time_remaining' => $queue['time_remaining'], // Should be calculated seconds
                         'building_internal_name' => $queue['building_internal_name'], // barracks or stable
                     ];
                }
            }

            // Zwróć dane w formacie JSON
            AjaxResponse::success([
                'building_name_pl' => $building_details['name_pl'],
                'building_level' => $building_details['level'],
                'available_units' => $availableUnitsData,
                'recruitment_queue' => $recruitmentQueueData
            ]);
            break;
        case 4: // Kuźnia
            $response['action_type'] = 'research';
            
            // Pobierz dane o badaniach dostępnych w kuźni
            require_once 'lib/ResearchManager.php';
            $researchManager = new ResearchManager($conn);
            $smithy_research_types = $researchManager->getResearchTypesForBuilding('smithy');

            // Pobierz aktualny poziom kuźni
            $smithy_level = $building_details['level'];

            // Pobierz aktualny poziom badań dla wioski
            $village_research_levels = $researchManager->getVillageResearchLevels($village_id);

            // Pobierz aktualną kolejkę badań dla wioski
            $research_queue = $researchManager->getResearchQueue($village_id);
            $current_research_ids = [];
            foreach ($research_queue as $queue_item) {
                $current_research_ids[$queue_item['research_type_id']] = true;
            }

            // Przygotuj HTML dla interfejsu badań
            $researchHtml = '<h3>Badania Technologii</h3>';
            $researchHtml .= '<p>Tutaj możesz badać nowe technologie militarne i ulepszenia broni.</p>';
            
            if (!empty($smithy_research_types)) {
                $researchHtml .= '<div class="research-list">';
                
                // Najpierw wyświetlamy trwające badania, jeśli istnieją
                if (!empty($research_queue)) {
                    $researchHtml .= '<h4>Aktualne badanie:</h4>';
                    $researchHtml .= '<table class="research-queue">';
                    $researchHtml .= '<tr><th>Badanie</th><th>Docelowy poziom</th><th>Pozostały czas</th><th>Akcja</th></tr>';
                    
                    foreach ($research_queue as $queue) {
                        if ($queue['building_type'] === 'smithy') {
                            $end_time = strtotime($queue['ends_at']);
                            $current_time = time();
                            $remaining_time = max(0, $end_time - $current_time);
                            $time_remaining = gmdate("H:i:s", $remaining_time);
                            
                            $researchHtml .= '<tr>';
                            $researchHtml .= '<td>' . htmlspecialchars($queue['research_name']) . '</td>';
                            $researchHtml .= '<td>' . $queue['level_after'] . '</td>';
                            $researchHtml .= '<td class="build-timer" data-ends-at="' . ($queue['ends_at']) . '" data-item-description="Badanie technologii">';
                            $researchHtml .= $time_remaining;
                            $researchHtml .= '</td>';
                            $researchHtml .= '<td><a href="cancel_research.php?research_queue_id=' . $queue['id'] . '" class="cancel-button">Anuluj</a></td>';
                            $researchHtml .= '</tr>';
                        }
                    }
                    
                    $researchHtml .= '</table>';
                }
                
                // Następnie wyświetlamy dostępne badania
                $researchHtml .= '<h4>Dostępne badania:</h4>';
                $researchHtml .= '<table class="research-options">';
                $researchHtml .= '<tr><th>Technologia</th><th>Poziom</th><th colspan="3">Koszt</th><th>Czas</th><th>Akcja</th></tr>';
                
                foreach ($smithy_research_types as $research) {
                    $research_id = $research['id'];
                    $internal_name = $research['internal_name'];
                    $name = $research['name_pl'];
                    $description = $research['description'];
                    $required_level = $research['required_building_level'];
                    $max_level = $research['max_level'];
                    $current_level = $village_research_levels[$internal_name] ?? 0;
                    $next_level = $current_level + 1;

                    // Sprawdź, czy badanie jest dostępne
                    $is_available = $smithy_level >= $required_level;
                    $is_at_max_level = $current_level >= $max_level;
                    $is_in_progress = isset($current_research_ids[$research_id]);
                    
                    // Oblicz koszt następnego poziomu
                    $cost = null;
                    $time = null;
                    $can_research = false;

                    if (!$is_at_max_level && $is_available && !$is_in_progress) {
                        $cost = $researchManager->getResearchCost($research_id, $next_level);
                        $time = $researchManager->calculateResearchTime($research_id, $next_level, $smithy_level);
                        
                        // Sprawdź, czy gracz ma wystarczające zasoby
                        $can_research = $village['wood'] >= $cost['wood'] && 
                                        $village['clay'] >= $cost['clay'] && 
                                        $village['iron'] >= $cost['iron'];
                    }
                    
                    $researchHtml .= '<tr class="research-item ' . (!$is_available ? 'unavailable' : '') . '">';
                    $researchHtml .= '<td><strong>' . htmlspecialchars($name) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>';
                    $researchHtml .= '<td>' . $current_level . '/' . $max_level . '</td>';
                    
                    if (!$is_at_max_level && !$is_in_progress) {
                        if ($is_available) {
                            $researchHtml .= '<td><img src="img/wood.png" title="Drewno" alt="Drewno"> ' . $cost['wood'] . '</td>';
                            $researchHtml .= '<td><img src="img/stone.png" title="Glina" alt="Glina"> ' . $cost['clay'] . '</td>';
                            $researchHtml .= '<td><img src="img/iron.png" title="Żelazo" alt="Żelazo"> ' . $cost['iron'] . '</td>';
                            $researchHtml .= '<td>' . gmdate("H:i:s", $time) . '</td>';
                            
                            $researchHtml .= '<td>';
                            $researchHtml .= '<form action="start_research.php" method="post" class="research-form">';
                            $researchHtml .= '<input type="hidden" name="village_id" value="' . $village_id . '">';
                            $researchHtml .= '<input type="hidden" name="research_type_id" value="' . $research_id . '">';
                            $researchHtml .= '<button type="submit" class="research-button" ' . ($can_research ? '' : 'disabled') . '>Badaj</button>';
                            $researchHtml .= '</form>';
                            $researchHtml .= '</td>';
                        } else {
                            $researchHtml .= '<td colspan="4">Wymagany poziom kuźni: ' . $required_level . '</td>';
                            $researchHtml .= '<td><button disabled>Niedostępne</button></td>';
                        }
                    } else if ($is_at_max_level) {
                        $researchHtml .= '<td colspan="4">Maksymalny poziom osiągnięty</td>';
                        $researchHtml .= '<td>-</td>';
                    } else if ($is_in_progress) {
                        $researchHtml .= '<td colspan="4">W trakcie badania</td>';
                        $researchHtml .= '<td>-</td>';
                    }
                    
                    $researchHtml .= '</tr>';
                }
                
                $researchHtml .= '</table>';
                $researchHtml .= '</div>';
            } else {
                $researchHtml .= '<p>Brak dostępnych badań w kuźni.</p>';
            }
            
            $response['additional_info_html'] = $researchHtml;
            break;
        case 5: // Tartak
        case 6: // Cegielnia
        case 7: // Huta Żelaza
            $response['action_type'] = 'info_production';
            // Pobierz dane o produkcji
            $productionPerHour = $buildingManager->getHourlyProduction($building_details['internal_name'], $building_details['level']); // Używamy internal_name do getHourlyProduction
            $resourceName = '';
            switch ($building_details['building_type_id']) {
                case 5: $resourceName = 'Drewna'; break;
                case 6: $resourceName = 'Gliny'; break;
                case 7: $resourceName = 'Żelaza'; break;
            }
            $response['additional_info_html'] = '<p>Opis: ' . htmlspecialchars($building_details['description_pl']) . '</p><p>Produkcja: '. $productionPerHour .'/godz. '. $resourceName .'</p>';
            break;
        case 8: // Magazyn
            $response['action_type'] = 'info';
            // Pobierz dane o pojemności
            $storageCapacity = $buildingManager->getWarehouseCapacityByLevel($building_details['level']); // Używamy getWarehouseCapacityByLevel
             $response['additional_info_html'] = '<p>Opis: ' . htmlspecialchars($building_details['description_pl']) . '</p><p>Pojemność magazynu: '. $storageCapacity .'</p>';
            break;
        case 9: // Rynek
            $response['action_type'] = 'trade';
            
            // Pobierz informacje o rynku i kupcach
            $market_level = $building_details['level'];
            $traders_capacity = 3 + floor($market_level * 0.7); // Przykładowa formuła: 3 kupców bazowo + 0.7 na poziom
            
            // Pobierz aktywne transporty
            $active_trades = [];
            $stmt = $conn->prepare("
                SELECT * FROM trade_routes 
                WHERE (source_village_id = ? OR target_village_id = ?) 
                AND arrival_time > NOW()
                ORDER BY arrival_time ASC
            ");
            $stmt->bind_param("ii", $village_id, $village_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $is_outgoing = $row['source_village_id'] == $village_id;
                $direction = $is_outgoing ? 'outgoing' : 'incoming';
                
                // Pobierz dane o wiosce docelowej/źródłowej
                $other_village_id = $is_outgoing ? $row['target_village_id'] : $row['source_village_id'];
                $other_village_stmt = $conn->prepare("
                    SELECT v.name, v.x_coord, v.y_coord, u.username 
                    FROM villages v 
                    JOIN users u ON v.user_id = u.id 
                    WHERE v.id = ?
                ");
                $other_village_stmt->bind_param("i", $other_village_id);
                $other_village_stmt->execute();
                $other_village = $other_village_stmt->get_result()->fetch_assoc();
                $other_village_stmt->close();
                
                $village_name = $other_village ? $other_village['name'] : 'Nieznana wioska';
                $coords = $other_village ? $other_village['x_coord'] . '|' . $other_village['y_coord'] : '?|?';
                $player_name = $other_village ? $other_village['username'] : 'Nieznany gracz';
                
                $arrival_time = strtotime($row['arrival_time']);
                $current_time = time();
                $remaining_time = max(0, $arrival_time - $current_time);
                
                $active_trades[] = [
                    'id' => $row['id'],
                    'direction' => $direction,
                    'wood' => $row['wood'],
                    'clay' => $row['clay'],
                    'iron' => $row['iron'],
                    'village_name' => $village_name,
                    'coords' => $coords,
                    'player_name' => $player_name,
                    'arrival_time' => $row['arrival_time'],
                    'remaining_time' => $remaining_time,
                    'traders_count' => $row['traders_count']
                ];
            }
            $stmt->close();
            
            // Oblicz liczbę dostępnych kupców
            $traders_in_use = 0;
            foreach ($active_trades as $trade) {
                if ($trade['direction'] == 'outgoing') {
                    $traders_in_use += $trade['traders_count'];
                }
            }
            $available_traders = max(0, $traders_capacity - $traders_in_use);
            
            // Generuj HTML
            ob_start();
            echo '<div class="building-actions">';
            echo '<h3>Rynek - handel</h3>';
            echo '<p>Tutaj możesz handlować zasobami z innymi graczami.</p>';
            
            echo '<div class="market-info">';
            echo '<p>Liczba dostępnych kupców: <strong>' . $available_traders . '/' . $traders_capacity . '</strong></p>';
            echo '</div>';
            
            if ($available_traders > 0) {
                echo '<div class="send-resources">';
                echo '<h4>Wyślij zasoby</h4>';
                echo '<form action="send_resources.php" method="post" id="send-resources-form">';
                echo '<input type="hidden" name="village_id" value="' . $village_id . '">';
                
                echo '<div class="form-group">';
                echo '<label for="target_coords">Cel (koordynaty x|y):</label>';
                echo '<input type="text" id="target_coords" name="target_coords" placeholder="500|500" pattern="\d+\|\d+" required>';
                echo '</div>';
                
                echo '<div class="resource-inputs">';
                echo '<div class="resource-input">';
                echo '<label for="wood">Drewno:</label>';
                echo '<input type="number" id="wood" name="wood" min="0" value="0" required>';
                echo '</div>';
                
                echo '<div class="resource-input">';
                echo '<label for="clay">Glina:</label>';
                echo '<input type="number" id="clay" name="clay" min="0" value="0" required>';
                echo '</div>';
                
                echo '<div class="resource-input">';
                echo '<label for="iron">Żelazo:</label>';
                echo '<input type="number" id="iron" name="iron" min="0" value="0" required>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="current-resources">';
                echo '<p>Dostępne zasoby: ';
                echo 'Drewno: <strong>' . floor($village['wood']) . '</strong>, ';
                echo 'Glina: <strong>' . floor($village['clay']) . '</strong>, ';
                echo 'Żelazo: <strong>' . floor($village['iron']) . '</strong>';
                echo '</p>';
                echo '</div>';
                
                echo '<div class="form-actions">';
                echo '<button type="submit" class="send-button">Wyślij zasoby</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="no-traders">';
                echo '<p>Nie masz dostępnych kupców do wysłania zasobów. Poczekaj, aż wrócą z transportu.</p>';
                echo '</div>';
            }
            
            // Wyświetl aktywne transporty
            if (!empty($active_trades)) {
                echo '<div class="active-trades">';
                echo '<h4>Aktywne transporty</h4>';
                echo '<table class="trades-table">';
                echo '<tr><th>Kierunek</th><th>Zasoby</th><th>Cel/Źródło</th><th>Czas przybycia</th></tr>';
                
                foreach ($active_trades as $trade) {
                    echo '<tr>';
                    echo '<td>' . ($trade['direction'] == 'outgoing' ? 'Wysyłka' : 'Odbiór') . '</td>';
                    echo '<td>';
                    echo 'Drewno: ' . $trade['wood'] . '<br>';
                    echo 'Glina: ' . $trade['clay'] . '<br>';
                    echo 'Żelazo: ' . $trade['iron'];
                    echo '</td>';
                    echo '<td>';
                    echo htmlspecialchars($trade['village_name']) . ' (' . $trade['coords'] . ')<br>';
                    echo 'Gracz: ' . htmlspecialchars($trade['player_name']);
                    echo '</td>';
                    echo '<td class="trade-timer" data-ends-at="' . $trade['arrival_time'] . '">' . gmdate("H:i:s", $trade['remaining_time']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="no-trades">';
                echo '<p>Nie masz aktywnych transportów.</p>';
                echo '</div>';
            }
            
            // Funkcjonalność ofert handlowych - przyszła funkcjonalność
            echo '<div class="market-offers">';
            echo '<h4>Oferty handlowe</h4>';
            echo '<p>Funkcjonalność ofert handlowych zostanie dodana w przyszłej aktualizacji.</p>';
            echo '</div>';
            
            echo '</div>';
            $content = ob_get_clean();
            
            $response['additional_info_html'] = $content;
            break;
        case 10: // Pałac/Rezydencja
            $response['action_type'] = 'noble';
             $response['additional_info_html'] = '
                 <h3>Pałac/Rezydencja</h3>
                 <p>Tutaj możesz rekrutować/zarządzać szlachcicami i wybijać monety (w Pałacu).</p>
                 <h4>Opcje:</h4>
                 <ul>
                     <li>Rekrutacja szlachcica: TODO</li>
                     <li>Wybijanie monet: TODO (tylko w Pałacu)</li>
                     <!-- Dodaj inne opcje -->
                 </ul>
             ';
            // TODO: Dodaj interfejs szlachcenia
            break;
         case 11: // Warsztat
             $response['action_type'] = 'recruit_siege';
            
            // Pobierz dane o jednostkach dostępnych w Warsztacie
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $garageUnits = $unitManager->getUnitTypes('garage');
            
            // Pobierz aktualne jednostki w wiosce
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Pobierz kolejkę rekrutacji
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'garage');
            
            // Generuj HTML dla kolejki rekrutacji
            $queueHtml = '';
            if (!empty($recruitmentQueue)) {
                $queueHtml .= '<h4>Aktualnie produkowane maszyny:</h4>';
                $queueHtml .= '<table class="recruitment-queue">';
                $queueHtml .= '<tr><th>Jednostka</th><th>Liczba</th><th>Pozostały czas</th><th>Akcja</th></tr>';
                
                foreach ($recruitmentQueue as $queue) {
                    $remaining = $queue['count'] - $queue['count_finished'];
                    $timeRemaining = gmdate("H:i:s", $queue['time_remaining']);
                    
                    $queueHtml .= '<tr>';
                    $queueHtml .= '<td>' . htmlspecialchars($queue['unit_name']) . '</td>';
                    $queueHtml .= '<td>' . $remaining . ' / ' . $queue['count'] . '</td>';
                    $queueHtml .= '<td class="build-timer" data-ends-at="' . ($queue['finish_at']) . '" data-item-description="Produkcja maszyny oblężniczej">';
                    $queueHtml .= $timeRemaining;
                    $queueHtml .= '</td>';
                    $queueHtml .= '<td><a href="cancel_recruitment.php?queue_id=' . $queue['id'] . '" class="cancel-button">Anuluj</a></td>';
                    $queueHtml .= '</tr>';
                }
                
                $queueHtml .= '</table>';
            }
            
            // Generuj HTML dla formularza rekrutacji
            $unitsHtml = '';
            if (!empty($garageUnits)) {
                $unitsHtml .= '<h4>Dostępne maszyny:</h4>';
                $unitsHtml .= '<form action="recruit_units.php" method="post" id="recruit-form">';
                $unitsHtml .= '<input type="hidden" name="building_type" value="garage">';
                $unitsHtml .= '<table class="recruitment-units">';
                $unitsHtml .= '<tr><th>Jednostka</th><th colspan="3">Koszt</th><th>Czas</th><th>Atak/Obrona</th><th>Posiadane</th><th>Produkuj</th></tr>';
                
                foreach ($garageUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Sprawdź wymagania
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Wymagany poziom budynku: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Oblicz czas rekrutacji
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    $recruitTimeFormatted = gmdate("H:i:s", $recruitTime);
                    
                    $unitsHtml .= '<tr>';
                    $unitsHtml .= '<td><strong>' . htmlspecialchars($unit['name_pl']) . '</strong><br><small>' . htmlspecialchars($unit['description_pl']) . '</small></td>';
                    $unitsHtml .= '<td><img src="img/wood.png" title="Drewno" alt="Drewno"> ' . $unit['cost_wood'] . '</td>';
                    $unitsHtml .= '<td><img src="img/stone.png" title="Glina" alt="Glina"> ' . $unit['cost_clay'] . '</td>';
                    $unitsHtml .= '<td><img src="img/iron.png" title="Żelazo" alt="Żelazo"> ' . $unit['cost_iron'] . '</td>';
                    $unitsHtml .= '<td>' . $recruitTimeFormatted . '</td>';
                    $unitsHtml .= '<td>' . $unit['attack'] . '/' . $unit['defense'] . '</td>';
                    $unitsHtml .= '<td>' . ($villageUnits[$unitInternal] ?? 0) . '</td>';
                    
                    if ($canRecruit) {
                        $unitsHtml .= '<td>';
                        $unitsHtml .= '<input type="number" name="count" class="recruit-count" min="1" max="100" value="1">';
                        $unitsHtml .= '<input type="hidden" name="unit_type_id" value="' . $unit['id'] . '">';
                        $unitsHtml .= '<button type="submit" class="recruit-button">Produkuj</button>';
                        $unitsHtml .= '</td>';
                    } else {
                        $unitsHtml .= '<td title="' . htmlspecialchars($disableReason) . '"><button disabled>Niedostępne</button></td>';
                    }
                    
                    $unitsHtml .= '</tr>';
                }
                
                $unitsHtml .= '</table>';
                $unitsHtml .= '</form>';
            } else {
                $unitsHtml .= '<p>Brak dostępnych maszyn do produkcji.</p>';
            }
            
            // Połącz wszystkie sekcje HTML
            $response['additional_info_html'] = '
                <h3>Warsztat</h3>
                <p>Tutaj możesz produkować maszyny oblężnicze.</p>
                ' . $queueHtml . '
                ' . $unitsHtml . '
            ';
             break;
         case 12: // Akademia
             $response['action_type'] = 'research_advanced';
             
             // Pobierz dane o badaniach dostępnych w akademii
             require_once 'lib/ResearchManager.php';
             if (!isset($researchManager)) {
                 $researchManager = new ResearchManager($conn);
             }
             $academy_research_types = $researchManager->getResearchTypesForBuilding('academy');

             // Pobierz aktualny poziom akademii
             $academy_level = $building_details['level'];

             // Pobierz aktualny poziom badań dla wioski
             $village_research_levels = $researchManager->getVillageResearchLevels($village_id);

             // Pobierz aktualną kolejkę badań dla wioski
             $research_queue = $researchManager->getResearchQueue($village_id);
             $current_research_ids = [];
             foreach ($research_queue as $queue_item) {
                 $current_research_ids[$queue_item['research_type_id']] = true;
             }

             // Przygotuj HTML dla interfejsu badań
             $researchHtml = '<h3>Akademia - Zaawansowane Technologie</h3>';
             $researchHtml .= '<p>Tutaj możesz badać zaawansowane technologie wojskowe i cywilne.</p>';
             
             if (!empty($academy_research_types)) {
                 $researchHtml .= '<div class="research-list">';
                 
                 // Najpierw wyświetlamy trwające badania, jeśli istnieją
                 if (!empty($research_queue)) {
                     $researchHtml .= '<h4>Aktualne badanie:</h4>';
                     $researchHtml .= '<table class="research-queue">';
                     $researchHtml .= '<tr><th>Badanie</th><th>Docelowy poziom</th><th>Pozostały czas</th><th>Akcja</th></tr>';
                     
                     foreach ($research_queue as $queue) {
                         if ($queue['building_type'] === 'academy') {
                             $end_time = strtotime($queue['ends_at']);
                             $current_time = time();
                             $remaining_time = max(0, $end_time - $current_time);
                             $time_remaining = gmdate("H:i:s", $remaining_time);
                             
                             $researchHtml .= '<tr>';
                             $researchHtml .= '<td>' . htmlspecialchars($queue['research_name']) . '</td>';
                             $researchHtml .= '<td>' . $queue['level_after'] . '</td>';
                             $researchHtml .= '<td class="build-timer" data-ends-at="' . ($queue['ends_at']) . '" data-item-description="Badanie zaawansowane">';
                             $researchHtml .= $time_remaining;
                             $researchHtml .= '</td>';
                             $researchHtml .= '<td><a href="cancel_research.php?research_queue_id=' . $queue['id'] . '" class="cancel-button">Anuluj</a></td>';
                             $researchHtml .= '</tr>';
                         }
                     }
                     
                     $researchHtml .= '</table>';
                 }
                 
                 // Następnie wyświetlamy dostępne badania
                 $researchHtml .= '<h4>Dostępne badania:</h4>';
                 $researchHtml .= '<table class="research-options">';
                 $researchHtml .= '<tr><th>Technologia</th><th>Poziom</th><th colspan="3">Koszt</th><th>Czas</th><th>Akcja</th></tr>';
                 
                 foreach ($academy_research_types as $research) {
                     $research_id = $research['id'];
                     $internal_name = $research['internal_name'];
                     $name = $research['name_pl'];
                     $description = $research['description'];
                     $required_level = $research['required_building_level'];
                     $max_level = $research['max_level'];
                     $current_level = $village_research_levels[$internal_name] ?? 0;
                     $next_level = $current_level + 1;

                     // Sprawdź, czy badanie jest dostępne
                     $is_available = $academy_level >= $required_level;
                     $is_at_max_level = $current_level >= $max_level;
                     $is_in_progress = isset($current_research_ids[$research_id]);
                     
                     // Sprawdź warunek poprzedniego badania, jeśli istnieje
                     $prereq_message = '';
                     if ($research['prerequisite_research_id'] && $is_available) {
                         $prereq = $researchManager->getResearchTypeById($research['prerequisite_research_id']);
                         if ($prereq) {
                             $prereq_internal_name = $prereq['internal_name'];
                             $prereq_required_level = $research['prerequisite_research_level'];
                             $prereq_current_level = $village_research_levels[$prereq_internal_name] ?? 0;
                             
                             if ($prereq_current_level < $prereq_required_level) {
                                 $is_available = false;
                                 $prereq_message = "Wymagane badanie: " . $prereq['name_pl'] . " na poziomie " . $prereq_required_level;
                             }
                         }
                     }
                     
                     // Oblicz koszt następnego poziomu
                     $cost = null;
                     $time = null;
                     $can_research = false;

                     if (!$is_at_max_level && $is_available && !$is_in_progress) {
                         $cost = $researchManager->getResearchCost($research_id, $next_level);
                         $time = $researchManager->calculateResearchTime($research_id, $next_level, $academy_level);
                         
                         // Sprawdź, czy gracz ma wystarczające zasoby
                         $can_research = $village['wood'] >= $cost['wood'] && 
                                         $village['clay'] >= $cost['clay'] && 
                                         $village['iron'] >= $cost['iron'];
                     }
                     
                     $researchHtml .= '<tr class="research-item ' . (!$is_available ? 'unavailable' : '') . '">';
                     $researchHtml .= '<td><strong>' . htmlspecialchars($name) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>';
                     $researchHtml .= '<td>' . $current_level . '/' . $max_level . '</td>';
                     
                     if (!$is_at_max_level && !$is_in_progress) {
                         if ($is_available) {
                             $researchHtml .= '<td><img src="img/wood.png" title="Drewno" alt="Drewno"> ' . $cost['wood'] . '</td>';
                             $researchHtml .= '<td><img src="img/stone.png" title="Glina" alt="Glina"> ' . $cost['clay'] . '</td>';
                             $researchHtml .= '<td><img src="img/iron.png" title="Żelazo" alt="Żelazo"> ' . $cost['iron'] . '</td>';
                             $researchHtml .= '<td>' . gmdate("H:i:s", $time) . '</td>';
                             
                             $researchHtml .= '<td>';
                             $researchHtml .= '<form action="start_research.php" method="post" class="research-form">';
                             $researchHtml .= '<input type="hidden" name="village_id" value="' . $village_id . '">';
                             $researchHtml .= '<input type="hidden" name="research_type_id" value="' . $research_id . '">';
                             $researchHtml .= '<button type="submit" class="research-button" ' . ($can_research ? '' : 'disabled') . '>Badaj</button>';
                             $researchHtml .= '</form>';
                             $researchHtml .= '</td>';
                         } else {
                             if ($prereq_message) {
                                 $researchHtml .= '<td colspan="4">' . $prereq_message . '</td>';
                             } else {
                                 $researchHtml .= '<td colspan="4">Wymagany poziom akademii: ' . $required_level . '</td>';
                             }
                             $researchHtml .= '<td><button disabled>Niedostępne</button></td>';
                         }
                     } else if ($is_at_max_level) {
                         $researchHtml .= '<td colspan="4">Maksymalny poziom osiągnięty</td>';
                         $researchHtml .= '<td>-</td>';
                     } else if ($is_in_progress) {
                         $researchHtml .= '<td colspan="4">W trakcie badania</td>';
                         $researchHtml .= '<td>-</td>';
                     }
                     
                     $researchHtml .= '</tr>';
                 }
                 
                 $researchHtml .= '</table>';
                 $researchHtml .= '</div>';
             } else {
                 $researchHtml .= '<p>Brak dostępnych zaawansowanych badań w akademii.</p>';
             }
             
             $response['additional_info_html'] = $researchHtml;
             break;
         case 13: // Odlewnia Monety (dla monet)
             $response['action_type'] = 'mint';
              $response['additional_info_html'] = '
                  <h3>Odlewnia Monety</h3>
                  <p>Tutaj możesz wybijać monety (jeśli masz Pałac).</p>
                  <h4>Opcje:</h4>
                  <p>TODO: Interfejs wybijania monet.</p>
              ';
             // TODO: Dodaj interfejs produkcji monet
             break;
        // Dodaj kolejne przypadki dla innych budynków

         default:
            // Jeśli nie ma specyficznej akcji, pozostaw domyślną akcję 'upgrade'
            $response['action_type'] = 'upgrade';

            $current_level = $building_details['level'];
            $next_level = $current_level + 1;
            $max_level = $building_details['max_level'];
            $internal_name = $building_details['internal_name'];

            $upgradeDetails = null;

            // Sprawdź, czy można jeszcze rozbudować (czy nie osiągnięto max poziomu)
            if ($current_level < $max_level) {
                 // Pobierz koszt rozbudowy
                 $costDetails = $buildingManager->getBuildingUpgradeCost($internal_name, $next_level);

                 // Pobierz czas budowy (wymaga poziomu Ratusza)
                 // $main_building_level jest pobierany na początku skryptu
                 $timeInSeconds = $buildingManager->getBuildingUpgradeTime($internal_name, $next_level, $main_building_level);
                 $timeFormatted = ($timeInSeconds !== null) ? gmdate("H:i:s", $timeInSeconds) : null;

                 if ($costDetails !== null && $timeFormatted !== null) {

                     // Pobierz aktualne surowce wioski
                      $villageManager = new VillageManager($conn);
                      $currentResources = $villageManager->getVillageResources($village_id);

                     // Sprawdź, czy gracz ma wystarczająco surowców
                     $has_resources = ($currentResources['wood'] >= $costDetails['wood'] &&
                                       $currentResources['clay'] >= $costDetails['clay'] &&
                                       $currentResources['iron'] >= $costDetails['iron']);

                     // TODO: Sprawdzić wymagania strukturalne (np. poziom Ratusza, inne budynki)
                     // Placeholder: Na razie zawsze true, ale docelowo tu będzie logika sprawdzająca zależności
                     $requirements_met = true; // Tymczasowo true

                     $can_upgrade = $has_resources && $requirements_met;

                     $upgradeDetails = [
                          'next_level' => $next_level,
                          'max_level' => $max_level,
                          'cost' => [
                              'wood' => $costDetails['wood'],
                              'clay' => $costDetails['clay'],
                              'iron' => $costDetails['iron'],
                          ],
                          'time' => $timeFormatted, // Sformatowany czas budowy
                          'can_upgrade_structurally' => $requirements_met, // Czy spełnione wymagania strukturalne
                          'has_resources' => $has_resources, // Czy gracz ma surowce
                          'can_upgrade' => $can_upgrade // Czy można rozbudować (łącznie)
                     ];

                     // Dodatkowe informacje dla widoku rozbudowy (opcjonalnie)
                      $response['additional_info_html'] = '<p>Opis: ' . htmlspecialchars($building_details['description_pl']) . '</p>'; // Można tu dodać np. bonusy z budynku
                 }
            }

             $response['details'] = [
                 'building_type_id' => $building_details['building_type_id'],
                 'name_pl' => $building_details['name_pl'],
                 'level' => $building_details['level'],
                 'description_pl' => $building_details['description_pl'],
                 'additional_info_html' => $response['additional_info_html'], // Przekazujemy dodatkowe info
                 'upgrade' => $upgradeDetails,
             ];

            break;
    }

    // Jeśli akcja to 'upgrade' i nie ma szczegółów rozbudowy (np. max poziom lub błąd danych),
    // upewnij się, że klucz 'upgrade' w 'details' jest nullem lub nie istnieje.
    // Poprzednia logika już to obsługuje, ale sprawdzam dla pewności.
    if ($response['action_type'] === 'upgrade' && (!isset($response['details']['upgrade']) || !$response['details']['upgrade'])) {
         if (isset($response['details']['upgrade'])) {
             // Jeśli upgrade jest false/null, ale klucz istnieje, usuń go dla czystości
             unset($response['details']['upgrade']);
         }
        // Można też ustawić inny action_type np. 'info_max_level' jeśli chcemy inny widok na max poziomie
         // Na razie pozostajemy przy action_type 'upgrade', ale bez sekcji 'upgrade' w odpowiedzi
    }

    // Wyczyść bufor przed wysłaniem końcowej odpowiedzi JSON
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Wystąpił błąd serwera: ' . $e->getMessage()]);
    error_log("Błąd w get_building_action.php: " . $e->getMessage());
} catch (Error $e) {
     ob_clean();
     header('Content-Type: application/json');
     echo json_encode(['error' => 'Wystąpił krytyczny błąd serwera: ' . $e->getMessage()]);
     error_log("Krytyczny błąd w get_building_action.php: " . $e->getMessage());
}

// Dodano kosmetyczny komentarz, aby potencjalnie odświeżyć cache PHP

?> 