<?php
require 'init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();
header('Content-Type: application/json');

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nie jesteś zalogowany.']);
    exit();
}
$user_id = $_SESSION['user_id'];

// Walidacja parametrów
$village_id = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;
$targetCoords = trim($_POST['target_coords'] ?? '');
$wood = isset($_POST['wood']) ? max(0, (int)$_POST['wood']) : 0;
$clay = isset($_POST['clay']) ? max(0, (int)$_POST['clay']) : 0;
$iron = isset($_POST['iron']) ? max(0, (int)$_POST['iron']) : 0;

if ($village_id <= 0 || !preg_match('/^(\d+)\|(\d+)$/', $targetCoords, $matches)) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe dane żądania.']);
    exit();
}
$target_x = (int)$matches[1];
$target_y = (int)$matches[2];

// Database connection: $conn provided by init.php

// Sprawdź, czy wioska należy do użytkownika
$stmt = $conn->prepare("SELECT id, x_coord, y_coord FROM villages WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $village_id, $user_id);
$stmt->execute();
$village = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$village) {
    echo json_encode(['success' => false, 'error' => 'Nie masz dostępu do tej wioski.']);
    $database->closeConnection();
    exit();
}

// Znajdź wioskę docelową (jeśli istnieje)
$stmt = $conn->prepare("SELECT id FROM villages WHERE x_coord = ? AND y_coord = ? LIMIT 1");
$stmt->bind_param("ii", $target_x, $target_y);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$target_village_id = $row ? (int)$row['id'] : null;

// Pobierz poziom rynku i oblicz liczbę kupców
$tmp = $conn->prepare("SELECT vb.level FROM village_buildings vb JOIN building_types bt ON vb.building_type_id=bt.id WHERE vb.village_id=? AND bt.internal_name='market'");
$tmp->bind_param("i", $village_id);
$tmp->execute();
$market_lvl = (int)$tmp->get_result()->fetch_assoc()['level'];
$tmp->close();
$traders_capacity = max(3, 3 + floor($market_lvl * 0.7));

// Sprawdź aktywne transporty (outgoing)
$stmt = $conn->prepare("SELECT SUM(traders_count) AS used FROM trade_routes WHERE source_village_id=? AND arrival_time > NOW()");
$stmt->bind_param("i", $village_id);
$stmt->execute();
$used = (int)$stmt->get_result()->fetch_assoc()['used'];
$stmt->close();
$available = max(0, $traders_capacity - $used);
if ($available < 1) {
    echo json_encode(['success' => false, 'error' => 'Brak dostępnych kupców.']);
    $database->closeConnection();
    exit();
}

// Pobierz surowce
$stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
$stmt->bind_param("i", $village_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($res['wood'] < $wood || $res['clay'] < $clay || $res['iron'] < $iron) {
    echo json_encode(['success' => false, 'error' => 'Niewystarczające zasoby.']);
    $database->closeConnection();
    exit();
}

// Oblicz czasy podróży
$distance = calculateDistance($village['x_coord'], $village['y_coord'], $target_x, $target_y);
$speed = defined('TRADER_SPEED') ? TRADER_SPEED : 100; // pola na godzinę
$timeSec = calculateTravelTime($distance, $speed);
$departure = date('Y-m-d H:i:s');
$arrival = date('Y-m-d H:i:s', time() + $timeSec);

// Dodaj rekord
$count_traders = 1;
$stmt = $conn->prepare("INSERT INTO trade_routes (source_village_id, target_village_id, target_x, target_y, wood, clay, iron, traders_count, departure_time, arrival_time) VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->bind_param("iiiiiiiiss", $village_id, $target_village_id, $target_x, $target_y, $wood, $clay, $iron, $count_traders, $departure, $arrival);
$stmt->execute();
$route_id = $stmt->insert_id;
$stmt->close();

// Odejmij surowce
$stmt = $conn->prepare("UPDATE villages SET wood=wood-?, clay=clay-?, iron=iron-? WHERE id=?");
$stmt->bind_param("iiii", $wood, $clay, $iron, $village_id);
$stmt->execute();
$stmt->close();

$database->closeConnection();

echo json_encode(['success' => true, 'route_id' => $route_id, 'departure_time' => $departure, 'arrival_time' => $arrival]);
exit();
?> 