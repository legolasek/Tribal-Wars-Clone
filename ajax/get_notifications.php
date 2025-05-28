<?php
require_once '../init.php';
require_once '../lib/managers/NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nie jesteś zalogowany.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$unreadOnly = isset($_GET['unreadOnly']) && $_GET['unreadOnly'] === 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

$notificationManager = new NotificationManager($conn);
$notifications = $notificationManager->getNotifications($user_id, $unreadOnly, $limit);
$unread_count = count($notificationManager->getNotifications($user_id, true, 999)); // Pobierz pełną liczbę nieprzeczytanych

if ($notifications !== false) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nie udało się pobrać powiadomień.']);
}

$conn->close();
?>
