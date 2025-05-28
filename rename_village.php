<?php
require 'init.php';
validateCSRF();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Użytkownik niezalogowany.']);
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

// Walidacja nowej nazwy wioski
if (strlen($new_village_name) < 3) {
    echo json_encode(['success' => false, 'error' => 'Nazwa wioski musi zawierać co najmniej 3 znaki.']);
    exit();
}

if (strlen($new_village_name) > 50) {
    echo json_encode(['success' => false, 'error' => 'Nazwa wioski może zawierać maksymalnie 50 znaków.']);
    exit();
}

// Sprawdź, czy nazwa zawiera tylko dozwolone znaki
if (!preg_match('/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ0-9\s\-\_\.]+$/u', $new_village_name)) {
    echo json_encode(['success' => false, 'error' => 'Nazwa wioski zawiera niedozwolone znaki.']);
    exit();
}

// Połącz się z bazą danych
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Database.php';

try {
    // Utwórz połączenie z bazą danych
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Nie udało się połączyć z bazą danych.']);
        exit();
    }

    // Jeśli nie podano konkretnego ID wioski, znajdź domyślną wioskę użytkownika
    if ($village_id <= 0) {
        $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village_data = $result->fetch_assoc();
        $stmt->close();

        if ($village_data && isset($village_data['id'])) {
            $village_id = $village_data['id'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Nie znaleziono wioski dla użytkownika.']);
            $database->closeConnection();
            exit();
        }
    }

    // Sprawdź, czy wioska należy do zalogowanego użytkownika
    $stmt = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $village_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village = $result->fetch_assoc();
    $stmt->close();

    if (!$village) {
        echo json_encode(['success' => false, 'error' => 'Nie masz uprawnień do zmiany nazwy tej wioski.']);
        $database->closeConnection();
        exit();
    }

    // Aktualizuj nazwę wioski w bazie danych
    $stmt = $conn->prepare("UPDATE villages SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $new_village_name, $village_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        // Aktualizacja się powiodła
        echo json_encode(['success' => true, 'message' => 'Nazwa wioski została zmieniona na: ' . $new_village_name]);

        // Zapisz nową nazwę wioski w sesji, jeśli jest to potrzebne
        $_SESSION['village_name'] = $new_village_name;
    } else {
        // Aktualizacja się nie powiodła
        echo json_encode(['success' => false, 'error' => 'Nie udało się zmienić nazwy wioski.']);
    }

    // Zamknij połączenie z bazą danych
    $database->closeConnection();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Wystąpił błąd: ' . $e->getMessage()]);
}
?> 