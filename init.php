<?php
// Rozpocznij sesję tylko jeśli nie jest już aktywna
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Załaduj konfigurację
require_once 'config/config.php';

// Załaduj Autoloader
require_once 'lib/Autoloader.php';

// Załaduj podstawowe funkcje (zawiera getCSRFToken i validateCSRF)
require_once 'lib/functions.php';

// Inicjalizuj obsługę błędów
require_once 'lib/utils/ErrorHandler.php';
ErrorHandler::initialize();

// Usunięto globalną sanityzację GET i POST. 
// Walidacja i sanityzacja danych wejściowych powinna odbywać się w miejscu ich użycia.
// $_GET = sanitizeInput($_GET);
// $_POST = sanitizeInput($_POST);

// Initialize CSRF token (generowane w getCSRFToken z functions.php)
// getCSRFToken(); // Wywoływane w header.php, aby token był dostępny w META tagu

// Explicitly include Database class
require_once 'lib/Database.php';

// Connect to database
$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// Determine current world from session or default
if (!isset($_SESSION['world_id'])) {
    $_SESSION['world_id'] = INITIAL_WORLD_ID;
}
define('CURRENT_WORLD_ID', (int)$_SESSION['world_id']);

// Inicjalizacja folderu logs, jeśli nie istnieje
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}

// Sprawdzenie, czy użytkownik jest zalogowany i pobranie jego danych
$user = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Upewnij się, że Autoloader działa i klasa UserManager jest dostępna
    // require_once 'lib/managers/UserManager.php'; 
    // $userManager = new UserManager($conn);
    // $user = $userManager->getUserById($user_id);

    // Tymczasowo używamy bezpośredniego zapytania z prepared statement
    $stmt = $conn->prepare("SELECT id, username, is_admin, is_banned FROM users WHERE id = ? LIMIT 1");
     if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
     } else {
         error_log("Database prepare failed in init.php for fetching user: " . $conn->error);
         // Obsługa błędu: wylogowanie lub błąd krytyczny w zależności od polityki bezpieczeństwa
         $user = null; // Ustaw użytkownika na null w przypadku błędu prepare
     }

    // Jeśli użytkownik z sesji nie istnieje lub jest zbanowany, wyloguj go
    if (!$user || $user['is_banned']) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // Zaktualizuj sesję na wypadek zmian w bazie
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = $user['is_admin'];

    // Sprawdź i przetwarzaj ukończone zadania dla aktywnej wioski użytkownika
    if (isset($_SESSION['village_id'])) {
        // Upewnij się, że VillageManager jest załadowany (Autoloader powinien to zrobić)
        // require_once 'lib/VillageManager.php';
        // $villageManager = new VillageManager($conn);
        // $villageManager->processCompletedTasksForVillage($_SESSION['village_id']);
        // Zostawiamy to do momentu implementacji ResourceManager i pełnego przetwarzania w game.php
    }

} else {
    // Jeśli użytkownik nie jest zalogowany, przekieruj na stronę logowania, chyba że to strona publiczna
    $public_pages = ['index.php', 'auth/login.php', 'auth/register.php', 'install.php', 'admin/admin_login.php', 'admin/db_verify.php', 'favicon.ico', 'css/', 'js/', 'img/', 'ajax/']; // Dodaj inne publiczne ścieżki (katalogi i ajax)
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $current_path = trim($current_path, '/');
    
    $is_public = false;
    
    // Sprawdź, czy ścieżka pasuje do publicznych plików/katalogów
    foreach ($public_pages as $public_item) {
        $public_item_trimmed = trim($public_item, '/');
        // Jeśli ścieżka URL jest dokładnie publicznym plikiem LUB zaczyna się od publicznego katalogu/
        if ($current_path === $public_item_trimmed || (strpos($current_path, $public_item_trimmed . '/') === 0 && $public_item_trimmed !== '')) {
            $is_public = true;
            break;
        }
    }
    

    if (!$is_public) {
        // Jeśli nie jest to publiczna strona i nie jest to żądanie AJAX (które może być kierowane do publicznego skryptu w ajax/)
        // to przekieruj na index.php (stronę logowania/główną)
        if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
             header("Location: index.php");
             exit();
        }
         // Jeśli to żądanie AJAX do niepublicznego zasobu, ale użytkownik nie jest zalogowany, również zakończ
         // (CSRF check powinien to też wychwycić, ale dodatkowe zabezpieczenie)
         // Można zwrócić błąd JSON lub status 401/403
         header('HTTP/1.1 401 Unauthorized');
         header('Content-Type: application/json');
         echo json_encode(['error' => 'Wymagane logowanie.']);
         exit();
    }
}

// Ustawienie aktywnej wioski (jeśli nie ustawiono i użytkownik jest zalogowany)
// Przeniesiono do game.php lub podobnych, gdzie faktycznie potrzebujemy village_id
/*
if (isset($user) && !isset($_SESSION['village_id'])) {
    // Upewnij się, że VillageManager jest załadowany (Autoloader powinien to zrobić)
    $villageManager = new VillageManager($conn);
    $firstVillageId = $villageManager->getFirstVillage($user['id']);
    if ($firstVillageId) {
        $_SESSION['village_id'] = $firstVillageId;
    }
}
*/

// CSRF protection for POST requests
// Generowanie tokenu per sesja (jeśli jeszcze nie istnieje) - Robione w getCSRFToken w functions.php, wywoływane w header.php
// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }

// Walidacja CSRF dla żądań POST - Robione przez validateCSRF() z functions.php
// validateCSRF(); // Ta funkcja jest teraz w functions.php

// Usunięto funkcje obliczeniowe - powinny być w dedykowanych klasach (BuildingManager, ResourceManager)
// function calculateHourlyProduction(...) { ... }
// function calculateWarehouseCapacity(...) { ... }

?> 