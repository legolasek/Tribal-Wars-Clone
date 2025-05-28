<?php
require 'init.php';
require_once __DIR__ . '/lib/managers/RankingManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/lib/managers/VillageManager.php'; // Zaktualizowana ścieżka

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Inicjalizacja menedżera wiosek (potrzebne dla header.php)
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

// Typ rankingu (gracze lub plemiona)
$ranking_type = isset($_GET['type']) ? $_GET['type'] : 'players';
$valid_ranking_types = ['players', 'tribes']; // Add 'tribes'
if (!in_array($ranking_type, $valid_ranking_types)) {
    $ranking_type = 'players'; // Default to players if invalid type
}

// Aktualna strona
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Inicjalizacja RankingManager
$rankingManager = new RankingManager($conn);

$total_records = 0;
$ranking_data = [];
$totalPages = 1;

// Pobierz dane rankingu i całkowitą liczbę rekordów w zależności od typu
if ($ranking_type === 'players') {
    $total_records = $rankingManager->getTotalPlayersCount();
    if ($total_records > 0) {
        $ranking_data = $rankingManager->getPlayersRanking($per_page, $offset);
    }
    $pageTitle = 'Ranking Graczy';
} elseif ($ranking_type === 'tribes') {
    $total_records = $rankingManager->getTotalTribesCount(); // This will currently return 0
    if ($total_records > 0) {
         $ranking_data = $rankingManager->getTribesRanking($per_page, $offset); // This will currently return []
    }
    $pageTitle = 'Ranking Plemion';
}

// Oblicz całkowitą liczbę stron po pobraniu total_records
if ($total_records > 0) {
     $totalPages = ceil($total_records / $per_page);
     // Adjust current page and offset if it exceeds total pages (can happen after data changes)
     if ($page > $totalPages) {
          $page = $totalPages;
          $offset = ($page - 1) * $per_page;
          // Re-fetch data for the corrected page if needed (RankingManager handles offset)
           if ($ranking_type === 'players') {
                $ranking_data = $rankingManager->getPlayersRanking($per_page, $offset);
           } elseif ($ranking_type === 'tribes') {
                $ranking_data = $rankingManager->getTribesRanking($per_page, $offset);
           }
     }
} else {
     // If no records, ensure ranking_data is empty and totalPages is 1
     $ranking_data = [];
     $totalPages = 1;
     $page = 1;
     $offset = 0;
}

// Oblicz początkowy numer rangi dla bieżącej strony
$start_rank = $offset + 1;

// Dodaj numer rangi do każdego wiersza danych
if ($ranking_type === 'players') {
    $current_rank = $start_rank;
    foreach ($ranking_data as &$player) {
        $player['rank'] = $current_rank++;
    }
    unset($player); // Unset the reference
}
// Note: For tribes, rank would be handled similarly if/when implemented


require 'header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">🏆</span>
            <span>Ranking</span>
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
            <h2>Ranking</h2>

            <div class="ranking-tabs">
                <a href="?type=players" class="ranking-tab <?= $ranking_type == 'players' ? 'active' : '' ?>">Gracze</a>
                <a href="?type=tribes" class="ranking-tab <?= $ranking_type == 'tribes' ? 'active' : '' ?>">Plemiona</a>
            </div>

            <div class="ranking-container">
                <?php if ($ranking_type === 'players'): ?>
                    <h3>Ranking Graczy</h3>

                    <?php if (count($ranking_data) > 0): ?>
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th class="rank-column">Miejsce</th>
                                    <th>Gracz</th>
                                    <th>Liczba wiosek</th>
                                    <th>Populacja</th>
                                    <th>Punkty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranking_data as $player): ?>
                                    <tr class="<?= $player['id'] == $user_id ? 'current-user' : '' ?>">
                                        <td class="rank-column"><?= $player['rank'] ?></td>
                                        <td><?= htmlspecialchars($player['username']) ?></td>
                                        <td><?= $player['village_count'] ?></td>
                                        <td><?= formatNumber($player['total_population']) ?></td>
                                        <td><?= formatNumber($player['points']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=1" class="page-link">«</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page - 1 ?>" class="page-link">‹</a>
                                <?php endif; ?>

                                <?php
                                // Rangi do wyświetlenia
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $totalPages);

                                // Adjust range if near the start or end
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page + 1 ?>" class="page-link">›</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $totalPages ?>" class="page-link">»</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="no-data">Brak danych graczy do wyświetlenia</div>
                    <?php endif; ?>

                <?php elseif ($ranking_type === 'tribes'): ?>
                    <h3>Ranking Plemion</h3>
                    <?php if (count($ranking_data) > 0): ?>
                         <!-- TODO: Add tribes ranking table structure here -->
                         <table class="ranking-table">
                             <thead>
                                 <tr>
                                     <th class="rank-column">Miejsce</th>
                                     <th>Nazwa Plemiona</th>
                                     <th>Liczba członków</th>
                                     <th>Liczba wiosek</th>
                                     <th>Punkty</th>
                                 </tr>
                             </thead>
                             <tbody>
                                  <?php // Example loop for tribes (currently $ranking_data is empty) ?>
                                  <?php foreach ($ranking_data as $tribe): ?>
                                      <tr>
                                           <td class="rank-column"><?= $tribe['rank'] ?? '-' ?></td>
                                           <td><?= htmlspecialchars($tribe['name']) ?></td>
                                           <td><?= $tribe['member_count'] ?? 0 ?></td>
                                           <td><?= $tribe['village_count'] ?? 0 ?></td>
                                           <td><?= formatNumber($tribe['points'] ?? 0) ?></td>
                                      </tr>
                                  <?php endforeach; ?>
                             </tbody>
                         </table>
                          <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=1" class="page-link">«</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page - 1 ?>" class="page-link">‹</a>
                                <?php endif; ?>

                                <?php
                                // Rangi do wyświetlenia
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $totalPages);

                                // Adjust range if near the start or end
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page + 1 ?>" class="page-link">›</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $totalPages ?>" class="page-link">»</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                         <div class="no-data">
                             System plemion jest w trakcie implementacji lub brak danych.
                         </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require 'footer.php'; ?> 