<?php

class MessageManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get a single message by ID for a specific user and mark it as read if the user is the receiver.
     *
     * @param int $messageId The ID of the message.
     * @param int $userId The ID of the user requesting the message.
     * @return array|null The message data or null if not found or no access.
     */
    public function getMessageByIdForUser(int $messageId, int $userId): ?array
    {
        // Pobierz wiadomość wraz z nadawcą i odbiorcą
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, m.sender_id, m.receiver_id,
                    u_sender.username AS sender_username,
                    u_receiver.username AS receiver_username
             FROM messages m
             JOIN users u_sender ON m.sender_id = u_sender.id
             JOIN users u_receiver ON m.receiver_id = u_receiver.id
             WHERE m.id = ? AND (m.receiver_id = ? OR m.sender_id = ?) LIMIT 1"
        );
        $stmt->bind_param("iii", $messageId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null; // Wiadomość nie znaleziona lub brak dostępu
        }

        $msg = $result->fetch_assoc();
        $stmt->close();

        // Oznacz jako przeczytane, jeśli to odbiorca i wiadomość jest nieprzeczytana
        if ($msg['receiver_id'] === $userId && !$msg['is_read']) {
            $stmt2 = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $stmt2->bind_param("i", $messageId);
            if ($stmt2->execute()) {
                // Jeśli pomyślnie oznaczono jako przeczytane, zaktualizuj zmienną w zwróconych danych
                $msg['is_read'] = 1;
            }
            $stmt2->close();
        }

        return $msg;
    }

    /**
     * Get a list of messages for a user based on tab and pagination.
     *
     * @param int $userId The ID of the user.
     * @param string $tab The message tab ('inbox', 'sent', 'archive').
     * @param int $offset The pagination offset.
     * @param int $limit The maximum number of messages to return.
     * @return array An array containing 'messages' (list of messages) and 'total' (total count for pagination).
     */
    public function getUserMessages(int $userId, string $tab, int $offset, int $limit): array
    {
        $messages = [];
        $totalMessages = 0;
        $query = "";
        $countQuery = "";

        // Determine query based on tab
        switch ($tab) {
            case 'inbox':
                $countQuery = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_archived = 0";
                $query = "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, u.username AS sender_username, u.id AS sender_id
                          FROM messages m
                          JOIN users u ON m.sender_id = u.id
                          WHERE m.receiver_id = ? AND m.is_archived = 0
                          ORDER BY m.sent_at DESC LIMIT ?, ?";
                $stmt_count = $this->conn->prepare($countQuery);
                $stmt_count->bind_param("i", $userId);
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iii", $userId, $limit, $offset); // Bind limit then offset
                break;

            case 'sent':
                $countQuery = "SELECT COUNT(*) as total FROM messages WHERE sender_id = ? AND is_sender_deleted = 0";
                $query = "SELECT m.id, m.subject, m.body, m.sent_at, 1 AS is_read, u.username AS receiver_username, u.id AS receiver_id
                          FROM messages m
                          JOIN users u ON m.receiver_id = u.id
                          WHERE m.sender_id = ? AND m.is_sender_deleted = 0
                          ORDER BY m.sent_at DESC LIMIT ?, ?";
                 $stmt_count = $this->conn->prepare($countQuery);
                 $stmt_count->bind_param("i", $userId);
                 $stmt = $this->conn->prepare($query);
                 $stmt->bind_param("iii", $userId, $limit, $offset); // Bind limit then offset
                break;

            case 'archive':
                $countQuery = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_archived = 1";
                $query = "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, u.username AS sender_username, u.id AS sender_id
                          FROM messages m
                          JOIN users u ON m.sender_id = u.id
                          WHERE m.receiver_id = ? AND m.is_archived = 1
                          ORDER BY m.sent_at DESC LIMIT ?, ?";
                 $stmt_count = $this->conn->prepare($countQuery);
                 $stmt_count->bind_param("i", $userId);
                 $stmt = $this->conn->prepare($query);
                 $stmt->bind_param("iii", $userId, $limit, $offset); // Bind limit then offset
                break;

            default:
                // Return empty for invalid tab
                return ['messages' => [], 'total' => 0];
        }

        // Get total count
        if (isset($stmt_count)) {
             $stmt_count->execute();
             $countResult = $stmt_count->get_result()->fetch_assoc();
             $totalMessages = $countResult['total'] ?? 0;
             $stmt_count->close();
        }

        // Get messages
        if (isset($stmt)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
        }

        return ['messages' => $messages, 'total' => $totalMessages];
    }

     /**
      * Get unread, archived, and sent message counts for a user.
      *
      * @param int $userId The ID of the user.
      * @return array An associative array with counts ('unread', 'archive', 'sent').
      */
     public function getMessageCounts(int $userId): array
     {
         $counts = ['unread' => 0, 'archive' => 0, 'sent' => 0];

         // Unread count
         $stmt = $this->conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_archived = 0");
         $stmt->bind_param("i", $userId);
         $stmt->execute();
         $unread_result = $stmt->get_result()->fetch_assoc();
         $counts['unread'] = $unread_result['unread_count'] ?? 0;
         $stmt->close();

         // Archive count
         $stmt = $this->conn->prepare("SELECT COUNT(*) as archive_count FROM messages WHERE receiver_id = ? AND is_archived = 1");
         $stmt->bind_param("i", $userId);
         $stmt->execute();
         $archive_result = $stmt->get_result()->fetch_assoc();
         $counts['archive'] = $archive_result['archive_count'] ?? 0;
         $stmt->close();

         // Sent count
         $stmt = $this->conn->prepare("SELECT COUNT(*) as sent_count FROM messages WHERE sender_id = ? AND is_sender_deleted = 0");
         $stmt->bind_param("i", $userId);
         $stmt->execute();
         $sent_result = $stmt->get_result()->fetch_assoc();
         $counts['sent'] = $sent_result['sent_count'] ?? 0;
         $stmt->close();

         return $counts;
     }

    /**
     * Perform a bulk action on messages for a user.
     *
     * @param int $userId The ID of the user performing the action.
     * @param string $action The action to perform ('mark_read', 'mark_unread', 'archive', 'unarchive', 'delete').
     * @param array $messageIds An array of message IDs to perform the action on.
     * @return bool True on success, false on failure.
     */
    public function performBulkAction(int $userId, string $action, array $messageIds): bool
    {
        if (empty($messageIds)) {
            return false;
        }

        $ids_str = implode(',', array_map('intval', $messageIds));

        $success = false;
        switch ($action) {
            case 'mark_read':
                // Only receiver can mark as read
                $stmt = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $userId);
                $success = $stmt->execute();
                $stmt->close();
                break;

            case 'mark_unread':
                // Only receiver can mark as unread
                $stmt = $this->conn->prepare("UPDATE messages SET is_read = 0 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $userId);
                $success = $stmt->execute();
                $stmt->close();
                break;

            case 'archive':
                // Only receiver can archive
                $stmt = $this->conn->prepare("UPDATE messages SET is_archived = 1 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $userId);
                $success = $stmt->execute();
                $stmt->close();
                break;

            case 'unarchive':
                // Only receiver can unarchive
                $stmt = $this->conn->prepare("UPDATE messages SET is_archived = 0 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $userId);
                $success = $stmt->execute();
                $stmt->close();
                break;

            case 'delete':
                // Implement deletion logic: set sender_deleted or receiver_deleted flags
                // For simplicity now, we'll use the old logic of deleting if receiver or sender+not_sender_deleted
                 // A more robust solution would use flags and a cleanup process.
                 // For now, mirror the old logic for deletion via receiver_id or sender_id + is_sender_deleted = 0
                $stmt = $this->conn->prepare("DELETE FROM messages WHERE id IN ($ids_str) AND (receiver_id = ? OR (sender_id = ? AND is_sender_deleted = 0))");
                $stmt->bind_param("ii", $userId, $userId);
                $success = $stmt->execute();
                $stmt->close();
                 // --- TODO: Implement proper flag-based deletion ---
                break;

            default:
                // Invalid action
                return false;
        }

        return $success;
    }

    /**
     * Perform a single message action (delete, archive, unarchive).
     *
     * @param int $userId The ID of the user performing the action.
     * @param int $messageId The ID of the message.
     * @param string $action The action to perform ('delete', 'archive', 'unarchive').
     * @return bool True on success, false on failure.
     */
    public function performSingleAction(int $userId, int $messageId, string $action): bool
    {
        // First, verify the message belongs to the user
        $stmt_check = $this->conn->prepare("SELECT id, sender_id, receiver_id FROM messages WHERE id = ? AND (receiver_id = ? OR sender_id = ?) LIMIT 1");
        $stmt_check->bind_param("iii", $messageId, $userId, $userId);
        $stmt_check->execute();
        $message = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if (!$message) {
            return false; // Message not found or user has no access
        }

        $is_sender = ($message['sender_id'] == $userId);
        $is_receiver = ($message['receiver_id'] == $userId);

        $success = false;
        switch ($action) {
            case 'delete':
                // Implement deletion logic (same as bulk for now, but on a single ID)
                 // TODO: Implement proper flag-based deletion
                $stmt = $this->conn->prepare("DELETE FROM messages WHERE id = ? AND (receiver_id = ? OR (sender_id = ? AND is_sender_deleted = 0))");
                $stmt->bind_param("iii", $messageId, $userId, $userId);
                $success = $stmt->execute();
                $stmt->close();
                break;

            case 'archive':
                // Only receiver can archive
                if ($is_receiver) {
                    $stmt = $this->conn->prepare("UPDATE messages SET is_archived = 1 WHERE id = ? AND receiver_id = ?");
                    $stmt->bind_param("ii", $messageId, $userId);
                    $success = $stmt->execute();
                    $stmt->close();
                }
                break;

            case 'unarchive':
                // Only receiver can unarchive
                if ($is_receiver) {
                    $stmt = $this->conn->prepare("UPDATE messages SET is_archived = 0 WHERE id = ? AND receiver_id = ?");
                    $stmt->bind_param("ii", $messageId, $userId);
                    $success = $stmt->execute();
                    $stmt->close();
                }
                break;

            default:
                // Invalid action
                return false;
        }

        return $success;
    }

    // Methods for actions (delete, archive, unarchive) will be added here later

}

?> 