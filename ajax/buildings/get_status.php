<?php
/**
 * AJAX - Pobieranie aktualnego statusu budynków
 * Zwraca aktualny stan kolejki budowy i inne informacje o budynkach w formacie JSON
 */
require_once '../../init.php';
require_once '../../lib/utils/AjaxResponse.php';

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Użytkownik nie jest zalogowany', null, 401);
}

try {
    // Pobierz ID wioski
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;
    
    // Jeśli nie podano ID wioski, pobierz pierwszą wioskę użytkownika
    if (!$village_id) {
        require_once '../../lib/VillageManager.php';
        $villageManager = new VillageManager($conn);
        $village_id = $villageManager->getFirstVillage($_SESSION['user_id']);
        
        if (!$village_id) {
            AjaxResponse::error('Nie znaleziono wioski', null, 404);
        }
    }
    
    // Sprawdź, czy wioska należy do zalogowanego użytkownika
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village_owner = $result->fetch_assoc();
    $stmt->close();
    
    if (!$village_owner || $village_owner['user_id'] != $_SESSION['user_id']) {
        AjaxResponse::error('Brak uprawnień do tej wioski', null, 403);
    }
    
    // Pobierz dane o kolejce budowy
    $building_queue = [];
    $completed_count = 0;
    
    // Sprawdź, czy są zakończone budowy
    $current_time = date('Y-m-d H:i:s');
    $stmt_completed = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM building_queue 
        WHERE village_id = ? AND ends_at <= ?
    ");
    $stmt_completed->bind_param("is", $village_id, $current_time);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $completed_row = $result_completed->fetch_assoc();
    $completed_count = $completed_row['count'];
    $stmt_completed->close();
    
    // Pobierz aktywne elementy w kolejce
    $stmt_queue = $conn->prepare("
        SELECT bq.id, bq.building_type_id, bq.level_after, bq.starts_at, bq.ends_at, 
               bt.name_pl as building_name, bt.internal_name
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        WHERE bq.village_id = ? AND bq.ends_at > ?
        ORDER BY bq.ends_at ASC
    ");
    $stmt_queue->bind_param("is", $village_id, $current_time);
    $stmt_queue->execute();
    $result_queue = $stmt_queue->get_result();
    
    while ($row = $result_queue->fetch_assoc()) {
        // Oblicz pozostały czas i procent postępu
        $end_time = strtotime($row['ends_at']);
        $start_time = strtotime($row['starts_at']);
        $current_time_stamp = time();
        
        $total_duration = $end_time - $start_time;
        $elapsed_time = $current_time_stamp - $start_time;
        $remaining_time = $end_time - $current_time_stamp;
        
        $progress_percent = min(100, max(0, ($elapsed_time / $total_duration) * 100));
        
        $building_queue[] = [
            'id' => $row['id'],
            'building_type_id' => $row['building_type_id'],
            'building_name' => $row['building_name'],
            'internal_name' => $row['internal_name'],
            'level_after' => $row['level_after'],
            'ends_at' => $row['ends_at'],
            'remaining_time' => $remaining_time,
            'remaining_time_formatted' => formatTime($remaining_time),
            'progress_percent' => $progress_percent
        ];
    }
    $stmt_queue->close();
    
    // Zwróć dane w formacie JSON
    AjaxResponse::success([
        'building_queue' => $building_queue,
        'completed_count' => $completed_count,
        'current_server_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Obsłuż wyjątek i zwróć błąd
    AjaxResponse::handleException($e);
} 