<?php
require_once '../../init.php';
require_once '../../lib/BuildingManager.php';
require_once '../../lib/VillageManager.php'; // Potrzebny do sprawdzenia uprawnień
require_once '../../lib/functions.php'; // Dla validateCSRF

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nie jesteś zalogowany.']);
    exit();
}

validateCSRF(); // Walidacja tokena CSRF

$user_id = $_SESSION['user_id'];
$queue_id = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : null;
$village_id = isset($_POST['village_id']) ? (int)$_POST['village_id'] : null;

if (!$queue_id || !$village_id) {
    echo json_encode(['status' => 'error', 'message' => 'Brak wymaganych parametrów (queue_id, village_id).']);
    exit();
}

$villageManager = new VillageManager($conn);
// Sprawdź, czy wioska należy do zalogowanego użytkownika
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Brak dostępu do wioski.']);
    exit();
}

// Sprawdź, czy zadanie w kolejce należy do tej wioski
$stmt = $conn->prepare("SELECT COUNT(*) FROM building_queue WHERE id = ? AND village_id = ?");
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Błąd serwera (prepare).']);
    exit();
}
$stmt->bind_param("ii", $queue_id, $village_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$stmt->close();

if ((int)$row[0] === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Zadanie w kolejce nie istnieje lub nie należy do tej wioski.']);
    exit();
}

// Usuń zadanie z kolejki
$stmt = $conn->prepare("DELETE FROM building_queue WHERE id = ?");
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Błąd serwera (prepare delete).']);
    exit();
}
$stmt->bind_param("i", $queue_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Budowa została anulowana. Surowce nie zostały zwrócone.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nie udało się anulować budowy.']);
}
$stmt->close();

$conn->close();
?>
