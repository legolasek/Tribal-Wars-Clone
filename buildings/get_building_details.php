<?php
require '../init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Rozpocznij buforowanie wyjścia

try {
    header('Content-Type: application/json');

    require_once '../lib/managers/BuildingManager.php';
    require_once '../lib/managers/VillageManager.php';
    require_once '../lib/functions.php'; // Zakładam, że mamy plik z funkcjami pomocniczymi
    require_once '../lib/managers/BuildingConfigManager.php'; // Dołącz nowy Manager
    require_once '../lib/managers/ResourceManager.php'; // Needed for current resources

    // Sprawdź, czy użytkownik jest zalogowany
    if (!isset($_SESSION['user_id'])) {
        ob_clean(); // Wyczyść bufor
        echo json_encode(['error' => 'Nie jesteś zalogowany.']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Sprawdź, czy przekazano village_id i building_internal_name
    if (!isset($_GET['village_id']) || !is_numeric($_GET['village_id']) ||
        !isset($_GET['building_internal_name']) || empty($_GET['building_internal_name'])) {
        ob_clean();
        echo json_encode(['error' => 'Nieprawidłowe parametry zapytania (village_id, building_internal_name).']);
        exit();
    }

    $village_id = (int)$_GET['village_id'];
    $internal_name = $_GET['building_internal_name'];

    // Database connection provided by init.php ($conn)
    if (!$conn) {
        ob_clean();
        echo json_encode(['error' => 'Nie udało się połączyć z bazą danych.']);
        exit();
    }

    // Stwórz instancje Managerów
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager); // Przekaż BuildingConfigManager
    $villageManager = new VillageManager($conn);
    $resourceManager = new ResourceManager($conn, $buildingManager); // Pass BuildingManager to ResourceManager

    // Sprawdź, czy wioska należy do użytkownika
    $villageData = $villageManager->getVillageInfo($village_id);
    if (!$villageData || $villageData['user_id'] != $user_id) {
        ob_clean();
        echo json_encode(['error' => 'Brak dostępu do wioski.']);
        exit();
    }

    // Pobierz dane konkretnego budynku w wiosce
    $building = $buildingManager->getVillageBuilding($village_id, $internal_name);

    $current_level = $building ? (int)$building['level'] : 0;

    // Pobierz konfigurację budynku (potrzebna dla max_level, kosztów, czasu itp.)
    $buildingConfig = $buildingConfigManager->getBuildingConfig($internal_name);
    if (!$buildingConfig) {
        ob_clean();
        echo json_encode(['error' => 'Nie znaleziono konfiguracji budynku.']);
        exit();
    }
    $max_level = (int)$buildingConfig['max_level'];

    // Sprawdź, czy budynek jest w trakcie rozbudowy (użyj BuildingManager lub BuildingQueueManager)
    // Potrzebna metoda w BuildingManager lub nowy BuildingQueueManager
    // Na razie proste zapytanie (lub użycie BuildingManager::getBuildingQueueItem, jeśli istnieje)
    
    // Sprawdź kolejkę budowy dla tej wioski
    $queue_item = $buildingManager->getBuildingQueueItem($village_id);
    $is_upgrading = ($queue_item && $queue_item['internal_name'] === $internal_name);
    $upgrade_info = $is_upgrading ? $queue_item : null;

    // Pobierz poziom ratusza (main_building) dla kalkulacji czasu budowy
    $main_building_level = $buildingManager->getBuildingLevel($village_id, 'main_building');

    // Przygotowanie danych odpowiedzi
    $response = [
        'internal_name' => $internal_name,
        'name_pl' => $buildingConfig['name_pl'],
        'level' => $current_level,
        'max_level' => $max_level,
        'description_pl' => $buildingConfig['description_pl'] ?? 'Brak opisu.',
        'production_type' => $buildingConfig['production_type'],
        'is_upgrading' => $is_upgrading,
        'queue_finish_time' => null, // Ustawienie domyślne
        'queue_level_after' => null, // Ustawienie domyślne
        'can_upgrade' => false, // Domyślnie nie można
        'upgrade_costs' => null,
        'upgrade_time_seconds' => null,
        'upgrade_time_formatted' => null,
        'requirements' => [],
        'current_village_resources' => [
             'wood' => (int)($villageData['wood'] ?? 0),
             'clay' => (int)($villageData['clay'] ?? 0),
             'iron' => (int)($villageData['iron'] ?? 0),
             'population' => (int)($villageData['population'] ?? 0),
             'warehouse_capacity' => (int)($villageData['warehouse_capacity'] ?? 0),
             'farm_capacity' => (int)($villageData['farm_capacity'] ?? 0) // Potrzebna kolumna lub obliczenie
        ],
        'production_info' => null, // Informacje o produkcji/pojemności
        'upgrade_not_available_reason' => ''
    ];

    // Dodaj szczegóły rozbudowy, jeśli jest w trakcie
    if ($is_upgrading) {
        $response['queue_level_after'] = (int)$upgrade_info['level']; // Zmieniono z level_after na level zgodne z building_queue
        $response['queue_finish_time'] = (int)strtotime($upgrade_info['finish_time']); // Zmieniono z ends_at na finish_time
        $response['upgrade_not_available_reason'] = 'Budynek w trakcie rozbudowy.';
    } else {
        // Jeśli budynek nie jest w trakcie rozbudowy, sprawdź możliwość rozbudowy na następny poziom
        if ($current_level < $max_level) {
            // Sprawdź czy jest zadanie w kolejce budowy (dla tej wioski) - użyj BuildingManager
            $isAnyBuildingInQueue = $buildingManager->isAnyBuildingInQueue($village_id); // Potrzebna metoda
            
            if ($isAnyBuildingInQueue) {
                 $response['upgrade_not_available_reason'] = 'Inny budynek jest już w trakcie rozbudowy w tej wiosce.';
            } else {
                // Oblicz koszty i czas rozbudowy na następny poziom używając BuildingConfigManager
                $next_level = $current_level + 1;
                $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($internal_name, $current_level); // calculateUpgradeCost przyjmuje currentLevel
                $upgrade_time_seconds = $buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $main_building_level); // calculateUpgradeTime przyjmuje currentLevel
                
                if ($upgrade_costs && $upgrade_time_seconds !== null) {
                    $response['upgrade_costs'] = $upgrade_costs;
                    $response['upgrade_time_seconds'] = $upgrade_time_seconds;
                    $response['upgrade_time_formatted'] = formatDuration($upgrade_time_seconds); // Użyj funkcji formatującej czas

                    // Sprawdź wymagania dotyczące innych budynków używając BuildingConfigManager (przez BuildingManager)
                    $requirementsCheck = $buildingManager->checkBuildingRequirements($internal_name, $village_id);
                    $response['requirements'] = $requirementsCheck; // Przekaż wynik sprawdzenia wymagań

                    // Sprawdź, czy gracz ma wystarczające zasoby
                    $hasEnoughResources = true;
                    $missingResources = [];
                    if ($villageData['wood'] < $upgrade_costs['wood']) { $hasEnoughResources = false; $missingResources[] = 'Drewno'; }
                    if ($villageData['clay'] < $upgrade_costs['clay']) { $hasEnoughResources = false; $missingResources[] = 'Glina'; }
                    if ($villageData['iron'] < $upgrade_costs['iron']) { $hasEnoughResources = false; $missingResources[] = 'Żelazo'; }

                    // Sprawdź populację (czy farma udźwignie kolejny poziom)
                    $populationCost = $buildingConfigManager->calculatePopulationCost($internal_name, $current_level);
                    $currentPopulation = $villageData['population'];
                    $farmCapacity = $villageData['farm_capacity'];

                    $populationCheck = ['success' => true, 'message' => ''];
                    if ($populationCost !== null && ($currentPopulation + $populationCost > $farmCapacity)) {
                        $populationCheck = ['success' => false, 'message' => 'Brak wystarczającej wolnej populacji. Wymagana wolna populacja: ' . $populationCost . '. Dostępna: ' . ($farmCapacity - $currentPopulation) . '.'];
                    }

                    if ($hasEnoughResources && $requirementsCheck['success'] && $populationCheck['success']) {
                         $response['can_upgrade'] = true;
                         $response['upgrade_not_available_reason'] = ''; // Wyczyść powód, jeśli można
                    } else {
                         $response['can_upgrade'] = false;
                         if (!$hasEnoughResources) {
                              $response['upgrade_not_available_reason'] = 'Brak wystarczających surowców: ' . implode(', ', $missingResources) . '.';
                         } elseif (!$requirementsCheck['success']) {
                              $response['upgrade_not_available_reason'] = $requirementsCheck['message'];
                         } elseif (!$populationCheck['success']) {
                              $response['upgrade_not_available_reason'] = $populationCheck['message'];
                         }
                    }

                } else {
                    $response['upgrade_not_available_reason'] = 'Nie można obliczyć kosztów lub czasu rozbudowy.';
                }
            }
        } else {
            $response['upgrade_not_available_reason'] = 'Budynek osiągnął maksymalny poziom.';
        }
    }

    // Dodaj informacje o produkcji lub pojemności
    $productionInfo = $buildingConfigManager->getProductionOrCapacityInfo($internal_name, $current_level);
    if ($productionInfo) {
        $response['production_info'] = $productionInfo;
        // Calculate for next level if not maxed
        if ($current_level < $max_level) {
             $productionInfoNextLevel = $buildingConfigManager->getProductionOrCapacityInfo($internal_name, $current_level + 1);
             if ($productionInfoNextLevel) {
                  if ($productionInfo['type'] === 'production') {
                       $response['production_info']['amount_per_hour_next_level'] = $productionInfoNextLevel['amount_per_hour'];
                  } elseif ($productionInfo['type'] === 'capacity') {
                       $response['production_info']['amount_next_level'] = $productionInfoNextLevel['amount'];
                  }
             }
        }
    }

    ob_clean(); // Wyczyść bufor przed wysłaniem JSON
    echo json_encode($response);

} catch (Exception $e) {
    ob_clean(); // Wyczyść bufor
    error_log("Error in get_building_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Wystąpił błąd serwera: ' . $e->getMessage()]);
}

// Połączenie z bazą danych zostanie zamknięte automatycznie na końcu skryptu

?>
