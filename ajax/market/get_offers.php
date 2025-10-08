<?php
session_start();
header('Content-Type: application/json');

// Include necessary files
require_once '../../config/config.php';
require_once '../../lib/Database.php';
require_once '../../lib/managers/MarketManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view offers.']);
    exit;
}

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $db->getConnection();
    $marketManager = new MarketManager($conn);

    $offers = $marketManager->getOffers();

    echo json_encode(['success' => true, 'offers' => $offers]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}