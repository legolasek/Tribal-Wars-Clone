<?php
require 'init.php';

// require_once 'config/config.php'; // Remove manual DB connection
// require_once 'lib/Database.php'; // Remove manual DB connection
require_once __DIR__ . '/lib/managers/UserManager.php'; // Include UserManager
require_once __DIR__ . '/lib/managers/VillageManager.php'; // Include VillageManager
require_once __DIR__ . '/lib/managers/RankingManager.php'; // Include RankingManager
require_once __DIR__ . '/lib/functions.php'; // For formatNumber

// Sprawdź, czy podano ID użytkownika lub nazwę gracza w adresie URL
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$username_get = isset($_GET['user']) ? trim($_GET['user']) : '';

// Jeśli nie podano ani ID, ani nazwy, przekieruj na ranking graczy
if ($player_id <= 0 && empty($username_get)) {
    header("Location: ranking.php?type=players");
    exit();
}

// Use global $conn from init.php
if (!$conn) {
    // Handle database connection error (though init.php should handle this)
    // For now, a simple error message
    die('Nie udało się połączyć z bazą danych.'); // Consider better error handling
}

// Inicjalizacja menedżerów
$userManager = new UserManager($conn);
$villageManager = new VillageManager($conn);
$rankingManager = new RankingManager($conn);

$user = null;

// Pobierz dane gracza po ID lub nazwie użytkownika
if ($player_id > 0) {
    $user = $userManager->getUserById($player_id); // Assuming getUserById method exists
} elseif (!empty($username_get)) {
    $user = $userManager->getUserByUsername($username_get); // Assuming getUserByUsername method exists
}

// Sprawdź, czy znaleziono gracza
if (!$user) {
    $pageTitle = 'Gracz nie istnieje';
    require 'header.php';
    // Use standard game layout divs
    echo '<div id="game-container"><div id="main-content"><main><h2>Gracz nie istnieje</h2><p>Profil gracza nie został znaleziony.</p><a href="ranking.php?type=players" class="btn btn-secondary mt-3">← Powrót do rankingu</a></main></div></div>';
    require 'footer.php';
    exit;
}

$user_id = $user['id'];
$username = $user['username'];

// Pobierz wioski gracza przy użyciu VillageManager
$villages = $villageManager->getUserVillages($user_id);

// Pobierz ranking gracza przy użyciu RankingManager
// RankingManager::getPlayerRank and getTotalPlayersCount are assumed to exist/be implemented
$playerRank = $rankingManager->getPlayerRank($user_id); // Assuming this method returns rank number
$totalPlayers = $rankingManager->getTotalPlayersCount(); // Assuming this method returns total count

// Pobierz ogólne statystyki (jeśli potrzebne i dostępne w menedżerach)
// Można dodać metody do UserManager lub RankingManager
// $total_users = $rankingManager->getTotalUsersCount(); // Example
// $total_villages = $villageManager->getTotalVillagesCount(); // Example

// For now, keep existing queries for total counts if managers don't have them
$res = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $res ? $res->fetch_assoc()['total'] : 0;
$res = $conn->query("SELECT COUNT(*) as total FROM villages");
$total_villages = $res ? $res->fetch_assoc()['total'] : 0;

// $database->closeConnection(); // Remove manual DB close

$pageTitle = 'Profil gracza: ' . htmlspecialchars($username);
require 'header.php';
?>

<div id="game-container">
    <?php // Add the header section similar to other pages if needed, or rely on header.php ?>
    <!-- Example header structure -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">👤</span>
            <span>Profil gracza</span>
        </div>
        <?php /* User section will be included by header.php if logic is there */ ?>
        <?php // Manual user section if header.php doesn't handle it based on context ?>
        <?php if (isset($_SESSION['user_id']) && ($currentUserVillage = $villageManager->getFirstVillage($_SESSION['user_id']))): ?>
         <div class="header-user">
             Gracz: <?= htmlspecialchars($_SESSION['username']) ?><br>
             <span class="village-name-display" data-village-id="<?= $currentUserVillage['id'] ?>"><?= htmlspecialchars($currentUserVillage['name']) ?> (<?= $currentUserVillage['x_coord'] ?>|<?= $currentUserVillage['y_coord'] ?>)</span>
         </div>
        <?php endif; ?>
    </header>

    <div id="main-content">
        <main>
            <h2>Profil gracza: <?php echo htmlspecialchars($username); ?></h2>
            <div class="summary-box">
                <b>Data rejestracji gracza:</b> <?php echo isset($user['registration_date']) ? htmlspecialchars($user['registration_date']) : 'brak danych'; ?><br>
                <b>Liczba graczy:</b> <?php echo $total_users; ?><br>
                <b>Liczba wiosek w grze:</b> <?php echo $total_villages; ?>
            </div>
            <p><b>Liczba wiosek:</b> <?php echo count($villages); ?></p>
            <p><b>Miejsce w rankingu:</b> <?php echo $playerRank; ?> / <?php echo $totalPlayers; ?></p>
            <div class="villages-list">
                <h3>Lista wiosek</h3>
                <?php if (count($villages) === 0): ?>
                    <p class="no-villages">Gracz nie posiada żadnej wioski.</p>
                <?php else: ?>
                <table>
                    <tr><th>Nazwa wioski</th><th>Koordynaty</th><th></th></tr>
                    <?php foreach ($villages as $v): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($v['name']); ?></td>
                            <td>(<?php echo $v['x_coord']; ?>|<?php echo $v['y_coord']; ?>)</td>
                            <td><a href="map.php?center_x=<?php echo $v['x_coord']; ?>&center_y=<?php echo $v['y_coord']; ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:0.95em; margin:0;">Pokaż na mapie</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
            <a href="ranking.php?type=players" class="btn btn-secondary mt-3">← Powrót do rankingu</a>
        </main>
    </div>
</div>

<?php require 'footer.php'; ?> 