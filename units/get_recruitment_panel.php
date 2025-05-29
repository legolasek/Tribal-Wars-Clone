<?php
require '../init.php';
require_once '../lib/managers/UnitManager.php';
require_once '../lib/managers/BuildingManager.php'; // Need BuildingManager to get building level

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Brak dostępu</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Pobierz wioskę gracza
$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$village = $res->fetch_assoc();
$stmt->close();
if (!$village) {
    echo '<div class="error">Brak wioski</div>';
    exit;
}
$village_id = $village['id'];

// Pobierz typ budynku z parametru żądania
$building_type = $_GET['building_type'] ?? '';

if (empty($building_type)) {
    echo '<div class="error">Nie podano typu budynku.</div>';
    exit;
}

// Pobierz poziom budynku w wiosce
$buildingManager = new BuildingManager($conn);
$building_level = 0;
$stmt_level = $conn->prepare("
    SELECT vb.level 
    FROM village_buildings vb
    JOIN building_types bt ON vb.building_type_id = bt.id
    WHERE vb.village_id = ? AND bt.internal_name = ? LIMIT 1
");
$stmt_level->bind_param("is", $village_id, $building_type);
$stmt_level->execute();
$level_result = $stmt_level->get_result()->fetch_assoc();
$stmt_level->close();

if ($level_result) {
    $building_level = (int)$level_result['level'];
}

// Jeśli budynek nie istnieje lub ma poziom 0, jednostki nie są dostępne
if ($building_level == 0) {
     echo '<div class="info">Ten budynek nie istnieje w Twojej wiosce lub jego poziom to 0. Rekrutacja jednostek jest niedostępna.</div>';
     exit;
}

$unitManager = new UnitManager($conn);
// Poprawione wywołanie metody
$availableUnits = $unitManager->getAvailableUnitsByBuilding($building_type, $building_level);

if (empty($availableUnits)) {
    echo '<div>Brak dostępnych jednostek do rekrutacji w tym budynku przy obecnym poziomie.</div>';
    exit;
}
echo '<form method="post" id="recruitment-form">';
echo '<input type="hidden" name="building_type" value="' . htmlspecialchars($building_type) . '">'; // Dodaj typ budynku do formularza
foreach ($availableUnits as $unit_data) { // Zmieniona pętla
    $icon = isset($unit_data['icon']) ? $unit_data['icon'] : '';
    $unit_internal_name = $unit_data['internal_name'];
    $unit_name = $unit_data['name_pl'];
    $unit_id = $unit_data['id'];
    $cost_wood = $unit_data['cost_wood'];
    $cost_clay = $unit_data['cost_clay'];
    $cost_iron = $unit_data['cost_iron'];

    echo '<div class="unit-info">';
    if ($icon) {
        echo '<img src="../img/unit/' . htmlspecialchars($icon) . '" class="unit-icon" alt="' . htmlspecialchars($unit_name) . '">';
    }
    echo '<label>' . htmlspecialchars($unit_name) . ':</label> ';
    // Użyj unit_id dla inputa, internal_name dla data attribute
    echo '<input type="number" name="units[' . $unit_id . ']" data-unit-internal-name="' . htmlspecialchars($unit_internal_name) . '" min="0" value="0">';
    // Zaktualizowany wyświetlacz kosztów
    echo ' <span class="resource-cost">Koszt: <img src="../img/wood.png" title="Drewno" alt="Drewno" class="resource-icon-small">' . $cost_wood . ' <img src="../img/stone.png" title="Glina" alt="Glina" class="resource-icon-small">' . $cost_clay . ' <img src="../img/iron.png" title="Żelazo" alt="Żelazo" class="resource-icon-small">' . $cost_iron . '</span>';
    echo '</div>';
}
echo '<button type="submit" class="btn btn-primary">Rekrutuj wybrane jednostki</button>';
echo '</form>'; 