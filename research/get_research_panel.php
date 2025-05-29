<?php
require '../init.php';
require_once '../lib/managers/ResearchManager.php';
require_once '../lib/managers/BuildingManager.php'; // Potrzebne do sprawdzenia wymagań i obliczeń czasu
require_once '../lib/managers/ResourceManager.php'; // Potrzebne do wyświetlenia aktualnych zasobów

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Brak dostępu. Zaloguj się ponownie.</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Pobierz wioskę gracza
$stmt = $conn->prepare("SELECT id, wood, clay, iron FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$village = $res->fetch_assoc();
$stmt->close();
if (!$village) {
    echo '<div class="error">Brak wioski.</div>';
    exit;
}
$village_id = $village['id'];
$current_wood = $village['wood'];
$current_clay = $village['clay'];
$current_iron = $village['iron'];

$researchManager = new ResearchManager($conn);
$buildingManager = new BuildingManager($conn);

// Sprawdź poziom Akademii w wiosce - Poprawka: Bezpośrednie zapytanie do bazy
$academy_level = 0;
$stmt_academy = $conn->prepare("
    SELECT vb.level 
    FROM village_buildings vb
    JOIN building_types bt ON vb.building_type_id = bt.id
    WHERE vb.village_id = ? AND bt.internal_name = 'academy' LIMIT 1
");
$stmt_academy->bind_param("i", $village_id);
$stmt_academy->execute();
$academy_result = $stmt_academy->get_result()->fetch_assoc();
$stmt_academy->close();

if ($academy_result) {
    $academy_level = (int)$academy_result['level'];
}

if ($academy_level === 0) {
    echo '<div class="info-message">Aby przeprowadzać badania, musisz wybudować Akademię.</div>';
    exit;
}

// Pobierz listę dostępnych badań dla Akademii
$availableResearches = $researchManager->getResearchTypesForBuilding('academy');

// Pobierz aktualne poziomy badań dla wioski
$village_research_levels = $researchManager->getVillageResearchLevels($village_id);

// Pobierz aktualną kolejkę badań
$research_queue = $researchManager->getResearchQueue($village_id);
$current_research_in_queue = [];
foreach ($research_queue as $item) {
    $current_research_in_queue[$item['research_type_id']] = true;
}

$researches_to_display = [];

foreach ($availableResearches as $research) {
     $research_id = $research['id'];
     $internal_name = $research['internal_name'];
     $name = $research['name_pl'];
     $max_level = $research['max_level'];
     $required_building_level = $research['required_building_level'];
     
     $current_level = $village_research_levels[$internal_name] ?? 0;
     $next_level = $current_level + 1;

    // Sprawdź, czy można dalej badać (nie osiągnięto max poziomu i spełniono wymagany poziom budynku)
    if ($current_level < $max_level && $academy_level >= $required_building_level) {
        // Sprawdź dodatkowe wymagania (np. inne badania)
        $requirements_met = true; // Domyślnie spełnione
        if ($research['prerequisite_research_id']) {
            $prereq_id = $research['prerequisite_research_id'];
            $prereq_level = $research['prerequisite_research_level'];
            // Pobierz informacje o wymaganym badaniu
            $prereq_info = $researchManager->getResearchTypeById($prereq_id); // Zakładamy, że getResearchTypeById istnieje i działa poprawnie
            $prereq_internal_name = $prereq_info['internal_name'] ?? null;
            $prereq_current_level = $village_research_levels[$prereq_internal_name] ?? 0;
            
            if ($prereq_current_level < $prereq_level) {
                $requirements_met = false;
            }
        }

        // Sprawdź, czy badanie nie jest już w kolejce
        $is_in_queue = isset($current_research_in_queue[$research_id]);

        if ($requirements_met && !$is_in_queue) {
             // Oblicz koszt i czas dla następnego poziomu
             $cost = $researchManager->getResearchCost($research_id, $next_level);
             $time_seconds = $researchManager->calculateResearchTime($research_id, $next_level, $academy_level); // Użyj poziomu Akademii do obliczenia czasu

            $researches_to_display[] = [
                'id' => $research_id,
                'internal_name' => $internal_name,
                'name_pl' => $name,
                'current_level' => $current_level,
                'next_level' => $next_level,
                'wood_cost' => $cost['wood'],
                'clay_cost' => $cost['clay'],
                'iron_cost' => $cost['iron'],
                'duration_seconds' => $time_seconds,
                'icon' => $research['icon'] ?? null, // Założenie, że ikona jest w danych typu badania
            ];
        }
    }
}

if (empty($researches_to_display)) {
    echo '<div class="info-message">Brak dostępnych badań do rozpoczęcia w tej chwili.</div>';
    exit;
}

echo '<div class="available-research-list">';

foreach ($researches_to_display as $research) {
    $icon_url = isset($research['icon']) && !empty($research['icon']) ? '../img/research/' . $research['icon'] : ''; // Używamy ikony z danych badania
    $can_afford = (
        $current_wood >= $research['wood_cost'] &&
        $current_clay >= $research['clay_cost'] &&
        $current_iron >= $research['iron_cost']
    );

    echo '<div class="research-available-item ' . ($can_afford ? 'can-afford' : 'cannot-afford') . '" data-research-id="' . $research['id'] . '">';
    echo '  <div class="item-header">';
    echo '    <div class="item-title">';
    if ($icon_url) {
        echo '<img src="' . htmlspecialchars($icon_url) . '" class="research-icon" alt="' . htmlspecialchars($research['name_pl']) . '">';
    }
    echo '      <span class="research-name">' . htmlspecialchars($research['name_pl']) . ' (poziom ' . $research['next_level'] . ')</span>'; // Wyświetl docelowy poziom
    echo '    </div>';
    echo '    <div class="item-actions">';
    if ($can_afford) {
        // Przycisk Rozpocznij Badanie
        echo '      <button class="btn btn-primary btn-small start-research-button" data-research-id="' . $research['id'] . '">Badaj</button>';
    } else {
        echo '      <button class="btn btn-primary btn-small" disabled>Badaj</button>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="research-details">';
    echo '    <div class="costs-info">';
    echo '      Koszt: ';
    // Użyj formatNumber z functions.php
    echo '      <span class="cost-item ' . ($current_wood >= $research['wood_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/wood.png" alt="Drewno"> ' . formatNumber($research['wood_cost']) . '</span> ';
    echo '      <span class="cost-item ' . ($current_clay >= $research['clay_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/stone.png" alt="Glina"> ' . formatNumber($research['clay_clay']) . '</span> '; // Poprawiono klucz na clay_cost
    echo '      <span class="cost-item ' . ($current_iron >= $research['iron_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/iron.png" alt="Żelazo"> ' . formatNumber($research['iron_iron']) . '</span>';
    echo '    </div>';
     // Czas badania - zakładamy, że czas jest w sekundach i możemy użyć formatTime Z functions.php
    echo '    <div class="time-info">Czas badania: <span class="research-time" data-time-seconds="' . $research['duration_seconds'] . '">' . formatTime($research['duration_seconds']) . '</span></div>';
    echo '  </div>';
    echo '</div>';
}

echo '</div>'; 