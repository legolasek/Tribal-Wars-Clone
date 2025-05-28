<?php
require 'init.php';
require_once __DIR__ . '/lib/managers/BuildingManager.php'; // Zaktualizowana ścieżka
require_once 'lib/managers/BuildingConfigManager.php'; // Dołącz BuildingConfigManager
require_once __DIR__ . '/lib/managers/VillageManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/lib/managers/ResourceManager.php'; // Zaktualizowana ścieżka
// Require other managers if they are initialized and used here directly (e.g., UnitManager, BattleManager, ResearchManager)
require_once __DIR__ . '/lib/managers/UnitManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/lib/managers/BattleManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/lib/managers/ResearchManager.php'; // Zaktualizowana ścieżka

require_once 'lib/functions.php'; // Dołącz plik z funkcjami pomocniczymi


// Stwórz instancje menedżerów
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn); // Inicjalizacja UnitManager
$battleManager = new BattleManager($conn, $villageManager); // Inicjalizacja BattleManager
$researchManager = new ResearchManager($conn); // Inicjalizacja ResearchManager


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = ''; // Do wyświetlania komunikatów

// --- POBIERANIE DANYCH WIOSKI ---
// Użyj VillageManager do pobrania danych wioski
$village = $villageManager->getFirstVillage($user_id);

if (!$village) {
    // To nie powinno się zdarzyć po wprowadzeniu create_village.php, ale zostawiamy jako zabezpieczenie
    header("Location: create_village.php");
    exit();
}
$village_id = $village['id'];

// Pobierz poziom Ratusza - potrzebny dla BuildingManager
$main_building_level = $buildingManager->getBuildingLevel($village_id, 'main_building');

// --- PRZETWARZANIE ZAKOŃCZONYCH ZADAŃ ---
// Wywołaj VillageManager::processCompletedTasksForVillage, aby przetworzyć
// zakończone budowy, rekrutacje, badania i zaktualizować surowce.
// Ta metoda zwróci komunikaty do wyświetlenia.
// Przetwarzanie ataków zostanie przeniesione do BattleManager i wywołane stamtąd.
$messages = $villageManager->processCompletedTasksForVillage($village_id);
$message = implode('', $messages); // Połącz komunikaty w jeden string

// Po przetworzeniu zadań i aktualizacji surowców przez VillageManager->updateResources,
// dane wioski w pamięci mogą być nieaktualne. Należy je pobrać ponownie.
$village = $villageManager->getVillageInfo($village_id); // Pobierz zaktualizowane dane wioski

// --- PRZETWARZANIE ZAKOŃCZONYCH ATAKÓW ---
// Ta logika została przeniesiona z game.php do BattleManager::processCompletedAttacks
$attackMessages = $battleManager->processCompletedAttacks($user_id); // Zakładamy, że processCompletedAttacks przyjmuje user_id
// Dodaj komunikaty o atakach do głównych komunikatów
if (!empty($attackMessages)) {
    $message .= implode('', $attackMessages);
}

// --- POBIERANIE DANYCH BUDYNKÓW DLA WIDOKU ---
// Użyj BuildingManager do pobrania wszystkich danych budynków potrzebnych dla widoku
$buildings_data = $buildingManager->getVillageBuildingsViewData($village_id, $main_building_level); // Użyj nowej metody

// --- WIDOK WIOSKI (HTML) ---
$pageTitle = htmlspecialchars($village['name']) . ' - Widok Wioski';
require 'header.php';
?>

