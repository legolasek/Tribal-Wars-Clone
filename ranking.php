<?php
require 'init.php';

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Typ rankingu (gracze lub plemiona)
$ranking_type = isset($_GET['type']) ? $_GET['type'] : 'players';

// Aktualna strona
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Całkowita liczba graczy do paginacji
$count_query = "SELECT COUNT(*) as total FROM users";
$stmt_count = $conn->prepare($count_query);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Pobierz ranking graczy
$query = "
    SELECT 
        u.id, 
        u.username, 
        COUNT(v.id) as village_count, 
        SUM(v.population) as total_population,
        SUM(
            (SELECT COUNT(*) FROM village_units vu WHERE vu.village_id = v.id)
        ) as total_units
    FROM 
        users u
    LEFT JOIN 
        villages v ON u.id = v.user_id
    GROUP BY 
        u.id
    ORDER BY 
        total_population DESC, village_count DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
$rank = $offset + 1;

while ($row = $result->fetch_assoc()) {
    $row['rank'] = $rank++;
    $players[] = $row;
}

$pageTitle = 'Ranking Graczy';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="css/main.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <style>
        .ranking-container {
            background: #f0e6c8;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .ranking-table th,
        .ranking-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #d2b48c;
        }
        .ranking-table th {
            background-color: #d2b48c;
            color: #5a4a3b;
            font-weight: bold;
        }
        .ranking-table tr:hover {
            background-color: #e4d5b7;
        }
        .ranking-table .current-user {
            background-color: #c8e6c9;
        }
        .ranking-table .rank-column {
            text-align: center;
            width: 70px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .pagination a, 
        .pagination span {
            display: inline-block;
            padding: 5px 10px;
            background: #d2b48c;
            color: #5a4a3b;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a:hover {
            background: #c8bca8;
        }
        .pagination .current {
            background: #8b4513;
            color: white;
        }
        .ranking-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #d2b48c;
        }
        .ranking-tab {
            padding: 10px 20px;
            background: #e4d5b7;
            color: #5a4a3b;
            text-decoration: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .ranking-tab.active {
            background: #d2b48c;
            color: #5a4a3b;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
<div id="game-container">
    <!-- Game header with resources -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">🏆</span>
            <span>Ranking</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?>
            <div class="header-links">
                <a href="game.php">Przegląd</a> | 
                <a href="logout.php">Wyloguj</a>
            </div>
        </div>
    </header>

    <div id="main-content">
        <!-- Sidebar navigation -->
        <nav id="sidebar">
            <ul>
                <li><a href="game.php">Przegląd</a></li>
                <li><a href="map.php">Mapa</a></li>
                <li><a href="reports.php">Raporty</a></li>
                <li><a href="messages.php">Wiadomości</a></li>
                <li><a href="ranking.php" class="active">Ranking</a></li>
                <li><a href="settings.php">Ustawienia</a></li>
                <li><a href="logout.php">Wyloguj</a></li>
            </ul>
        </nav>
        <main>
            <h2>Ranking</h2>
            
            <div class="ranking-tabs">
                <a href="?type=players" class="ranking-tab <?= $ranking_type == 'players' ? 'active' : '' ?>">Gracze</a>
                <a href="?type=tribes" class="ranking-tab <?= $ranking_type == 'tribes' ? 'active' : '' ?>">Plemiona</a>
            </div>
            
            <div class="ranking-container">
                <?php if ($ranking_type == 'players'): ?>
                    <h3>Ranking Graczy</h3>
                    
                    <?php if (count($players) > 0): ?>
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
                                <?php foreach ($players as $player): ?>
                                    <tr class="<?= $player['id'] == $user_id ? 'current-user' : '' ?>">
                                        <td class="rank-column"><?= $player['rank'] ?></td>
                                        <td><?= htmlspecialchars($player['username']) ?></td>
                                        <td><?= $player['village_count'] ?></td>
                                        <td><?= formatNumber($player['total_population']) ?></td>
                                        <td><?= formatNumber($player['total_population'] * 10) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=1">«</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page - 1 ?>">‹</a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $total_pages);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?type=<?= $ranking_type ?>&page=<?= $i ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page + 1 ?>">›</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $total_pages ?>">»</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data">Brak danych do wyświetlenia</div>
                    <?php endif; ?>
                <?php else: ?>
                    <h3>Ranking Plemion</h3>
                    <div class="no-data">
                        System plemion jest w trakcie implementacji.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require 'footer.php'; ?>
</body>
</html> 