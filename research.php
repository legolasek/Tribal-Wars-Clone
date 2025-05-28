<?php
session_start();
require_once 'config/config.php';
require_once 'lib/Database.php';
require_once 'lib/ResearchManager.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Connect
$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();
$rm = new ResearchManager($conn);

// Determine default village
$stmt = $conn->prepare("SELECT id, name FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$villageData = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$villageData) {
    header("Location: create_village.php");
    exit();
}
$village_id = $villageData['id'];
$village_name = $villageData['name'];

// Fetch research data
$levels = $rm->getVillageResearchLevels($village_id);
$queue = $rm->getResearchQueue($village_id);
$available = $rm->getResearchTypesForBuilding('academy');

$db->closeConnection();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Badania — Tribal Wars Nowa Edycja</title>
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
    <script src="js/main.js?v=<?php echo time(); ?>"></script>
</head>
<body>
<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">🔬</span>
            <span class="game-name">Badania</span>
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
                <li><a href="settings.php">⚙️ Ustawienia</a></li>
                <li><a href="logout.php">🚪 Wyloguj</a></li>
            </ul>
        </nav>
        <main>
            <h2>Badania w wiosce <?php echo htmlspecialchars($village_name); ?></h2>
            <!-- Kolejka badań -->
            <section class="form-container">
                <h3>Kolejka badań</h3>
                <?php if (!empty($queue)): ?>
                    <table class="upgrade-buildings-table">
                        <thead>
                            <tr><th>Badanie</th><th>Poziom docelowy</th><th>Pozostały czas</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($queue as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['research_name']); ?></td>
                                <td><?php echo $item['level_after']; ?></td>
                                <td><span class="build-timer" data-ends-at="<?php echo strtotime($item['ends_at']); ?>"></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Brak badań w kolejce.</p>
                <?php endif; ?>
            </section>
            <!-- Dostępne badania -->
            <section class="form-container mt-3">
                <h3>Dostępne badania</h3>
                <form id="research-form">
                    <table class="upgrade-buildings-table">
                        <thead>
                            <tr><th>Badanie</th><th>Aktualny poziom</th><th>Koszt</th><th>Czas</th><th>Akcja</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($available as $internal => $r): 
                            $current = $levels[$internal] ?? 0;
                            $next = $current + 1;
                            $cost = $rm->getResearchCost($r['id'], $next);
                            $time = $rm->calculateResearchTime($r['id'], $next, $levels['academy'] ?? 0);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['name_pl']); ?></td>
                                <td><?php echo $current; ?></td>
                                <td><?php echo $cost['wood']; ?>D <?php echo $cost['clay']; ?>G <?php echo $cost['iron']; ?>Ż</td>
                                <td><?php echo gmdate('H:i:s', $time); ?></td>
                                <td><button type="button" class="btn btn-primary start-research" data-research-id="<?php echo $r['id']; ?>" data-next-level="<?php echo $next; ?>">Rozpocznij</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </section>
        </main>
    </div>
</div>
<script>
// Inicjalizacja timerów badań
initializeBuildTimers();
// Obsługa kliknięcia rozpoczęcia badania
document.querySelectorAll('.start-research').forEach(btn => {
    btn.addEventListener('click', function() {
        const researchId = this.dataset.researchId;
        const level = this.dataset.nextLevel;
        fetch('start_research.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `village_id=<?php echo $village_id; ?>&research_type_id=${researchId}`
        })
        .then(r=>r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert(data.error);
        });
    });
});
</script>
</body>
</html> 