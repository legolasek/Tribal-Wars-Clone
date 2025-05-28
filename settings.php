<?php
require 'init.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';

// Handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = trim($_POST['new_email'] ?? '');
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p class="error-message">Nieprawidłowy adres e-mail.</p>';
    } else {
        // Check if email is taken
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = '<p class="error-message">Podany e-mail jest już zajęty.</p>';
        } else {
            $stmt_update = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_email, $user_id);
            if ($stmt_update->execute()) {
                $message = '<p class="success-message">Adres e-mail został zaktualizowany.</p>';
            } else {
                $message = '<p class="error-message">Wystąpił błąd podczas aktualizacji e-maila.</p>';
            }
            $stmt_update->close();
        }
        $stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = '<p class="error-message">Wszystkie pola zmiany hasła są wymagane.</p>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<p class="error-message">Nowe hasło i potwierdzenie nie są identyczne.</p>';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $message = '<p class="error-message">Obecne hasło jest nieprawidłowe.</p>';
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_hashed, $user_id);
            if ($stmt_update->execute()) {
                $message = '<p class="success-message">Hasło zostało zmienione pomyślnie.</p>';
            } else {
                $message = '<p class="error-message">Wystąpił błąd podczas zmiany hasła.</p>';
            }
            $stmt_update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia — Tribal Wars Nowa Edycja</title>
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
    <script src="js/main.js?v=<?php echo time(); ?>"></script>
</head>
<body>
<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">⚙️</span>
            <span class="game-name">Ustawienia</span>
        </div>
        <div class="header-user">Witaj, <b><?php echo htmlspecialchars($username); ?></b></div>
    </header>
    <div id="main-content">
        <nav id="sidebar">
            <ul>
                <li><a href="game.php">🏠 Wioska</a></li>
                <li><a href="map.php">🗺️ Mapa</a></li>
                <li><a href="attack.php">⚔️ Atak</a></li>
                <li><a href="reports.php">📜 Raporty</a></li>
                <li><a href="messages.php">✉️ Wiadomości</a></li>
                <li><a href="ranking.php">🏆 Ranking</a></li>
                <li><a href="settings.php" class="active">⚙️ Ustawienia</a></li>
                <li><a href="logout.php">🚪 Wyloguj</a></li>
            </ul>
        </nav>
        <main>
            <h2>Ustawienia konta</h2>
            <?php echo $message; ?>
            <section class="form-container">
                <h3>Zmiana adresu e-mail</h3>
                <form action="settings.php" method="POST">
                    <label for="new_email">Nowy e-mail</label>
                    <input type="email" id="new_email" name="new_email" required>
                    <input type="submit" name="change_email" value="Zmień e-mail" class="btn btn-primary mt-2">
                </form>
            </section>
            <section class="form-container mt-3">
                <h3>Zmiana hasła</h3>
                <form action="settings.php" method="POST">
                    <label for="current_password">Obecne hasło</label>
                    <input type="password" id="current_password" name="current_password" required>

                    <label for="new_password">Nowe hasło</label>
                    <input type="password" id="new_password" name="new_password" required>

                    <label for="confirm_password">Potwierdź hasło</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>

                    <input type="submit" name="change_password" value="Zmień hasło" class="btn btn-primary mt-2">
                </form>
            </section>
        </main>
    </div>
</div>
</body>
</html> 