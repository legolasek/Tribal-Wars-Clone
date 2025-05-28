<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Endpoint: map_data.php
// Zwraca JSON z danymi o wioskach, graczach i sojuszach w wycinku mapy
require_once 'config/config.php';
require_once 'lib/Database.php';

header('Content-Type: application/json; charset=utf-8');

$x = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$y = isset($_GET['y']) ? (int)$_GET['y'] : 0;
$size = isset($_GET['size']) ? max(5, min(50, (int)$_GET['size'])) : 20;

$min_x = $x - floor($size/2);
$max_x = $x + floor($size/2);
$min_y = $y - floor($size/2);
$max_y = $y + floor($size/2);

$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();

// Pobierz wioski
$stmt = $conn->prepare("SELECT id, x_coord, y_coord, name, user_id, points FROM villages WHERE x_coord >= ? AND x_coord <= ? AND y_coord >= ? AND y_coord <= ?");
$stmt->bind_param('iiii', $min_x, $max_x, $min_y, $max_y);
$stmt->execute();
$res = $stmt->get_result();
$villages = [];
while ($v = $res->fetch_assoc()) {
    $type = ($v['user_id'] == -1) ? 'barbarian' : 'player';
    $img = ($type == 'barbarian') ? 'img/ds_graphic/map/village_barb.png' : 'img/ds_graphic/map/village_player.png';
    $villages[] = [
        'id' => $v['id'],
        'x' => $v['x_coord'],
        'y' => $v['y_coord'],
        'name' => $v['name'],
        'user_id' => $v['user_id'],
        'points' => $v['points'],
        'type' => $type,
        'img' => $img
    ];
}
$stmt->close();

// Pobierz graczy
$res = $conn->query('SELECT id, username, points, ally_id FROM users');
$players = [];
while ($p = $res->fetch_assoc()) {
    $players[] = [
        'id' => $p['id'],
        'username' => $p['username'],
        'points' => $p['points'],
        'ally_id' => $p['ally_id'] ?? null
    ];
}
$res->close();

// Pobierz sojusze
if ($conn->query("SHOW TABLES LIKE 'allies'")->num_rows) {
    $res = $conn->query('SELECT id, name, points, short FROM allies');
    $allies = [];
    while ($a = $res->fetch_assoc()) {
        $allies[] = [
            'id' => $a['id'],
            'name' => $a['name'],
            'points' => $a['points'],
            'short' => $a['short']
        ];
    }
    $res->close();
} else {
    $allies = [];
}

$db->closeConnection();

echo json_encode([
    'villages' => $villages,
    'players' => $players,
    'allies' => $allies
]); 