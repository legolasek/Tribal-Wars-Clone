<?php
require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/UnitManager.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';

// Initialize managers
$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = $_GET['village_id'] ?? null;
$building_internal_name = $_GET['building'] ?? null;

if (!$village_id || !$building_internal_name) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters.']);
    exit();
}

// Verify that the user owns the village
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this village.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request: Display the recruitment panel
    try {
        // Get building details
        $building_level = $buildingManager->getBuildingLevel($village_id, $building_internal_name);
        if ($building_level === 0) {
            echo "<p>You must build the {$building_internal_name} first.</p>";
            exit();
        }

        // Get available units for this building
        $available_units = $unitManager->getAvailableUnitsByBuilding($building_internal_name, $building_level);

        // Get current units in the village
        $village_units = $unitManager->getVillageUnits($village_id);

        // Get recruitment queue for this building
        $recruitment_queue = $unitManager->getRecruitmentQueues($village_id, $building_internal_name);

        // Render the recruitment panel view
        include '../../buildings/recruit_panel.php';

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST request: Process recruitment
    $data = json_decode(file_get_contents('php://input'), true);
    $unit_id = $data['unit_id'] ?? null;
    $count = $data['count'] ?? null;

    if (!$unit_id || !$count || !is_numeric($count) || $count <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input.']);
        exit();
    }

    $count = intval($count);
    $building_level = $buildingManager->getBuildingLevel($village_id, $building_internal_name);

    // Check requirements
    $requirements = $unitManager->checkRecruitRequirements($unit_id, $village_id);
    if (!$requirements['can_recruit']) {
        http_response_code(400);
        echo json_encode(['error' => "Cannot recruit unit: " . $requirements['reason']]);
        exit();
    }

    // Check resources
    $resource_check = $unitManager->checkResourcesForRecruitment($unit_id, $count, $village);
    if (!$resource_check['can_afford']) {
        http_response_code(400);
        echo json_encode(['error' => 'Not enough resources.']);
        exit();
    }

    // Deduct resources
    $costs = $resource_check['total_costs'];
    $villageManager->updateVillageResources($village_id, -$costs['wood'], -$costs['clay'], -$costs['iron']);

    // Add to recruitment queue
    $result = $unitManager->recruitUnits($village_id, $unit_id, $count, $building_level);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        // Rollback resources if recruitment failed
        $villageManager->updateVillageResources($village_id, $costs['wood'], $costs['clay'], $costs['iron']);
        http_response_code(500);
        echo json_encode(['error' => $result['error']]);
    }
}
?>