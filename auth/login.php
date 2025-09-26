<?php
require '../init.php';
// Walidacja CSRF dla żądań POST jest wykonywana automatycznie w validateCSRF() z functions.php

$message = '';

// --- PRZETWARZANIE DANYCH (LOGOWANIE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validateCSRF(); // Usunięto stąd, bo jest w validateCSRF() wywoływanym globalnie dla POST w init.php (jeśli logika init.php została odpowiednio zmieniona)

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Walidacja danych wejściowych (podstawowa)
    if (empty($username) || empty($password)) {
        $message = '<p class="error-message">Wszystkie pola są wymagane!</p>';
    } else {
        // Użycie Prepared Statement do pobrania danych użytkownika
        $stmt = $conn->prepare("SELECT id, username, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $db_username, $hashed_password, $is_banned);
        $stmt->fetch();

        if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
            $stmt->close(); // Zamknij pierwsze zapytanie

            if ($is_banned) {
                 $message = '<p class="error-message">Twoje konto zostało zbanowane.</p>';
            } else {
                // Ustawienie sesji
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;

                // Sprawdzenie, czy użytkownik ma już wioskę przy użyciu Prepared Statement
                $stmt_check_village = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
                $stmt_check_village->bind_param("i", $id);
                $stmt_check_village->execute();
                $stmt_check_village->store_result();

                if ($stmt_check_village->num_rows > 0) {
                    $stmt_check_village->close();
                    // Przekierowanie do wyboru świata lub gry
                    header("Location: ../game/world_select.php?redirect=game/game.php");
                } else {
                     $stmt_check_village->close();
                    // Przekierowanie do tworzenia wioski
                    header("Location: ../game/world_select.php?redirect=player/create_village.php");
                }
                exit();
            }
        } else {
             if ($stmt) $stmt->close(); // Zamknij zapytanie nawet jeśli nie znaleziono użytkownika
            $message = '<p class="error-message">Nieprawidłowa nazwa użytkownika lub hasło.</p>';
        }
    }
}

// --- PREZENTACJA (HTML) ---
$pageTitle = 'Logowanie';
require '../header.php';
?>
<main>
    <div class="form-container">
        <h1>Logowanie</h1>
        <?= $message ?>
        <form action="login.php" method="POST">
            <?php if (isset($_SESSION['csrf_token'])): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php endif; ?>
            
            <label for="username">Nazwa użytkownika</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Zaloguj" class="btn btn-primary">
        </form>
        <p class="mt-2">Nie masz konta? <a href="register.php">Zarejestruj się</a>.</p>
        <p><a href="../index.php">Wróć do strony głównej</a>.</p>
    </div>
</main>
<?php
require '../footer.php';
// Zamknij połączenie z bazą po renderowaniu strony
if (isset($database)) {
    $database->closeConnection();
}
?>
