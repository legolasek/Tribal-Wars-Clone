<?php
require 'init.php';
validateCSRF();

// ini_set('display_errors', 1); // Remove development-specific settings
// ini_set('display_startup_errors', 1); // Remove development-specific settings
// error_reporting(E_ALL); // Remove development-specific settings

// Remove duplicate session_start() and header()
// session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/lib/managers/VillageManager.php';

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Użytkownik niezalogowany.', 'redirect' => 'login.php']);
    exit();
}

// Sprawdź, czy zapytanie jest typu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowa metoda żądania.']);
    exit();
}

// Sprawdź, czy podano nową nazwę wioski
if (!isset($_POST['new_village_name']) || empty($_POST['new_village_name'])) {
    echo json_encode(['success' => false, 'error' => 'Nie podano nazwy wioski.']);
    exit();
}

// Wyodrębnij dane z żądania
$new_village_name = trim($_POST['new_village_name']);
$village_id = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;
$user_id = $_SESSION['user_id'];

// Walidacja nowej nazwy wioski (można też dodać do VillageManager)
if (strlen($new_village_name) < 3) {
    echo json_encode(['success' => false, 'error' => 'Nazwa wioski musi zawierać co najmniej 3 znaki.']);
    exit();
}

if (strlen($new_village_name) > 50) {
    echo json_encode(['success' => false, 'error' => 'Nazwa wioski może zawierać maksymalnie 50 znaków.']);
    exit();
}

// Sprawdź, czy nazwa zawiera tylko dozwolone znaki
// Użyj tej samej regex co w lib/functions.php -> isValidVillageName
if (!preg_match('/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ0-9\s\-\.\_]+$/u', $new_village_name)) { // Added \. to allowed chars based on isValidVillageName
     echo json_encode(['success' => false, 'error' => 'Nazwa wioski zawiera niedozwolone znaki.']);
     exit();
}

// Remove manual DB connection
// require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
// require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Database.php';

try {
    // Use global $conn from init.php
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Nie udało się połączyć z bazą danych.']);
        exit();
    }

    // Jeśli nie podano konkretnego ID wioski, znajdź domyślną wioskę użytkownika
    // Użyj VillageManager do pobrania pierwszej wioski, jeśli village_id <= 0
    if ($village_id <= 0) {
        $villageManager = new VillageManager($conn);
        $firstVillage = $villageManager->getFirstVillage($user_id);
        if ($firstVillage && isset($firstVillage['id'])) {
            $village_id = $firstVillage['id'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Nie znaleziono wioski dla użytkownika.']);
            exit(); // Exit directly, no manual connection to close
        }
    }

    // Sprawdź, czy wioska należy do zalogowanego użytkownika - VillageManager handles this in renameVillage
    // No need for this explicit check here
    /*
    $stmt = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $village_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village = $result->fetch_assoc();
    $stmt->close();

    if (!$village) {
        echo json_encode(['success' => false, 'error' => 'Nie masz uprawnień do zmiany nazwy tej wioski.']);
        // $database->closeConnection(); // Remove
        exit();
    }
    */

    // Use VillageManager to rename the village
    $villageManager = $villageManager ?? new VillageManager($conn); // Instantiate if not already
    $renameResult = $villageManager->renameVillage($village_id, $user_id, $new_village_name);

    if ($renameResult['success']) {
        // Aktualizacja się powiodła
        echo json_encode(['success' => true, 'message' => $renameResult['message']]);

        // Zapisz nową nazwę wioski w sesji, jeśli jest to potrzebne (np. dla wyświetlania w nagłówku)
        // Ta logika może być lepiej zarządzana gdzie indziej, ale na razie zostawiamy
        $_SESSION['village_name'] = $new_village_name;
    } else {
        // Aktualizacja się nie powiodła
        echo json_encode(['success' => false, 'error' => $renameResult['message']]);
    }

    // Remove manual DB connection close
    // $database->closeConnection();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Wystąpił błąd: ' . $e->getMessage()]);
}

// No need for $conn->close(); - handled by init.php if persistent, or closes automatically
?> 