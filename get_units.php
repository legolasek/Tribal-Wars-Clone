<?php
require 'init.php';
require_once 'lib/UnitManager.php';

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

$unitManager = new UnitManager($conn);
$units = $unitManager->getVillageUnits($village_id);

// Mapowanie ikon jednostek
$unit_icons = [
    'militia' => 'unit_militia.png',
    'spear' => 'unit_spear.png',
    'sword' => 'unit_sword.png',
    'axe' => 'unit_axe.png',
    'archer' => 'unit_archer.png',
    'scout' => 'unit_scout.png',
    'light' => 'unit_light.png',
    'heavy' => 'unit_heavy.png',
    'ram' => 'unit_ram.png',
    'catapult' => 'unit_catapult.png',
    'knight' => 'unit_knight.png',
    'snob' => 'unit_snob.png',
];

if (!$units) {
    echo '<div>Brak jednostek w wiosce.</div>';
    exit;
}
echo '<div class="current-units-table">';
foreach ($units as $unit => $count) {
    $icon = isset($unit_icons[$unit]) ? 'img/unit/' . $unit_icons[$unit] : '';
    echo '<div class="unit-info">';
    if ($icon) {
        echo '<img src="' . $icon . '" class="unit-icon" alt="' . htmlspecialchars($unit) . '">';
    }
    echo '<div>' . htmlspecialchars($unitManager->getUnitNamePL($unit)) . ': <b>' . (int)$count . '</b></div>';
    echo '</div>';
}
echo '</div>'; 