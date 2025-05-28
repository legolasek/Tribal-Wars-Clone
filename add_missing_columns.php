<?php
require_once 'config/config.php';
require_once 'lib/Database.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Dodawanie brakujących kolumn</h1>";

$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();

// Add upgrade_level_to column
$checkColumn1 = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_level_to'");
if ($checkColumn1->num_rows == 0) {
    if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_level_to INT DEFAULT NULL")) {
        echo "<p style='color: green;'>Kolumna upgrade_level_to dodana pomyślnie.</p>";
    } else {
        echo "<p style='color: red;'>Błąd podczas dodawania kolumny upgrade_level_to: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Kolumna upgrade_level_to już istnieje.</p>";
}

// Add upgrade_ends_at column
$checkColumn2 = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_ends_at'");
if ($checkColumn2->num_rows == 0) {
    if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_ends_at DATETIME DEFAULT NULL")) {
        echo "<p style='color: green;'>Kolumna upgrade_ends_at dodana pomyślnie.</p>";
    } else {
        echo "<p style='color: red;'>Błąd podczas dodawania kolumny upgrade_ends_at: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Kolumna upgrade_ends_at już istnieje.</p>";
}

// Show table structure after changes
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
} else {
    echo "Error: " . $conn->error;
}

echo "<p><a href='game.php'>Przejdź do strony głównej gry</a></p>";

$db->closeConnection();
?> 