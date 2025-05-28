<?php
/**
 * AJAX - Pobieranie aktualnych zasobów wioski
 * Zwraca aktualne wartości zasobów i inne informacje o wiosce w formacie JSON
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/AjaxResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/BuildingConfigManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/BuildingManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ResourceManager.php';


// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Użytkownik nie jest zalogowany', null, 401);
}

try {
    // Stwórz instancje managerów
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $villageManager = new VillageManager($conn);
    $resourceManager = new ResourceManager($conn, $buildingManager);

    // Pobierz ID wioski
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;
    
    // Jeśli nie podano ID wioski, pobierz pierwszą wioskę użytkownika
    if (!$village_id) {
        $village_data = $villageManager->getFirstVillage($_SESSION['user_id']);
        
        if (!$village_data) {
            AjaxResponse::error('Nie znaleziono wioski', null, 404);
        }
        $village_id = $village_data['id'];
    }
    
    // Sprawdź, czy wioska należy do zalogowanego użytkownika
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village_owner = $result->fetch_assoc();
    $stmt->close();
    
    if (!$village_owner || $village_owner['user_id'] != $_SESSION['user_id']) {
        AjaxResponse::error('Brak uprawnień do tej wioski', null, 403);
    }
    
    // Aktualizuj zasoby wioski
    $villageManager->updateResources($village_id);
    
    // Pobierz aktualne dane wioski
    $village = $villageManager->getVillageInfo($village_id);
    
    // Pobierz budynki produkcyjne i ich poziomy - optymalizacja zapytania
    $stmt = $conn->prepare("
        SELECT bt.internal_name, vb.level, bt.production_type, bt.production_initial, bt.production_factor
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        WHERE vb.village_id = ? AND (bt.production_type IS NOT NULL OR bt.internal_name = 'warehouse')
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $production_buildings = [];
    $warehouse_level = 0;
    $wood_production = 0;
    $clay_production = 0;
    $iron_production = 0;
    
    while ($row = $result->fetch_assoc()) {
        $internal_name = $row['internal_name'];
        $level = $row['level'];
        
        // Zapisz poziom magazynu
        if ($internal_name === 'warehouse') {
            $warehouse_level = $level;
            continue;
        }
        
        // Oblicz produkcję dla budynków produkcyjnych
        if ($row['production_type'] && $row['production_initial'] && $row['production_factor']) {
            $production = floor($row['production_initial'] * pow($row['production_factor'], $level - 1));
            
            // Przypisz do odpowiedniego surowca
            if ($internal_name === 'sawmill' || $internal_name === 'wood_production') {
                $wood_production = $production;
            } else if ($internal_name === 'clay_pit' || $internal_name === 'clay_production') {
                $clay_production = $production;
            } else if ($internal_name === 'iron_mine' || $internal_name === 'iron_production') {
                $iron_production = $production;
            }
        }
    }
    $stmt->close();
    
    // Oblicz pojemność magazynu
    $warehouse_capacity = $buildingManager->getWarehouseCapacityByLevel($warehouse_level);
    
    // Sprawdź, czy są budynki w trakcie budowy
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM building_queue 
        WHERE village_id = ? AND finish_time > NOW()
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $building_queue = $result->fetch_assoc();
    $stmt->close();
    
    // Sprawdź, czy jest rekrutacja jednostek
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM unit_queue 
        WHERE village_id = ? AND finish_at > UNIX_TIMESTAMP()
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recruitment_queue = $result->fetch_assoc();
    $stmt->close();
    
    // Przygotuj dane do zwrócenia
    $resources_data = [
        'wood' => [
            'amount' => round($village['wood']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $wood_production,
            'production_per_second' => round($wood_production / 3600, 2)
        ],
        'clay' => [
            'amount' => round($village['clay']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $clay_production,
            'production_per_second' => round($clay_production / 3600, 2)
        ],
        'iron' => [
            'amount' => round($village['iron']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $iron_production,
            'production_per_second' => round($iron_production / 3600, 2)
        ],
        'population' => [
            'amount' => round($village['population']),
            'capacity' => $village['farm_capacity']
        ],
        'village_name' => $village['name'],
        'village_id' => $village_id,
        'coords' => $village['x_coord'] . '|' . $village['y_coord'],
        'buildings_in_queue' => $building_queue['count'],
        'units_in_recruitment' => $recruitment_queue['count'],
        'last_update' => $village['last_resource_update'],
        'current_server_time' => date('Y-m-d H:i:s')
    ];
    
    // Zwróć dane w formacie JSON
    AjaxResponse::success($resources_data);
    
} catch (Exception $e) {
    // Obsłuż wyjątek i zwróć błąd z pełnymi szczegółami
    AjaxResponse::error(
        'Wystąpił błąd: ' . $e->getMessage(),
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ],
        500
    );
}
