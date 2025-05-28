<?php
require_once '../init.php';
require_once '../lib/managers/NotificationManager.php';
require_once '../lib/functions.php'; // Dla validateCSRF

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nie jesteś zalogowany.']);
    exit();
}

validateCSRF(); // Walidacja tokena CSRF

$user_id = $_SESSION['user_id'];

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->markAllAsRead($user_id);

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'Wszystkie powiadomienia oznaczone jako przeczytane.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nie udało się oznaczyć wszystkich powiadomień jako przeczytane.']);
}

$conn->close();
?>
