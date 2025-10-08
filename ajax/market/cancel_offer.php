<?php
session_start();
header('Content-Type: application/json');

// Include necessary files
require_once '../../config/config.php';
require_once '../../lib/Database.php';
require_once '../../lib/managers/MarketManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to cancel an offer.']);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$villageId = $_SESSION['current_village_id'] ?? null;
$offerId = $_POST['offer_id'] ?? null;


if (!$villageId || !$offerId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $db->getConnection();
    $marketManager = new MarketManager($conn);

    $result = $marketManager->cancelOffer((int)$offerId, (int)$villageId);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}