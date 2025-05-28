<?php
require_once 'config/config.php';
require_once 'lib/Database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Weryfikacja tabel bazy danych</h1>";

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// Lista tabel które powinny istnieć
$expected_tables = [
    'users',
    'villages',
    'building_types',
    'village_buildings',
    'building_queue',
    'unit_types',
    'village_units',
    'unit_queue',
    'research_types',
    'village_research',
    'research_queue'
];

// Sprawdź, czy tabele istnieją
echo "<h2>Weryfikacja istnienia tabel:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Tabela</th><th>Status</th><th>Liczba rekordów</th></tr>";

foreach ($expected_tables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        // Tabela istnieje - policz liczbę wierszy
        $count_query = "SELECT COUNT(*) as count FROM `$table`";
        $count_result = $conn->query($count_query);
        $count = $count_result->fetch_assoc()['count'];
        
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color:green;'>Istnieje</td>";
        echo "<td>$count</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color:red;'>Nie istnieje!</td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}

echo "</table>";

// Sprawdź strukturę tabeli unit_types
echo "<h2>Weryfikacja struktury tabeli unit_types:</h2>";
$unit_types_columns = $conn->query("SHOW COLUMNS FROM unit_types");

if ($unit_types_columns) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Nazwa kolumny</th><th>Typ</th><th>NULL</th><th>Klucz</th><th>Domyślnie</th><th>Extra</th></tr>";
    
    while ($column = $unit_types_columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red;'>Nie można pobrać struktury tabeli unit_types: " . $conn->error . "</p>";
}

// Sprawdź strukturę tabeli research_queue
echo "<h2>Weryfikacja struktury tabeli research_queue:</h2>";
$research_queue_columns = $conn->query("SHOW COLUMNS FROM research_queue");

if ($research_queue_columns) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Nazwa kolumny</th><th>Typ</th><th>NULL</th><th>Klucz</th><th>Domyślnie</th><th>Extra</th></tr>";
    
    while ($column = $research_queue_columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red;'>Nie można pobrać struktury tabeli research_queue: " . $conn->error . "</p>";
}

$database->closeConnection();

echo "<h2>Weryfikacja zakończona.</h2>";
echo "<p><a href='install.php'>Powrót do instalatora</a></p>";
?> 