<div id="game-container">
    <!-- Game header with resources -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">🏠</span> <!-- Ikona dla przeglądu wioski -->
            <span>Przegląd</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?><br>
            <span class="village-name-display" data-village-id="<?= $village_id ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
        </div>
    </header>

    <main id="main-content">
        

        <section class="main-game-content">
            <div id="village-view-graphic">
                <!-- Tutaj będzie grafika wioski z placeholderami budynków -->
                <img src="img/village_bg.jpg" alt="Widok wioski" style="width: 100%; height: auto; border-radius: var(--border-radius-medium);">
                <!-- Przykładowe pozycje placeholderów budynków (powinny być w JS lub CSS) -->
                <?php
                // Przykładowe pozycje dla placeholderów budynków
                $building_positions = [
                    'main_building' => ['left' => '45%', 'top' => '30%', 'width' => '10%', 'height' => '15%'],
                    'barracks' => ['left' => '20%', 'top' => '50%', 'width' => '8%', 'height' => '12%'],
                    'stable' => ['left' => '70%', 'top' => '40%', 'width' => '8%', 'height' => '12%'],
                    'clay_pit' => ['left' => '10%', 'top' => '70%', 'width' => '8%', 'height' => '12%'],
                    'iron_mine' => ['left' => '80%', 'top' => '75%', 'width' => '8%', 'height' => '12%'],
                    'sawmill' => ['left' => '30%', 'top' => '80%', 'width' => '8%', 'height' => '12%'],
                    'warehouse' => ['left' => '55%', 'top' => '60%', 'width' => '8%', 'height' => '12%'],
                    'farm' => ['left' => '35%', 'top' => '50%', 'width' => '8%', 'height' => '12%'],
                    'wall' => ['left' => '40%', 'top' => '90%', 'width' => '20%', 'height' => '8%'],
                    'market' => ['left' => '60%', 'top' => '20%', 'width' => '8%', 'height' => '12%'],
                    'smithy' => ['left' => '15%', 'top' => '25%', 'width' => '8%', 'height' => '12%'],
                    'academy' => ['left' => '75%', 'top' => '65%', 'width' => '8%', 'height' => '12%'],
                    'stable' => ['left' => '70%', 'top' => '40%', 'width' => '8%', 'height' => '12%'], // Duplicate, remove one
                    'workshop' => ['left' => '25%', 'top' => '35%', 'width' => '8%', 'height' => '12%'],
                    'statue' => ['left' => '85%', 'top' => '55%', 'width' => '8%', 'height' => '12%'],
                    'church' => ['left' => '50%', 'top' => '10%', 'width' => '8%', 'height' => '12%'],
                    'first_church' => ['left' => '50%', 'top' => '10%', 'width' => '8%', 'height' => '12%'], // Same position as church?
                     'watchtower' => ['left' => '5%', 'top' => '15%', 'width' => '8%', 'height' => '12%'],
                ];
                // Sort positions by internal name for consistent rendering
                ksort($building_positions);

                foreach ($buildings_data as $building) {
                    $pos = $building_positions[$building['internal_name']] ?? null;
                    if ($pos) {
                        $building_image_path = 'img/ds_graphic/buildings/' . htmlspecialchars($building['internal_name']) . '.png'; // Corrected image path
                        $is_upgrading_class = $building['is_upgrading'] ? 'building-upgrading' : '';
                        ?>
                        <div class="building-placeholder <?= $is_upgrading_class ?>" 
                             style="left: <?= $pos['left'] ?>; top: <?= $pos['top'] ?>; width: <?= $pos['width'] ?>; height: <?= $pos['height'] ?>;"
                             data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                             data-village-id="<?= $village_id ?>">
                            <img src="<?= $building_image_path ?>" alt="<?= htmlspecialchars($building['name_pl']) ?>" class="building-icon">
                            <span class="placeholder-text"><?= htmlspecialchars($building['name_pl']) ?> (<?= $building['level'] ?>)</span>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <section class="building-list-section">
                <h2>Szczegóły budynków</h2>
                <div class="buildings-list">
                    <?php foreach ($buildings_data as $building): ?>
                        <?php
                        $current_level = $building['level'];
                        $next_level = $building['next_level'];
                        $is_upgrading = $building['is_upgrading'];
                        $queue_finish_time = $building['queue_finish_time'];
                        $queue_level_after = $building['queue_level_after'];
                        $max_level = $building['max_level'];
                        $upgrade_costs = $building['upgrade_costs'];
                        $upgrade_time_seconds = $building['upgrade_time_seconds'];
                        $can_upgrade = $building['can_upgrade'];
                        $upgrade_not_available_reason = $building['upgrade_not_available_reason'];
                        $production_type = $building['production_type'];
                        $population_cost = $building['population_cost'];
                        $next_level_population_cost = $building['next_level_population_cost'];

                        // Add production info for resource buildings
                        $production_info = '';
                        if ($production_type && ($building['internal_name'] === 'wood_production' || $building['internal_name'] === 'clay_production' || $building['internal_name'] === 'iron_production' || $building['internal_name'] === 'farm')) {
                             $hourly_production = $buildingManager->getHourlyProduction($building['internal_name'], $current_level);
                             $production_info = "<p>Produkcja: " . formatNumber($hourly_production) . "/godz.</p>";
                        }

                         // Add population cost info
                        $population_info = '';
                        if ($population_cost !== null) {
                             $population_info = "<p>Populacja: " . formatNumber($population_cost) . "</p>";
                        }
                        
                        // Format upgrade time
                        $upgrade_time_formatted = ($upgrade_time_seconds !== null) ? formatDuration($upgrade_time_seconds) : '';

                        ?>
                        <div class="building-item" data-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">
                            <h3><?= htmlspecialchars($building['name_pl']) ?> (Poziom <?= $building['level'] ?>)</h3>
                            <p><?= htmlspecialchars($building['description_pl']) ?></p>
                            <?= $production_info // Display production info if available ?>
                            <?= $population_info // Display population cost info if available ?>

                            <?php if ($is_upgrading): ?>
                                <p class="upgrade-status">W trakcie rozbudowy do poziomu <?= $queue_level_after ?>.</p>
                                <p class="upgrade-timer" data-finish-time="<?= $queue_finish_time ?>"><?= getRemainingTimeText($queue_finish_time) ?></p>
                                 <!-- Progress bar placeholder -->
                                <div class="progress-bar-container" data-finish-time="<?= $queue_finish_time ?>">
                                    <div class="progress-bar"></div>
                                    <span class="progress-text"></span>
                                </div>
                                <button class="btn btn-secondary cancel-upgrade-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">Anuluj</button>
                            <?php elseif ($current_level >= $max_level): ?>
                                 <p class="upgrade-status">Osiągnięto maksymalny poziom (<?= $max_level ?>).</p>
                                 <button class="btn btn-secondary" disabled>Maksymalny poziom</button>
                            <?php else: ?>
                                <p class="upgrade-status">Rozbudowa do poziomu <?= $next_level ?>:</p>
                                <?php if ($upgrade_costs): ?>
                                     <p>Koszt: 
                                        <span class="resource wood <?= ($village['wood'] ?? 0) < ($upgrade_costs['wood'] ?? 0) ? 'not-enough' : '' ?>">
                                            <img src="img/ds_graphic/resources/wood.png" alt="Drewno"><?= formatNumber($upgrade_costs['wood'] ?? 0) ?>
                                        </span>
                                        <span class="resource clay <?= ($village['clay'] ?? 0) < ($upgrade_costs['clay'] ?? 0) ? 'not-enough' : '' ?>">
                                            <img src="img/ds_graphic/resources/clay.png" alt="Glina"><?= formatNumber($upgrade_costs['clay'] ?? 0) ?>
                                        </span>
                                        <span class="resource iron <?= ($village['iron'] ?? 0) < ($upgrade_costs['iron'] ?? 0) ? 'not-enough' : '' ?>">
                                            <img src="img/ds_graphic/resources/iron.png" alt="Żelazo"><?= formatNumber($upgrade_costs['iron'] ?? 0) ?>
                                        </span>
                                     </p>
                                     <?php if ($next_level_population_cost !== null && $next_level_population_cost > 0): // Check population cost for NEXT level ?>
                                         <p>Wymagana wolna populacja: 
                                             <span class="resource population <?= (($village['max_population'] ?? 0) - ($village['current_population'] ?? 0)) < $next_level_population_cost ? 'not-enough' : '' ?>">
                                                <img src="img/ds_graphic/resources/population.png" alt="Populacja"><?= formatNumber($next_level_population_cost) ?>
                                             </span>
                                         </p>
                                     <?php endif; ?>
                                     <?php if ($upgrade_time_seconds !== null): ?>
                                         <p>Czas budowy: <span class="upgrade-time-formatted"><?= $upgrade_time_formatted ?></span></p>
                                     <?php endif; ?>
                                     
                                     <?php if ($can_upgrade): ?>
                                         <button class="btn btn-primary upgrade-building-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-village-id="<?= $village_id ?>">Rozbuduj do poziomu <?= $next_level ?></button>
                                     <?php else: ?>
                                         <p class="error-message"><?= htmlspecialchars($upgrade_not_available_reason) ?></p>
                                         <button class="btn btn-primary" disabled>Rozbuduj do poziomu <?= $next_level ?></button>
                                     <?php endif; ?>
                                     
                                <?php else: ?>
                                     <p>Brak danych o kosztach.</p>
                                     <button class="btn btn-primary" disabled>Rozbuduj do poziomu <?= $next_level ?></button>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Action Button (opens specific building panel via AJAX/JS) -->
                             <?php 
                                // Only show action button if not upgrading and not max level (or if action is always available)
                                $showActionButton = !$is_upgrading; // Show if not upgrading
                                // Some buildings might have actions even at max level (e.g. Market, Barracks)
                                // We need a way to define this, maybe in building config?
                                // For now, assume action is available if not upgrading and not max level,
                                // OR if it's a building with a dedicated panel regardless of upgrade status/level > 0.
                                 $buildings_with_panels = ['main_building', 'barracks', 'stable', 'workshop', 'smithy', 'academy', 'market', 'statue', 'church', 'first_church', 'mint']; // Add buildings with panels
                                 if (in_array($building['internal_name'], $buildings_with_panels) && $current_level > 0) {
                                     $showActionButton = true; // Always show button for buildings with panels if level > 0
                                 }

                                if ($showActionButton): 
                                     $actionText = getBuildingActionText($building['internal_name']);
                                     if ($actionText !== 'Akcja' || in_array($building['internal_name'], $buildings_with_panels)): // Show button if specific text or has panel
                             ?>
                                    <button class="btn btn-secondary building-action-button" 
                                             data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                                             data-village-id="<?= $village_id ?>">
                                             <?= htmlspecialchars($actionText) ?>
                                    </button>
                                <?php 
                                     endif;
                                endif;
                                ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <!-- Building Details/Action Popup -->
        <div id="building-action-popup" class="popup-container">
            <div class="popup-content">
                <span class="close-button">&times;</span>
                <div id="popup-details">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>



    </main>
</div>

<?php require 'footer.php'; ?>

<!-- Scripts -->
<script src="js/resources.js"></script>
<script src="js/buildings.js"></script>
<script src="js/units.js"></script>
<script src="js/research.js"></script>
<script src="js/market.js"></script>
<script src="js/main_building.js"></script>
<script src="js/noble.js"></script>
<script src="js/mint.js"></script>
<script src="js/info_panel.js"></script> <!-- Generic panel for info -->



<script>
// Helper functions (should be in a common.js file)
// Usunięte zduplikowane funkcje PHP i JS - są w lib/functions.php i js/resources.js/inne pliki JS
// function formatDuration(seconds) { ... }
// function formatNumber(number) { ... }


document.addEventListener('DOMContentLoaded', function() {
    // Initialize resource timers
    // Assuming fetchUpdate from resources.js is called here or via setInterval
    // fetchUpdate(); // Initial fetch
    // setInterval(fetchUpdate, 1000); // Update every second

    // Initialize building queue timers
    updateTimers(); // from js/buildings.js
    setInterval(updateTimers, 1000); // Update every second

    // Initialize recruitment queue timers
    // updateRecruitmentTimers(); // from js/units.js
    // setInterval(updateRecruitmentTimers, 1000); // Update every second

     // Initialize research queue timers
    // updateResearchTimers(); // from js/research.js
    // setInterval(updateResearchTimers, 1000); // Update every second

});

</script>
