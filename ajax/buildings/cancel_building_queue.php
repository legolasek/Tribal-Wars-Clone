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

// Rozpocznij transakcję
$conn->begin_transaction();

try {
    // Pobierz szczegóły zadania z kolejki i zablokuj wiersz
    $stmt = $conn->prepare("SELECT building_type_id, level FROM building_queue WHERE id = ? AND village_id = ? FOR UPDATE");
    if ($stmt === false) throw new Exception('Błąd serwera (prepare select).');
    $stmt->bind_param("ii", $queue_id, $village_id);
    $stmt->execute();
    $queue_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$queue_item) {
        throw new Exception('Zadanie w kolejce nie istnieje lub nie należy do tej wioski.');
    }

    // Pobierz internal_name budynku
    $stmt_type = $conn->prepare("SELECT internal_name FROM building_types WHERE id = ?");
    if ($stmt_type === false) throw new Exception('Błąd serwera (prepare get type).');
    $stmt_type->bind_param("i", $queue_item['building_type_id']);
    $stmt_type->execute();
    $building_type = $stmt_type->get_result()->fetch_assoc();
    $stmt_type->close();

    if (!$building_type) {
        throw new Exception('Nie znaleziono typu budynku.');
    }
    $internal_name = $building_type['internal_name'];
    $next_level = $queue_item['level'];

    // Oblicz koszt, który należy zwrócić
    require_once '../../lib/managers/BuildingConfigManager.php';
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $refund_cost = $buildingManager->getBuildingUpgradeCost($internal_name, $next_level);

    if (!$refund_cost) {
        throw new Exception('Nie udało się obliczyć kosztu do zwrotu.');
    }

    // Zwróć surowce do wioski
    $stmt_refund = $conn->prepare("UPDATE villages SET wood = wood + ?, clay = clay + ?, iron = iron + ? WHERE id = ?");
    if ($stmt_refund === false) throw new Exception('Błąd serwera (prepare refund).');
    $stmt_refund->bind_param("dddi", $refund_cost['wood'], $refund_cost['clay'], $refund_cost['iron'], $village_id);
    if (!$stmt_refund->execute()) {
        throw new Exception('Nie udało się zwrócić surowców.');
    }
    $stmt_refund->close();

    // Usuń zadanie z kolejki
    $stmt_delete = $conn->prepare("DELETE FROM building_queue WHERE id = ?");
    if ($stmt_delete === false) throw new Exception('Błąd serwera (prepare delete).');
    $stmt_delete->bind_param("i", $queue_id);
    if (!$stmt_delete->execute()) {
        throw new Exception('Nie udało się usunąć zadania z kolejki.');
    }
    $stmt_delete->close();

    // Zatwierdź transakcję
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Budowa została anulowana, a surowce zostały zwrócone.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
