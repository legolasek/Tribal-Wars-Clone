<?php

class UserManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Zmienia adres e-mail użytkownika.
     *
     * @param int $user_id ID użytkownika.
     * @param string $new_email Nowy adres e-mail.
     * @return array Wynik operacji (success: bool, message: string).
     */
    public function changeEmail(int $user_id, string $new_email): array
    {
        $new_email = trim($new_email);

        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Nieprawidłowy adres e-mail.'];
        }

        // Check if email is already taken by another user
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if ($stmt === false) {
             error_log("UserManager::changeEmail prepare select failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'Wystąpił błąd systemu (select).'];
        }
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Podany e-mail jest już zajęty.'];
        }
        $stmt->close();

        // Update the email
        $stmt_update = $this->conn->prepare("UPDATE users SET email = ? WHERE id = ?");
         if ($stmt_update === false) {
             error_log("UserManager::changeEmail prepare update failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'Wystąpił błąd systemu (update).'];
        }
        $stmt_update->bind_param("si", $new_email, $user_id);

        if ($stmt_update->execute()) {
            $stmt_update->close();
            return ['success' => true, 'message' => 'Adres e-mail został zaktualizowany.'];
        } else {
            error_log("UserManager::changeEmail execute update failed: " . $this->conn->error);
            $stmt_update->close();
            return ['success' => false, 'message' => 'Wystąpił błąd podczas aktualizacji e-maila.'];
        }
    }

    /**
     * Zmienia hasło użytkownika.
     *
     * @param int $user_id ID użytkownika.
     * @param string $current_password Obecne hasło (niehaszowane).
     * @param string $new_password Nowe hasło (niehaszowane).
     * @param string $confirm_password Potwierdzenie nowego hasła (niehaszowane).
     * @return array Wynik operacji (success: bool, message: string).
     */
    public function changePassword(int $user_id, string $current_password, string $new_password, string $confirm_password): array
    {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'Wszystkie pola zmiany hasła są wymagane.'];
        }

        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'Nowe hasło i potwierdzenie nie są identyczne.'];
        }

        // Verify current password
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = ?");
         if ($stmt === false) {
             error_log("UserManager::changePassword prepare select failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'Wystąpił błąd systemu (select).'];
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);

        if (!$stmt->fetch()) {
            $stmt->close();
            // User not found, although protected by session check, good practice to handle
             return ['success' => false, 'message' => 'Nie znaleziono użytkownika.'];
        }
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            return ['success' => false, 'message' => 'Obecne hasło jest nieprawidłowe.'];
        }

        // Hash the new password and update
        $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
         if ($stmt_update === false) {
             error_log("UserManager::changePassword prepare update failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'Wystąpił błąd systemu (update).'];
        }
        $stmt_update->bind_param("si", $new_hashed, $user_id);

        if ($stmt_update->execute()) {
            $stmt_update->close();
            return ['success' => true, 'message' => 'Hasło zostało zmienione pomyślnie.'];
        } else {
            error_log("UserManager::changePassword execute update failed: " . $this->conn->error);
            $stmt_update->close();
            return ['success' => false, 'message' => 'Wystąpił błąd podczas zmiany hasła.'];
        }
    }

    // Methods for user management will be added here

}

?> 