<?php
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/managers/BuildingManager.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html lang='pl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Systemu Budynków</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #5a3921; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Test Systemu Budynków - Diagnostyka</h1>";

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();
$buildingManager = new BuildingManager($conn);

echo "<section>
    <h2>1. Struktura tabeli village_buildings</h2>";

// Verify table structure
$result = $conn->query('DESCRIBE village_buildings');
if ($result) {
    echo "<table>
        <tr><th>Pole</th><th>Typ</th><th>Null</th><th>Klucz</th><th>Domyślnie</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $row['Field'] . "</td>
            <td>" . $row['Type'] . "</td>
            <td>" . $row['Null'] . "</td>
            <td>" . $row['Key'] . "</td>
            <td>" . $row['Default'] . "</td>
            <td>" . $row['Extra'] . "</td>
        </tr>";
    }
    
    echo "</table>";
    
    // Check if the required columns exist
    $result->data_seek(0);
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[$row['Field']] = true;
    }
    
    echo "<p>Wymagane kolumny:</p>
    <ul>";
    $required_columns = ['id', 'village_id', 'building_type_id', 'level', 'upgrade_level_to', 'upgrade_ends_at'];
    foreach ($required_columns as $column) {
        if (isset($fields[$column])) {
            echo "<li class='success'>$column - OK</li>";
        } else {
            echo "<li class='error'>$column - BRAK!</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p class='error'>Błąd: " . $conn->error . "</p>";
}

echo "</section>";

echo "<section>
    <h2>2. Typy budynków w bazie danych</h2>";

// Check building types
$result = $conn->query('SELECT * FROM building_types ORDER BY id');
if ($result) {
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Internal Name</th>
            <th>Nazwa (PL)</th>
            <th>Max Level</th>
            <th>Czas budowy</th>
            <th>Mnożnik czasu</th>
            <th>Drewno (bazowo)</th>
            <th>Glina (bazowo)</th>
            <th>Żelazo (bazowo)</th>
            <th>Mnożnik kosztu</th>
        </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td>" . $row['internal_name'] . "</td>
            <td>" . $row['name_pl'] . "</td>
            <td>" . $row['max_level'] . "</td>
            <td>" . $row['base_build_time_initial'] . "</td>
            <td>" . $row['build_time_factor'] . "</td>
            <td>" . $row['cost_wood_initial'] . "</td>
            <td>" . $row['cost_clay_initial'] . "</td>
            <td>" . $row['cost_iron_initial'] . "</td>
            <td>" . $row['cost_factor'] . "</td>
        </tr>";
    }
    
    echo "</table>";
    echo "<p>Znaleziono " . $result->num_rows . " typów budynków w bazie danych.</p>";
} else {
    echo "<p class='error'>Błąd: " . $conn->error . "</p>";
}

echo "</section>";

echo "<section>
    <h2>3. Weryfikacja funkcji BuildingManager</h2>";

// Test BuildingManager functions
echo "<h3>3.1. Obliczanie kosztów rozbudowy</h3>";
$test_buildings = ['main_building', 'sawmill', 'clay_pit', 'iron_mine', 'warehouse'];
$test_levels = [1, 2, 3, 5, 10];

echo "<table>
    <tr>
        <th>Budynek</th>
        <th>Do poziomu</th>
        <th>Drewno</th>
        <th>Glina</th>
        <th>Żelazo</th>
    </tr>";

foreach ($test_buildings as $building) {
    foreach ($test_levels as $level) {
        $cost = $buildingManager->getBuildingUpgradeCost($building, $level);
        if ($cost) {
            echo "<tr>
                <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
                <td>" . $level . "</td>
                <td>" . $cost['wood'] . "</td>
                <td>" . $cost['clay'] . "</td>
                <td>" . $cost['iron'] . "</td>
            </tr>";
        } else {
            echo "<tr>
                <td>" . $building . "</td>
                <td>" . $level . "</td>
                <td colspan='3' class='error'>Błąd obliczenia kosztu</td>
            </tr>";
        }
    }
}

echo "</table>";

echo "<h3>3.2. Obliczanie czasu rozbudowy (z różnymi poziomami ratusza)</h3>";
$test_main_building_levels = [1, 5, 10, 20];

echo "<table>
    <tr>
        <th>Budynek</th>
        <th>Do poziomu</th>
        <th>Poziom Ratusza</th>
        <th>Czas budowy (sekundy)</th>
        <th>Czas budowy (format)</th>
    </tr>";

foreach ($test_buildings as $building) {
    foreach ($test_levels as $level) {
        foreach ($test_main_building_levels as $main_level) {
            $time = $buildingManager->getBuildingUpgradeTime($building, $level, $main_level);
            if ($time !== null) {
                echo "<tr>
                    <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
                    <td>" . $level . "</td>
                    <td>" . $main_level . "</td>
                    <td>" . $time . "</td>
                    <td>" . gmdate("H:i:s", $time) . "</td>
                </tr>";
            } else {
                echo "<tr>
                    <td>" . $building . "</td>
                    <td>" . $level . "</td>
                    <td>" . $main_level . "</td>
                    <td colspan='2' class='error'>Błąd obliczenia czasu</td>
                </tr>";
            }
        }
    }
}

echo "</table>";

echo "<h3>3.3. Obliczanie produkcji surowców</h3>";
$production_buildings = ['sawmill', 'clay_pit', 'iron_mine'];
$production_test_levels = [1, 5, 10, 15, 20, 30];

echo "<table>
    <tr>
        <th>Budynek</th>
        <th>Poziom</th>
        <th>Produkcja na godzinę</th>
        <th>Produkcja na dzień</th>
    </tr>";

foreach ($production_buildings as $building) {
    foreach ($production_test_levels as $level) {
        $production = $buildingManager->getHourlyProduction($building, $level);
        echo "<tr>
            <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
            <td>" . $level . "</td>
            <td>" . $production . "</td>
            <td>" . ($production * 24) . "</td>
        </tr>";
    }
}

echo "</table>";

echo "</section>";

echo "<section>
    <h2>4. Informacje debugowania</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>MySQL Client Info: " . $conn->client_info . "</p>";
echo "<p>MySQL Server Info: " . $conn->server_info . "</p>";
echo "</section>";

echo "<p><a href='../game/game.php'>Powrót do gry</a></p>";
echo "</body></html>";

$database->closeConnection();
?> 