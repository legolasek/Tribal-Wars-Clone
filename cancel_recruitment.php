<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();

require_once 'lib/utils/AjaxResponse.php'; // Dołącz AjaxResponse

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        AjaxResponse::error('Nie jesteś zalogowany.', null, 401);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Musisz być zalogowany, aby wykonać tę akcję.</p>";
        header("Location: login.php");
    }
    exit();
}

$user_id = $_SESSION['user_id'];

// Sprawdź, czy przekazano ID zadania do anulowania
if (!isset($_POST['queue_item_id']) || !is_numeric($_POST['queue_item_id'])) {
    if (isset($_POST['ajax'])) {
        AjaxResponse::error('Nieprawidłowe ID zadania rekrutacji.', null, 400);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Nieprawidłowe ID zadania rekrutacji.</p>";
        header("Location: game.php");
    }
    exit();
}

$queue_item_id = (int)$_POST['queue_item_id'];

$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$unitConfigManager = new UnitConfigManager($conn); // Utworzenie instancji

try {
    // Pobierz informacje o zadaniu z kolejki i sprawdź, czy należy do wioski zalogowanego użytkownika
    // Pobieramy również unit_type_id i count, aby obliczyć zwrot kosztów
    $stmt = $conn->prepare("
        SELECT uq.id, uq.village_id, uq.unit_type_id, uq.count, uq.count_finished, ut.name_pl, ut.internal_name
        FROM unit_queue uq
        JOIN unit_types ut ON uq.unit_type_id = ut.id
        JOIN villages v ON uq.village_id = v.id
        WHERE uq.id = ? AND v.user_id = ?
    ");

    $stmt->bind_param("ii", $queue_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isset($_POST['ajax'])) {
            AjaxResponse::error('Zadanie rekrutacji nie istnieje lub nie masz do niego dostępu.', null, 404);
        } else {
            $_SESSION['game_message'] = "<p class='error-message'>Zadanie rekrutacji nie istnieje lub nie masz do niego dostępu.</p>";
            header("Location: game.php");
        }
        $stmt->close();
        exit();
    }

    $queue = $result->fetch_assoc();
    $stmt->close();

    $village_id = $queue['village_id'];
    $unit_type_id = $queue['unit_type_id'];
    $total_count = $queue['count'];
    $finished_count = $queue['count_finished'];
    $unit_name_pl = $queue['name_pl'];
    $unit_internal_name = $queue['internal_name'];

    // Oblicz liczbę jednostek do anulowania (te, które nie zostały jeszcze ukończone)
    $to_cancel_count = $total_count - $finished_count;

    // Rozpocznij transakcję
    $conn->begin_transaction();

    // Usuń kolejkę rekrutacji
    $stmt_delete = $conn->prepare("DELETE FROM unit_queue WHERE id = ?");
    $stmt_delete->bind_param("i", $queue_item_id);
    $success = $stmt_delete->execute();
    // Nie zamykaj od razu, jeśli transakcja będzie kontynuowana
    // $stmt_delete->close(); // Zamkniemy na końcu transakcji

    if (!$success) {
        throw new Exception("Błąd podczas usuwania zadania rekrutacji z kolejki.");
    }

    // === Zwróć część surowców i populacji za anulowane jednostki ===
    // Pobierz koszty jednostki z UnitConfigManager (lub unit_types_cache w UnitManager)
    // Zakładamy, że UnitConfigManager ma metodę getUnitCost($unit_type_id)
    // Jeśli nie, musimy pobrać koszty z tabeli unit_types

    $unit_config = $unitConfigManager->getUnitConfig($unit_internal_name); // Pobierz konfigurację jednostki po internal_name
    if ($unit_config) {
        $return_percentage = 0.9; // 90% zwrotu

        $returned_wood = floor($unit_config['cost_wood'] * $to_cancel_count * $return_percentage);
        $returned_clay = floor($unit_config['cost_clay'] * $to_cancel_count * $return_percentage);
        $returned_iron = floor($unit_config['cost_iron'] * $to_cancel_count * $return_percentage);

        // Populacja jest zwracana (zmniejsza zużycie), więc odejmujemy od obecnej populacji wioski
        $returned_population = floor($unit_config['population'] * $to_cancel_count * $return_percentage);

        // Dodaj surowce i odejmij populację od wioski
        $stmt_update_village = $conn->prepare("
            UPDATE villages 
            SET wood = wood + ?, clay = clay + ?, iron = iron + ?, population = population - ? 
            WHERE id = ?
        ");

        $stmt_update_village->bind_param("ddiii", $returned_wood, $returned_clay, $returned_iron, $returned_population, $village_id);

        if (!$stmt_update_village->execute()) {
            // Zaloguj błąd, ale nie rzucaj wyjątku, żeby nie cofać usunięcia z kolejki
            error_log("Błąd podczas zwracania surowców/populacji dla anulowanej rekrutacji zadania ID " . $queue_item_id . ": " . $conn->error);
        }
        // $stmt_update_village->close(); // Zamkniemy na końcu transakcji
    } else {
        error_log("Błąd: Nie znaleziono konfiguracji jednostki do obliczenia zwrotu kosztów: " . $unit_internal_name);
    }
    // ========================================================

    // Dodaj już wytrenowane jednostki do wioski (ta logika już była)
    if ($finished_count > 0) {
        // Użyj VillageManager lub UnitManager (jeśli ma taką publiczną metodę)
        // Sprawdziłem UnitManager, ma private addUnitsToVillage, potrzebujemy publicznej
        // lub użyć logiki VillageManager (który takiej metody nie ma publicznej)
        // Na razie użyję logiki bezpośrednio, docelowo powinno być w Managerze
        $stmt_add_units = $conn->prepare("
            INSERT INTO village_units (village_id, unit_type_id, count) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE count = count + VALUES(count)
        ");
        $stmt_add_units->bind_param("iii", $village_id, $unit_type_id, $finished_count);
        if (!$stmt_add_units->execute()) {
            error_log("Błąd podczas dodawania ukończonych jednostek po anulowaniu rekrutacji: " . $conn->error);
            // Nie rzucaj wyjątku, żeby nie cofać usunięcia z kolejki
        }
        // $stmt_add_units->close(); // Zamkniemy na końcu transakcji
        $message_finished = ". Dodano $finished_count ukończonych jednostek do wioski.";;
} else {
        $message_finished = ".";
    }

    // === Zatwierdź transakcję ===
    $conn->commit();
    // ==========================

    // === Przygotuj odpowiedź (AJAX lub przekierowanie) ===
    if (isset($_POST['ajax'])) {
        // Pobierz zaktualizowane dane wioski po zwróceniu surowców i populacji
        $updatedVillageInfo = $villageManager->getVillageInfo($village_id);
        AjaxResponse::success([
            'success' => true,
            'message' => "Anulowano rekrutację {$unit_name_pl} (x$to_cancel_count)$message_finished",
            'queue_item_id' => $queue_item_id,
            'village_id' => $village_id,
            'unit_internal_name' => $unit_internal_name,
            'village_info' => $updatedVillageInfo // Zwróć zaktualizowane info o wiosce
        ]);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Anulowano rekrutację {$unit_name_pl} (x$to_cancel_count)$message_finished</p>";
        header("Location: game.php");
    }
    // ==================================================

} catch (Exception $e) {
    // === Wycofaj transakcję w przypadku błędu ===
    $conn->rollback();
    // =======================================

    // === Obsłuż wyjątek i zwróć błąd ===
    if (isset($_POST['ajax'])) {
        AjaxResponse::error(
            'Wystąpił błąd podczas anulowania rekrutacji: ' . $e->getMessage(),
            ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
            500 // HTTP status code
        );
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Wystąpił błąd podczas anulowania rekrutacji: " . htmlspecialchars($e->getMessage()) . "</p>";
        header("Location: game.php");
    }
    // ==================================
} finally {
    // Zamknij prepared statements, jeśli nie zostały zamknięte wcześniej w try/catch
    if (isset($stmt_delete) && $stmt_delete !== null) { $stmt_delete->close(); }
    if (isset($stmt_update_village) && $stmt_update_village !== null) { $stmt_update_village->close(); }
    if (isset($stmt_add_units) && $stmt_add_units !== null) { $stmt_add_units->close(); }
    // Połączenie z bazą danych jest zarządzane przez init.php
    // $conn->close(); // Usunięto
}
?> 