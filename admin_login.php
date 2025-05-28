<?php
require 'init.php';
// Redirect if already admin
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin.php');
    exit();
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $message = '<p class="error-message">Wszystkie pola są wymagane!</p>';
    } else {
        $stmt = $conn->prepare('SELECT id, password, is_admin FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($id, $hash, $is_admin);
        if ($stmt->fetch() && password_verify($password, $hash) && $is_admin) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = true;
            header('Location: admin.php');
            exit();
        } else {
            $message = '<p class="error-message">Nieprawidłowe dane lub brak uprawnień.</p>';
        }
        $stmt->close();
    }
}
$pageTitle = 'Panel Administratora - Logowanie';
require 'header.php';
?>
<main>
    <div class="form-container">
        <h1>Panel Administratora - Logowanie</h1>
        <?= $message ?>
        <form method="POST" action="admin_login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <label for="username">Nazwa użytkownika</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Zaloguj" class="btn btn-primary">
        </form>
    </div>
</main>
<?php require 'footer.php'; ?> 