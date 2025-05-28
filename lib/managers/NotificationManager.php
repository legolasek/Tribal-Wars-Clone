<?php

class NotificationManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Dodaje nowe powiadomienie do bazy danych.
     * @param int $userId ID użytkownika
     * @param string $message Treść powiadomienia
     * @param string $type Typ powiadomienia (np. 'success', 'error', 'info')
     * @return bool True w przypadku sukcesu, false w przypadku błędu.
     */
    public function addNotification($userId, $message, $type = 'info', $link = '', $expiresAt = null) {
        if ($expiresAt === null) {
            $expiresAt = time() + (7 * 24 * 60 * 60); // Domyślnie 7 dni
        }
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at, expires_at) VALUES (?, ?, ?, ?, 0, NOW(), ?)");
        if ($stmt) {
            $stmt->bind_param("isssi", $userId, $message, $type, $link, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Pobiera powiadomienia dla danego użytkownika.
     * @param int $userId ID użytkownika
     * @param bool $unreadOnly Jeśli true, pobiera tylko nieprzeczytane powiadomienia.
     * @param int $limit Limit liczby powiadomień.
     * @return array Tablica powiadomień.
     */
    public function getNotifications($userId, $unreadOnly = false, $limit = 10) {
        // Usuń wygasłe powiadomienia przed pobraniem
        $this->cleanExpiredNotifications();

        $query = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        $query .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $notifications = [];
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();
        }
        return $notifications;
    }

    /**
     * Usuwa wygasłe powiadomienia z bazy danych.
     */
    private function cleanExpiredNotifications() {
        $stmt = $this->conn->prepare("DELETE FROM notifications WHERE expires_at < ?");
        if ($stmt) {
            $currentTime = time();
            $stmt->bind_param("i", $currentTime);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Oznacza powiadomienie jako przeczytane.
     * @param int $notificationId ID powiadomienia.
     * @param int $userId ID użytkownika (dla bezpieczeństwa, aby użytkownik mógł oznaczyć tylko swoje powiadomienia).
     * @return bool True w przypadku sukcesu, false w przypadku błędu.
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $notificationId, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Oznacza wszystkie powiadomienia użytkownika jako przeczytane.
     * @param int $userId ID użytkownika.
     * @return bool True w przypadku sukcesu, false w przypadku błędu.
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}
