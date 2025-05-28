<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Instalacja tabel jednostek</h1>";

// Ładowanie konfiguracji bazy danych
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

try {
    // Połączenie z bazą danych
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }
    
    echo "<p>Połączono z bazą danych.</p>";
    
    // Pobierz i wykonaj skrypt SQL
    $sql = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'sql_create_units_table.sql');
    
    if (!$sql) {
        die("Nie udało się wczytać pliku SQL.");
    }
    
    echo "<p>Wczytano plik SQL.</p>";
    
    // Wykonaj poszczególne zapytania
    $queries = explode(';', $sql);
    $success_count = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        if ($conn->query($query)) {
            $success_count++;
            echo "<p style='color:green'>Zapytanie wykonane poprawnie: " . htmlspecialchars(substr($query, 0, 80)) . "...</p>";
        } else {
            echo "<p style='color:red'>Błąd zapytania: " . htmlspecialchars(substr($query, 0, 80)) . "...</p>";
            echo "<p>Błąd: " . $conn->error . "</p>";
        }
    }
    
    echo "<h2>Zakończono instalację</h2>";
    echo "<p>Pomyślnie wykonano $success_count zapytań.</p>";
    echo "<p><a href='game.php'>Powrót do gry</a></p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red'>Wystąpił błąd: " . $e->getMessage() . "</p>";
}
?> 