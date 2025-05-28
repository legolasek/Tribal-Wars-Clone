<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nie jesteś zalogowany.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Musisz być zalogowany, aby wykonać tę akcję.</p>";
        header("Location: login.php");
    }
    exit();
}

// Sprawdź, czy przekazano wymagane parametry
if (!isset($_POST['village_id']) || !is_numeric($_POST['village_id']) || 
    !isset($_POST['building_id']) || !is_numeric($_POST['building_id']) ||
    !isset($_POST['recruit']) || !is_array($_POST['recruit'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Brak wymaganych parametrów.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Brak wymaganych parametrów.</p>";
        header("Location: game.php");
    }
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = (int)$_POST['village_id'];
$building_id = (int)$_POST['building_id'];
$recruit_data = $_POST['recruit'];

// Wymagane pliki
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'VillageManager.php';

// Połącz z bazą danych
// Database connection: $conn provided by init.php

if (!$conn) {
    $_SESSION['game_message'] = 'Błąd: Nie udało się połączyć z bazą danych.';
    header('Location: game.php');
    exit();
}

$unitManager = new UnitManager($conn);

try {
    // Rozpocznij transakcję
    $conn->begin_transaction();
    
    // Sprawdź, czy wioska należy do użytkownika
    $stmt_check_village = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ?");
    $stmt_check_village->bind_param("ii", $village_id, $user_id);
    $stmt_check_village->execute();
    $result_village = $stmt_check_village->get_result();
    
    if ($result_village->num_rows === 0) {
        throw new Exception("Brak uprawnień do tej wioski.");
    }
    $stmt_check_village->close();
    
    // Sprawdź, czy budynek to koszary i należy do wioski
    $stmt_check_building = $conn->prepare("
        SELECT vb.id, bt.internal_name, vb.level 
    FROM village_buildings vb 
    JOIN building_types bt ON vb.building_type_id = bt.id 
        WHERE vb.id = ? AND vb.village_id = ? AND bt.internal_name = 'barracks'
    ");
    $stmt_check_building->bind_param("ii", $building_id, $village_id);
    $stmt_check_building->execute();
    $result_building = $stmt_check_building->get_result();
    
    if ($result_building->num_rows === 0) {
        throw new Exception("Nieprawidłowy budynek.");
    }
    
    $building = $result_building->fetch_assoc();
    $barracks_level = $building['level'];
    $stmt_check_building->close();
    
    // Pobierz aktualną ilość kolejek rekrutacji dla tych koszar (dla danego budynku/typu kolejki)
    // Zmieniamy zapytanie, żeby sprawdzało dla building_id, nie tylko building_type
    $stmt_check_queue = $conn->prepare("
        SELECT COUNT(*) as queue_count 
        FROM unit_queue 
        WHERE village_id = ? AND building_type = ?
    ");
    // Pobierz building_type na podstawie building_id (lub internal_name z building_types)
    // Na razie użyjemy 'barracks' na sztywno, ale docelowo powinno być dynamiczne
    $queue_building_type = 'barracks'; // Może wymagać pobrania z building_types na podstawie building_id

    $stmt_check_queue->bind_param("is", $village_id, $queue_building_type); // Używamy village_id i building_type
    $stmt_check_queue->execute();
    $result_queue = $stmt_check_queue->get_result();
    $queue_count = $result_queue->fetch_assoc()['queue_count'];
    $stmt_check_queue->close();
    
    // Sprawdź, czy nie przekroczono limitu kolejek rekrutacji (2 dla koszar) - TO MOŻE BYĆ ZMIENNE ZALEŻNE OD POZIOMU BUDYNKU
    // Na razie zostawiamy na sztywno 2, ale warto dodać konfigurację np. w building_types lub oddzielnej tabeli
    $max_queues = 2; // Domyślny limit kolejek rekrutacji w jednym budynku (np. koszarach)
    // if ($barracks_level >= 10) $max_queues = 3; // Przykładowy dynamiczny limit

    if ($queue_count >= $max_queues) {
        throw new Exception("Maksymalna ilość kolejek rekrutacji ($max_queues) została osiągnięta dla tego budynku.");
    }

// Pobierz aktualne zasoby wioski
    $stmt_resources = $conn->prepare("SELECT wood, clay, iron, population FROM villages WHERE id = ?");
    $stmt_resources->bind_param("i", $village_id);
    $stmt_resources->execute();
    $resources = $stmt_resources->get_result()->fetch_assoc();
    $stmt_resources->close();
    
    // Sprawdź, czy wybrano jakiekolwiek jednostki
    $total_units = 0;
    $total_population = 0;
    $total_wood = 0;
    $total_clay = 0;
    $total_iron = 0;
    $units_to_recruit = [];
    
    foreach ($recruit_data as $unit_type_id => $count) {
        $count = (int)$count;
        if ($count <= 0) continue;
        
        $total_units += $count;
        
        // Pobierz informacje o jednostce
        $stmt_unit = $conn->prepare("
            SELECT internal_name, name_pl, cost_wood, cost_clay, cost_iron, population, training_time_base, required_building_level
            FROM unit_types 
            WHERE id = ? AND building_type = 'barracks'
        ");
        $stmt_unit->bind_param("i", $unit_type_id);
        $stmt_unit->execute();
        $result_unit = $stmt_unit->get_result();
        
        if ($result_unit->num_rows === 0) {
            throw new Exception("Nieprawidłowa jednostka.");
        }
        
        $unit = $result_unit->fetch_assoc();
        $stmt_unit->close();
        
        // Sprawdź, czy poziom koszar jest wystarczający
        if ($barracks_level < $unit['required_building_level']) {
            throw new Exception("Zbyt niski poziom koszar dla jednostki " . $unit['name_pl'] . ".");
        }
        
        // Oblicz koszt i czas treningu
        $wood_cost = $count * $unit['cost_wood'];
        $clay_cost = $count * $unit['cost_clay'];
        $iron_cost = $count * $unit['cost_iron'];
        $population_cost = $count * $unit['population'];
        
        // Dodaj do sumy
        $total_wood += $wood_cost;
        $total_clay += $clay_cost;
        $total_iron += $iron_cost;
        $total_population += $population_cost;
        
        // Oblicz czas treningu z uwzględnieniem poziomu koszar (5% szybciej na poziom) - TYMCZASOWO OBCZAMY CZAS NA JEDNOSTKĘ
        // CAŁKOWITY czas dla partii jednostek będzie obliczany przy dodawaniu do kolejki
        $training_time_base = $unit['training_time_base'];
        $training_time_per_unit = floor($training_time_base * pow(0.95, $barracks_level - 1)); // Czas na jednostkę
        // Całkowity czas treningu dla tej partii jednostek = $training_time_per_unit * $count;
        
        $units_to_recruit[] = [
            'unit_type_id' => $unit_type_id,
            'count' => $count,
            'training_time_per_unit' => $training_time_per_unit, // Czas na jednostkę
            'name' => $unit['name_pl'],
            'internal_name' => $unit['internal_name']
        ];
    }
    
    // Sprawdź, czy wybrano jakiekolwiek jednostki
    if ($total_units === 0) {
        throw new Exception("Nie wybrano żadnych jednostek do rekrutacji.");
    }
    
    // Sprawdź, czy gracz ma wystarczające zasoby i populację
    if ($resources['wood'] < $total_wood || 
        $resources['clay'] < $total_clay || 
        $resources['iron'] < $total_iron ||
        ($resources['population'] + $total_population) > $village['farm_capacity']) { // Sprawdź limit populacji
        // Trzeba pobrać aktualną farm_capacity wioski. Pobraliśmy tylko wood, clay, iron, population.
        // Pobierz pełne dane wioski, żeby mieć farm_capacity
        $village_data = $villageManager->getVillageInfo($village_id); // Pobierz pełne dane
        if (!$village_data || ($village_data['population'] + $total_population) > $village_data['farm_capacity']) {
            throw new Exception("Brak wystarczającej ilości wolnej populacji w wiosce.");
        }
        
        throw new Exception("Brak wystarczających zasobów lub wolnej populacji na rekrutację wybranych jednostek.");
    }
    
    // === ODEJMIJ ZASOBY i populację ===
    $stmt_deduct_resources = $conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ?, population = population + ? WHERE id = ?");
     // Upewnij się, że odejmujesz koszty i dodajesz populację (ludność jest 'zużywana', więc dodajemy do obecnej liczby ludności w wiosce)
    $stmt_deduct_resources->bind_param("ddiii", $total_wood, $total_clay, $total_iron, $total_population, $village_id);
    if (!$stmt_deduct_resources->execute()) {
        throw new Exception("Błąd podczas odejmowania zasobów i dodawania populacji: " . $stmt_deduct_resources->error);
    }
    $stmt_deduct_resources->close();
    // ===============================

    // Rekrutuj jednostki - dla każdego typu jednostki utwórz osobną kolejkę
    // Czas zakończenia kolejnego zadania zależy od czasu zakończenia poprzedniego ZADANIA Z TEGO SAMEGO BUDYNKU (KOSZAR)
    $recruited_queues = [];
    $current_time = time();
    $last_finish_time = $current_time; // Czas zakończenia ostatniego zadania w tej kolejce

    // Pobierz czas zakończenia ostatniego zadania w obecnej kolejce dla tego budynku
    $stmt_last_queue = $conn->prepare("
        SELECT finish_at FROM unit_queue 
        WHERE village_id = ? AND building_type = ? 
        ORDER BY finish_at DESC LIMIT 1");
    $stmt_last_queue->bind_param("is", $village_id, $queue_building_type);
    $stmt_last_queue->execute();
    $result_last_queue = $stmt_last_queue->get_result();
    if ($row_last_queue = $result_last_queue->fetch_assoc()) {
        $last_finish_time = max($last_finish_time, $row_last_queue['finish_at']); // Użyj najpóźniejszego czasu
    }
    $stmt_last_queue->close();

    foreach ($units_to_recruit as $recruit_data) {
        $unit_type_id = $recruit_data['unit_type_id'];
        $count = $recruit_data['count'];
        $training_time_per_unit = $recruit_data['training_time_per_unit']; // Czas na 1 jednostkę
        
        // Całkowity czas treningu dla tej partii (wszystkich jednostek tego samego typu w tym zadaniu)
        $total_training_time_for_batch = $training_time_per_unit * $count;
        
        // Czas rozpoczęcia tego zadania to czas zakończenia poprzedniego
        $started_at_this_task = $last_finish_time;
        // Czas zakończenia tego zadania
        $finish_at_this_task = $started_at_this_task + $total_training_time_for_batch;
        
        // Dodaj do kolejki rekrutacji
        $stmt_add_queue = $conn->prepare("
            INSERT INTO unit_queue (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type)
            VALUES (?, ?, ?, 0, ?, ?, ?)
        ");
        $stmt_add_queue->bind_param("iiiiss", $village_id, $unit_type_id, $count, $started_at_this_task, $finish_at_this_task, $queue_building_type);
        
        if (!$stmt_add_queue->execute()) {
            throw new Exception("Błąd podczas dodawania zadania rekrutacji do kolejki: " . $stmt_add_queue->error);
        }
        
        // Zaktualizuj czas zakończenia dla następnego zadania w pętli
        $last_finish_time = $finish_at_this_task;
        
        $recruited_queues[] = [ // Zbierz dane o dodanych zadaniach
            'queue_id' => $conn->insert_id,
            'unit_name' => $recruit_data['name'],
            'count' => $count,
            'finish_at' => $finish_at_this_task // Zwróć timestamp
        ];
    }
    
    $stmt_add_queue->close();

    // === Zatwierdź transakcję ===
    $conn->commit();
    // ==========================

    // === Przygotuj odpowiedź (AJAX lub przekierowanie) ===
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        // Pobierz zaktualizowane dane wioski po odjęciu zasobów i dodaniu populacji
        $updatedVillageInfo = $villageManager->getVillageInfo($village_id);
        AjaxResponse::success([
            'message' => 'Jednostki dodano do kolejki rekrutacji!',
            'recruited_queues' => $recruited_queues,
            'village_info' => [ // Zwróć zaktualizowane info o wiosce
                'wood' => $updatedVillageInfo['wood'],
                'clay' => $updatedVillageInfo['clay'],
                'iron' => $updatedVillageInfo['iron'],
                'population' => $updatedVillageInfo['population'],
                'warehouse_capacity' => $updatedVillageInfo['warehouse_capacity'],
                'farm_capacity' => $updatedVillageInfo['farm_capacity']
            ]
        ]);
    } else {
        // Przygotuj komunikat sukcesu i przekieruj
        $success_message = '<p class=\'success-message\'>Jednostki dodano do kolejki rekrutacji!</p>';
        // Możesz dodać więcej szczegółów o rekrutowanych jednostkach do komunikatu
        $_SESSION['game_message'] = $success_message;
        header("Location: game.php");
    }
    // ==================================================
    
} catch (Exception $e) {
    // === Wycofaj transakcję w przypadku błędu ===
    $conn->rollback();
    // =======================================

    // === Obsłuż wyjątek i zwróć błąd ===
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        AjaxResponse::error(
            'Błąd podczas rekrutacji jednostek: ' . $e->getMessage(),
            ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
            500 // HTTP status code
        );
    } else {
        $_SESSION['game_message'] = "<p class=\'error-message\'>Błąd podczas rekrutacji jednostek: " . htmlspecialchars($e->getMessage()) . "</p>";
        header("Location: game.php");
    }
    // ==================================
} finally {
    // Upewnij się, że połączenie jest zamknięte, jeśli nie używasz init.php w ten sposób
    // init.php powinno zarządzać połączeniem
    // $conn->close(); // Usunięto, bo init.php zarządza
}
?> 