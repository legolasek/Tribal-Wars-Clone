<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/UserManager.php'; // Zaktualizowana ścieżka

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';

$villageManager = new VillageManager($conn); // Instantiate VillageManager
$village_id = $villageManager->getFirstVillage($user_id); // Get the user's first village ID
$village = null;
if ($village_id) {
    $village = $villageManager->getVillageInfo($village_id); // Get village details if an ID exists
}

// Inicjalizacja UserManager
$userManager = new UserManager($conn); // Instantiate UserManager

// Handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = $_POST['new_email'] ?? '';
    $result = $userManager->changeEmail($user_id, $new_email);
    if ($result['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($result['message']) . '</p>';
    } else {
        $message = '<p class="error-message">' . htmlspecialchars($result['message']) . '</p>';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $result = $userManager->changePassword($user_id, $current_password, $new_password, $confirm_password);
    if ($result['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($result['message']) . '</p>';
    } else {
        $message = '<p class="error-message">' . htmlspecialchars($result['message']) . '</p>';
    }
}
?>
<?php require '../header.php'; ?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">⚙️</span>
            <span>Ustawienia</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?><br>
            <?php if (isset($village) && $village): // Check if village data is available ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>
    <div id="main-content">

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
<?php require '../footer.php'; ?> 