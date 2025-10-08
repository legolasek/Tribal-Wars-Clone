<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../lib/Database.php';
require_once '../../lib/managers/VillageManager.php';
require_once '../../lib/managers/BuildingManager.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['village_id']) || !isset($_GET['building_internal_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$village_id = (int)$_GET['village_id'];
$internal_name = $_GET['building_internal_name'];
$user_id = (int)$_SESSION['user_id'];

// Verify that the village belongs to the user
$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();
$villageManager = new VillageManager($conn);
$village = $villageManager->getVillageInfo($village_id);

if (!$village || $village['user_id'] != $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Route to the correct building panel
$panel_file = '';
switch ($internal_name) {
    case 'market':
        $panel_file = 'market_panel.php';
        break;
    // Add other cases for barracks, stable, etc. here in the future
    default:
        echo json_encode(['status' => 'error', 'message' => 'Action for this building is not implemented.']);
        exit;
}

// Capture the output of the panel file
ob_start();
// Pass variables to the included file
$data = ['village_id' => $village_id];
include __DIR__ . '/' . $panel_file;
$html_content = ob_get_clean();

echo json_encode([
    'status' => 'success',
    'html' => $html_content,
    'action_type' => $internal_name // Pass action type for JS to handle
]);