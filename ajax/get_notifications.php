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

// Use the new method to get unread count
$unread_count = $notificationManager->getUnreadNotificationCount($user_id);

// Get notifications list
$notifications = $notificationManager->getNotifications($user_id, $unreadOnly, $limit);

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
