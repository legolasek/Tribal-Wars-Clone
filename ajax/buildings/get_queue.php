<?php
require_once '../../init.php';
require_once '../../lib/BuildingManager.php';
require_once '../../lib/managers/BuildingConfigManager.php'; // Potrzebny do BuildingManager
require_once '../../lib/VillageManager.php'; // Potrzebny do sprawdzenia uprawnień

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nie jesteś zalogowany.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

if (!$village_id) {
    echo json_encode(['status' => 'error', 'message' => 'Brak ID wioski.']);
    exit();
}

$villageManager = new VillageManager($conn);
// Sprawdź, czy wioska należy do zalogowanego użytkownika
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Brak dostępu do wioski.']);
    exit();
}

$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

$queue_item = $buildingManager->getBuildingQueueItem($village_id);

if ($queue_item) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'queue_item' => [
                'id' => $queue_item['id'],
                'building_name_pl' => $queue_item['name_pl'],
                'internal_name' => $queue_item['internal_name'],
                'level' => $queue_item['level'],
                'finish_time' => strtotime($queue_item['finish_time']) // Zwróć timestamp
            ]
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'queue_item' => null
        ]
    ]);
}

$conn->close();
?>
