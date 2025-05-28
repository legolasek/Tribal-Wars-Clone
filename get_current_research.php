<?php
require 'init.php';
require_once 'lib/ResearchManager.php';

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

$researchManager = new ResearchManager($conn);
$researches = $researchManager->getVillageResearchLevels($village_id);

if (empty(array_filter($researches))) {
    echo '<div class="research-empty">Brak przeprowadzonych badań (poziom > 0).</div>';
    exit;
}
echo '<div class="current-research-list">';

// Mapowanie ikon badań (przykład - trzeba będzie dodać rzeczywiste ikony)
$research_icons = [
    'axe' => 'unit_axe.png', // Przykład: ikona topora dla badania topornika
    'spear' => 'unit_spear.png', // Przykład: ikona piki dla badania pikiniera
    // Dodaj mapowania dla innych badań
];

// Pobierz nazwy badań z ResearchManager (jeśli nie są zawarte w getVillageResearchLevels)
// Alternatywnie, zmodyfikuj getVillageResearchLevels aby zwracało pełne dane
$all_research_types = $researchManager->getAllResearchTypes(); // Założenie, że taka metoda istnieje
$research_names = [];
foreach ($all_research_types as $type) {
    $research_names[$type['internal_name']] = $type['name_pl'];
    // Tutaj można też pobrać ikonę, jeśli jest w tabeli research_types
    $research_icons[$type['internal_name']] = $type['icon'] ?? ''; // Założenie, że jest kolumna 'icon'
}

// Filtruj badania tylko te na poziomie > 0
$active_researches = array_filter($researches, function($level) { return $level > 0; });

if (empty($active_researches)) {
     echo '<div class="research-empty">Brak przeprowadzonych badań (poziom > 0).</div>';
     exit;
}

foreach ($active_researches as $internal_name => $level) {
    $icon_url = isset($research_icons[$internal_name]) && !empty($research_icons[$internal_name]) ? 'img/research/' . $research_icons[$internal_name] : ''; // Używamy internal_name do mapowania, zmieniono ścieżkę na img/research/
    $research_name = $research_names[$internal_name] ?? $internal_name;

    echo '<div class="research-item">';
    if ($icon_url) {
        echo '<img src="' . htmlspecialchars($icon_url) . '" class="research-icon" alt="' . htmlspecialchars($research_name) . '">';
    }
    echo '<span class="research-name">' . htmlspecialchars($research_name) . '</span>';
    echo '<span class="research-level">poziom <b>' . (int)$level . '</b></span>';
    echo '</div>';
}
echo '</div>'; 