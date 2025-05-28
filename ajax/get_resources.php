<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/functions.php'; // Dodano, aby funkcje globalne były dostępne
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ResourceManager.php'; // Potrzebny do obliczania produkcji

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nie jesteś zalogowany.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

if (!$village_id) {
    echo json_encode(['error' => 'Brak ID wioski.']);
    exit();
}

$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, new BuildingManager($conn, new BuildingConfigManager($conn))); // BuildingManager i BuildingConfigManager są potrzebne przez ResourceManager

// Sprawdź, czy wioska należy do zalogowanego użytkownika
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['error' => 'Brak dostępu do wioski.']);
    exit();
}

// Zaktualizuj zasoby wioski przed ich pobraniem
$villageManager->updateResources($village_id);

// Pobierz zaktualizowane zasoby
$currentRes = $villageManager->getVillageInfo($village_id);

if ($currentRes) {
    $wood_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'wood');
    $clay_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'clay');
    $iron_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'iron');

    $response_data = [
        'wood' => [
            'amount' => (int)$currentRes['wood'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $wood_prod_per_hour,
            'production_per_second' => $wood_prod_per_hour / 3600,
        ],
        'clay' => [
            'amount' => (int)$currentRes['clay'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $clay_prod_per_hour,
            'production_per_second' => $clay_prod_per_hour / 3600,
        ],
        'iron' => [
            'amount' => (int)$currentRes['iron'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $iron_prod_per_hour,
            'production_per_second' => $iron_prod_per_hour / 3600,
        ],
        'population' => [
            'amount' => (int)$currentRes['population'],
            'capacity' => (int)$currentRes['farm_capacity'],
        ],
        'current_server_time' => date('Y-m-d H:i:s'),
    ];
    echo json_encode(['status' => 'success', 'data' => $response_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nie udało się pobrać zasobów wioski.']);
}

$conn->close();
?>
