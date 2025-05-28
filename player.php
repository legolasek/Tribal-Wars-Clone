<?php
require 'init.php';
require_once 'config/config.php';
require_once 'lib/Database.php';

$username = isset($_GET['user']) ? trim($_GET['user']) : '';

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil gracza <?php echo htmlspecialchars($username); ?> - Tribal Wars Nowa Edycja</title>
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
    <style>
        .player-profile { max-width: 600px; margin: 40px auto; background: #fffaf0; border: 2px solid #8b4513; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 32px 36px; }
        .player-profile h2 { color: #654321; margin-top: 0; }
        .villages-list { margin-top: 18px; }
        .villages-list table { width: 100%; border-collapse: collapse; }
        .villages-list th, .villages-list td { border: 1px solid #e0cfa0; padding: 8px 10px; text-align: left; }
        .villages-list th { background: #d2b48c; color: #4a3c2b; }
        .villages-list td { background: #fffaf0; }
        .back-btn { margin-top: 18px; background: #8b4513; color: #f0e6c8; border: none; border-radius: 4px; padding: 8px 18px; font-size: 1em; cursor: pointer; text-decoration: none; display: inline-block; }
        .back-btn:hover { background: #a0522d; color: #fffbe6; }
    </style>
</head>
<body>
<div class="player-profile">
<?php
if ($username === '') {
    echo '<p>Nie podano gracza.</p>';
    echo '<a href="map.php" class="back-btn">&larr; Powrót do mapy</a>';
    exit;
}

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT id, username, registration_date FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<h2>Gracz nie istnieje</h2>';
    echo '<a href="map.php" class="back-btn">&larr; Powrót do mapy</a>';
    $database->closeConnection();
    exit;
}

$user_id = $user['id'];

// Pobierz wioski gracza
$stmt = $conn->prepare("SELECT name, x_coord, y_coord FROM villages WHERE user_id = ? ORDER BY name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$villages = [];
while ($row = $result->fetch_assoc()) {
    $villages[] = $row;
}
$stmt->close();

// Ranking gracza wg liczby wiosek
$ranking = 0;
$total_players = 0;
$stmt = $conn->prepare("SELECT u.username, COUNT(v.id) as villages_count FROM users u LEFT JOIN villages v ON u.id = v.user_id GROUP BY u.id ORDER BY villages_count DESC, u.username ASC");
$stmt->execute();
$result = $stmt->get_result();
$rankings = [];
while ($row = $result->fetch_assoc()) {
    $rankings[] = $row;
}
$stmt->close();
$total_players = count($rankings);
foreach ($rankings as $i => $row) {
    if ($row['username'] === $username) {
        $ranking = $i + 1;
        break;
    }
}

// Liczba wszystkich graczy
$res = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $res ? $res->fetch_assoc()['total'] : 0;
// Liczba wszystkich wiosek
$res = $conn->query("SELECT COUNT(*) as total FROM villages");
$total_villages = $res ? $res->fetch_assoc()['total'] : 0;

$database->closeConnection();

?>
    <h2>Profil gracza: <?php echo htmlspecialchars($username); ?></h2>
    <div class="summary-box" style="background:#f5e7c2; border:1px solid #c8bca8; border-radius:6px; padding:12px 18px; margin-bottom:18px;">
        <b>Data rejestracji gracza:</b> <?php echo isset($user['registration_date']) ? htmlspecialchars($user['registration_date']) : 'brak danych'; ?><br>
        <b>Liczba graczy:</b> <?php echo $total_users; ?><br>
        <b>Liczba wiosek w grze:</b> <?php echo $total_villages; ?>
    </div>
    <p><b>Liczba wiosek:</b> <?php echo count($villages); ?></p>
    <p><b>Miejsce w rankingu:</b> <?php echo $ranking; ?> / <?php echo $total_players; ?></p>
    <div class="villages-list">
        <h3>Lista wiosek</h3>
        <?php if (count($villages) === 0): ?>
            <p>Gracz nie posiada żadnej wioski.</p>
        <?php else: ?>
        <table>
            <tr><th>Nazwa wioski</th><th>Koordynaty</th><th></th></tr>
            <?php foreach ($villages as $v): ?>
                <tr>
                    <td><?php echo htmlspecialchars($v['name']); ?></td>
                    <td>(<?php echo $v['x_coord']; ?>|<?php echo $v['y_coord']; ?>)</td>
                    <td><a href="map.php?center_x=<?php echo $v['x_coord']; ?>&center_y=<?php echo $v['y_coord']; ?>" class="back-btn" style="padding:4px 10px; font-size:0.95em; margin:0;">Pokaż na mapie</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <a href="map.php" class="back-btn">&larr; Powrót do mapy</a>
</div>
</body>
</html> 