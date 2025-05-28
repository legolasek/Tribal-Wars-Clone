<?php
require 'init.php';
require_once 'lib/UnitManager.php';
require_once 'lib/utils/AjaxResponse.php';
require_once 'lib/VillageManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Użytkownik nie jest zalogowany', null, 401);
}
$user_id = $_SESSION['user_id'];

try {
    // Pobierz wioskę gracza (może być potrzebne do walidacji)
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

    if (!$village_id) {
        // Jeśli nie podano village_id, spróbuj pobrać pierwszą wioskę użytkownika
        $villageManager = new VillageManager($conn);
        $village_data = $villageManager->getFirstVillage($user_id);
        if (!$village_data) {
            AjaxResponse::error('Nie znaleziono wioski dla użytkownika', null, 404);
        }
        $village_id = $village_data['id'];
    } else {
        // Jeśli podano village_id, sprawdź czy należy do użytkownika
        $villageManager = new VillageManager($conn);
        $village_data = $villageManager->getVillageInfo($village_id);
        if (!$village_data || $village_data['user_id'] != $user_id) {
            AjaxResponse::error('Brak uprawnień do tej wioski', null, 403);
        }
    }

    $unitManager = new UnitManager($conn);
    // Pobierz kolejki rekrutacji (opcjonalnie dla konkretnego typu budynku, np. Koszar)
    // Na razie pobieramy wszystkie dla wioski, frontend może filtrować lub można dodać parametr building_type do GET
    $queue = $unitManager->getRecruitmentQueues($village_id);

    // Przygotuj dane do zwrócenia w JSON - dodaj potrzebne pola, np. timestampy
    $queue_data = [];
    foreach ($queue as $item) {
        $queue_data[] = [
            'id' => (int)$item['id'],
            'unit_type_id' => (int)$item['unit_type_id'],
            'count' => (int)$item['count'],
            'count_finished' => (int)$item['count_finished'],
            'started_at' => strtotime($item['started_at']), // Timestamp
            'finish_at' => strtotime($item['finish_at']), // Timestamp
            'building_type' => $item['building_type'],
            'unit_name_pl' => $item['name_pl'],
            'unit_internal_name' => $item['internal_name']
            // Można dodać icon_url jeśli UnitManager to zwróci
        ];
    }

    // Zwróć dane w formacie JSON
    AjaxResponse::success([
        'village_id' => $village_id,
        'queue' => $queue_data,
        'current_server_time' => time() // Dodaj aktualny czas serwera (timestamp)
    ]);

} catch (Exception $e) {
    // Obsłuż wyjątek i zwróć błąd w formacie JSON
    AjaxResponse::error(
        'Wystąpił błąd podczas pobierania kolejki rekrutacji: ' . $e->getMessage(),
        ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
        500 // HTTP status code
    );
}

// Połączenie z bazą danych jest zarządzane przez init.php
// $conn->close(); // Usunięto
?> 