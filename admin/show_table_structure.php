<?php
require_once '../config/config.php';
require_once '../lib/Database.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();

echo "<h2>Struktura tabeli village_buildings</h2>";
$result = $conn->query('DESCRIBE village_buildings');

if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h2>Skrypt SQL do dodania brakujących kolumn</h2>";
echo "<pre>";
echo "ALTER TABLE village_buildings ADD COLUMN upgrade_level_to INT DEFAULT NULL;
ALTER TABLE village_buildings ADD COLUMN upgrade_ends_at DATETIME DEFAULT NULL;";
echo "</pre>";

echo "<h2>Wykonaj skrypt SQL</h2>";
echo "<form method='post'>";
echo "<input type='submit' name='execute_sql' value='Dodaj brakujące kolumny'>";
echo "</form>";

// Execute the SQL if the form is submitted
if (isset($_POST['execute_sql'])) {
    // Add upgrade_level_to column if it doesn't exist
    $checkColumn = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_level_to'");
    if ($checkColumn->num_rows == 0) {
        if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_level_to INT DEFAULT NULL")) {
            echo "<p style='color: green;'>Kolumna upgrade_level_to dodana pomyślnie.</p>";
        } else {
            echo "<p style='color: red;'>Błąd podczas dodawania kolumny upgrade_level_to: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>Kolumna upgrade_level_to już istnieje.</p>";
    }
    
    // Add upgrade_ends_at column if it doesn't exist
    $checkColumn = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_ends_at'");
    if ($checkColumn->num_rows == 0) {
        if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_ends_at DATETIME DEFAULT NULL")) {
            echo "<p style='color: green;'>Kolumna upgrade_ends_at dodana pomyślnie.</p>";
        } else {
            echo "<p style='color: red;'>Błąd podczas dodawania kolumny upgrade_ends_at: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>Kolumna upgrade_ends_at już istnieje.</p>";
    }
}

// Show table structure again after changes
if (isset($_POST['execute_sql'])) {
    echo "<h2>Struktura tabeli village_buildings po zmianach</h2>";
    $result = $conn->query('DESCRIBE village_buildings');
    
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
}

$db->closeConnection();
?> 