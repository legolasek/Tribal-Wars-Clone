<?php
require '../../init.php';
validateCSRF();
header('Content-Type: text/html; charset=UTF-8'); // Zwracamy HTML po akcji POST

// Configuration and DB connection provided by init.php
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php'; // Potrzebujemy BuildingConfigManager

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nie jesteś zalogowany.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Musisz być zalogowany, aby wykonać tę akcję.</p>";
        header("Location: ../../auth/login.php");
    }
    exit();
}

// Sprawdź, czy przekazano ID zadania do anulowania
if (!isset($_POST['queue_item_id']) || !is_numeric($_POST['queue_item_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nieprawidłowe ID zadania.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Nieprawidłowe ID zadania.</p>";
        header("Location: ../../game/game.php");
    }
    exit();
}

$queue_item_id = (int)$_POST['queue_item_id'];
$user_id = $_SESSION['user_id'];

$buildingManager = new BuildingManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);

try {
    // Pobierz informacje o zadaniu z kolejki i upewnij się, że należy do wioski zalogowanego użytkownika
    $stmt = $conn->prepare("
        SELECT bq.id, bq.village_id, bq.village_building_id, bt.name_pl, bq.building_type_id, bq.level
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        JOIN villages v ON bq.village_id = v.id
        WHERE bq.id = ? AND v.user_id = ?
    ");
    $stmt->bind_param("ii", $queue_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Zadanie nie istnieje lub nie masz do niego dostępu.']);
        } else {
            $_SESSION['game_message'] = "<p class='error-message'>Zadanie nie istnieje lub nie masz do niego dostępu.</p>";
            header("Location: ../game/game.php");
        }
        $stmt->close();
        exit();
    }

    $queue_item = $result->fetch_assoc();
    $stmt->close();
    
    // Pobierz szczegóły budynku dla anulowanego zadania (potrzebne do obliczenia kosztu)
    $building_type_id = $queue_item['building_type_id']; // Zakładając, że building_queue zawiera building_type_id
    $cancelled_level = $queue_item['level'];
    
    // Pobierz internal_name z building_types na podstawie building_type_id
    $stmt_get_internal_name = $conn->prepare("SELECT internal_name FROM building_types WHERE id = ?");
    $stmt_get_internal_name->bind_param("i", $building_type_id);
    $stmt_get_internal_name->execute();
    $building_type_row = $stmt_get_internal_name->get_result()->fetch_assoc();
    $stmt_get_internal_name->close();
    
    if (!$building_type_row) {
         throw new Exception("Nie znaleziono typu budynku dla anulowanego zadania.");
    }
    $cancelled_building_internal_name = $building_type_row['internal_name'];

    // Oblicz koszt rozbudowy do anulowanego poziomu (poprzedni poziom + 1)
    // Koszty są obliczane dla przejścia Z poziomu $cancelled_level-1 NA poziom $cancelled_level
    $cost_level_before_cancel = $cancelled_level - 1; 
    $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($cancelled_building_internal_name, $cost_level_before_cancel);
    
    if (!$upgrade_costs) {
         error_log("Błąd obliczania kosztów dla anulowanej budowy: " . $cancelled_building_internal_name . " do poziomu " . $cancelled_level);
         // Kontynuuj usuwanie zadania nawet jeśli koszty nie udało się obliczyć (lepiej usunąć niż zostawić wiszące zadanie)
    }
    
    // Rozpocznij transakcję dla atomowości (usunięcie z kolejki + zwrot surowców)
    $conn->begin_transaction();

    // Usuń zadanie z kolejki
    $stmt_delete = $conn->prepare("DELETE FROM building_queue WHERE id = ?");
    $stmt_delete->bind_param("i", $queue_item_id);
    $success = $stmt_delete->execute();
    $stmt_delete->close();

    if (!$success) {
         throw new Exception("Błąd podczas usuwania zadania z kolejki.");
    }

    // Zwróć część surowców, jeśli koszty były dostępne
    if ($upgrade_costs) {
        $return_percentage = 0.9; // 90% zwrotu surowców
        $returned_wood = floor($upgrade_costs['wood'] * $return_percentage);
        $returned_clay = floor($upgrade_costs['clay'] * $return_percentage);
        $returned_iron = floor($upgrade_costs['iron'] * $return_percentage);
        
        // Dodaj surowce do wioski
        $stmt_add_resources = $conn->prepare("
            UPDATE villages 
            SET wood = wood + ?, clay = clay + ?, iron = iron + ? 
            WHERE id = ?
        ");
        $stmt_add_resources->bind_param("iiii", $returned_wood, $returned_clay, $returned_iron, $queue_item['village_id']);
        
        if (!$stmt_add_resources->execute()) {
            // Zaloguj błąd, ale nie rzucaj wyjątku, żeby nie cofać usunięcia z kolejki
            error_log("Błąd podczas zwracania surowców dla anulowanej budowy zadania ID " . $queue_item_id . ": " . $conn->error);
        }
         $stmt_add_resources->close();
    }

    // Zatwierdź transakcję
    $conn->commit();

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        // Pobierz zaktualizowane zasoby wioski po zwróceniu surowców
        $updatedVillageInfo = $villageManager->getVillageInfo($queue_item['village_id']);
        $response = [
            'success' => true,
            'message' => 'Zadanie budowy zostało anulowane. Odzyskano część surowców.',
            'queue_item_id' => $queue_item_id, // Zwróć ID anulowanego zadania
            'village_id' => $queue_item['village_id'],
            'village_building_id' => $queue_item['village_building_id'],
            'building_internal_name' => $cancelled_building_internal_name, // Zwróć internal_name
            'new_resources' => null // Docelowo zwróć aktualne surowce lub zaktualizuj je przez resourceUpdater
        ];
         if ($updatedVillageInfo) {
             // Zwróć aktualne zasoby, populację i pojemności
             $response['village_info'] = [
                 'wood' => $updatedVillageInfo['wood'],
                 'clay' => $updatedVillageInfo['clay'],
                 'iron' => $updatedVillageInfo['iron'],
                 'population' => $updatedVillageInfo['population'], // Może się zmienić jeśli anulowano farmę
                 'warehouse_capacity' => $updatedVillageInfo['warehouse_capacity'],
                 'farm_capacity' => $updatedVillageInfo['farm_capacity']
             ];
         }

        echo json_encode($response);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Zadanie budowy zostało anulowane. Odzyskano część surowców.</p>";
        header("Location: ../game/game.php");
    }

} catch (Exception $e) {
    $conn->rollback(); // Cofnij transakcję w przypadku błędu
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Wystąpił błąd: ' . $e->getMessage()]);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Wystąpił błąd: " . htmlspecialchars($e->getMessage()) . "</p>";
        header("Location: ../game/game.php");
    }
}

$conn->close();
?> 