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
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null;

if (!$notification_id) {
    echo json_encode(['status' => 'error', 'message' => 'Brak ID powiadomienia.']);
    exit();
}

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->markAsRead($notification_id, $user_id);

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'Powiadomienie oznaczone jako przeczytane.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nie udało się oznaczyć powiadomienia jako przeczytane.']);
}

$conn->close();
?>
