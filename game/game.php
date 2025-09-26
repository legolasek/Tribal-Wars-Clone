<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php'; // Dołącz BuildingConfigManager
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/ResourceManager.php'; // Zaktualizowana ścieżka
// Require other managers if they are initialized and used here directly (e.g., UnitManager, BattleManager, ResearchManager)
require_once __DIR__ . '/../lib/managers/UnitManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/BattleManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/ResearchManager.php'; // Zaktualizowana ścieżka

require_once __DIR__ . '/../lib/functions.php'; // Dołącz plik z funkcjami pomocniczymi


// Stwórz instancje menedżerów
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn); // Inicjalizacja UnitManager
$battleManager = new BattleManager($conn, $villageManager, $buildingManager); // Inicjalizacja BattleManager
$researchManager = new ResearchManager($conn); // Inicjalizacja ResearchManager


if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
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
    header("Location: /player/create_village.php");
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

// Determine time of day and set background image path
date_default_timezone_set('Europe/Warsaw'); // Ustaw strefę czasową na europejską/warszawską (lub odpowiednią dla serwera XAMPP)
$current_hour = (int)date('H'); // Get current hour (0-23)

$day_start_hour = 8;
$night_start_hour = 22;

// Assuming the background image files are named 'background.jpg' in their respective folders
$day_background_path = '/img/ds_graphic/visual/back_none.jpg';
$night_background_path = '/img/ds_graphic/visual_night/back_none.jpg';

if ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) {
    // Day time (8:00 to 21:59)
    $village_background_image = $day_background_path;
} else {
    // Night time (22:00 to 7:59)
    $village_background_image = $night_background_path;
}

require '../header.php';
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
        <!-- Resource Bar -->
        <div id="resource-bar">
            <div class="resource-item">
                <img src="/img/ds_graphic/wood.png" alt="Drewno">
                <span id="current-wood"><?= formatNumber($village['wood']) ?></span> / <span id="warehouse-wood-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/stone.png" alt="Glina">
                <span id="current-clay"><?= formatNumber($village['clay']) ?></span> / <span id="warehouse-clay-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/iron.png" alt="Żelazo">
                <span id="current-iron"><?= formatNumber($village['iron']) ?></span> / <span id="warehouse-iron-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/resources/population.png" alt="Populacja">
                <span id="current-population"><?= $village['population'] ?></span> / <span id="farm-population-capacity"><?= $village['farm_capacity'] ?></span>
            </div>
        </div>
    </header>

    <main id="main-content">
        <?php if (!empty($message)): ?>
            <div class="game-message">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <section class="main-game-content">
            <div id="village-view-graphic">
                <!-- Tutaj będzie grafika wioski z placeholderami budynków -->
                <img src="<?= $village_background_image ?>" alt="Widok wioski" style="width: 100%; height: auto; border-radius: var(--border-radius-medium);">
                <!-- Przykładowe pozycje placeholderów budynków (powinny być w JS lub CSS) -->
                <?php
                // Mapowanie internal_name budynków na nazwy plików grafik
                $building_graphic_map = [
                    'main_building' => 'main',
                    'barracks' => 'barracks',
                    'stable' => 'stable',
                    'workshop' => 'garage',
                    'academy' => 'academy',
                    'market' => 'market',
                    'smithy' => 'smith',
                    'wood_production' => 'wood', // Nazwa pliku grafiki dla tartaku
                    'clay_pit' => 'stone', // Nazwa pliku grafiki dla cegielni (używa 'stone' grafik)
                    'iron_mine' => 'iron', // Nazwa pliku grafiki dla huty
                    'farm' => 'farm',
                    'warehouse' => 'storage', // Nazwa pliku grafiki dla magazynu (używa 'storage' grafik)
                    'wall' => 'wall',
                    'statue' => 'statue',
                    'church' => 'church',
                    'first_church' => 'church_f', // Nazwa pliku grafiki dla pierwszego kościoła
                    'watchtower' => 'watchtower',
                    // Dodaj inne budynki, jeśli mają grafiki
                ];

                // Przykładowe pozycje dla placeholderów budynków
                $building_positions = [
                    // Pozycje odwzorowane na podstawie oryginalnego zrzutu ekranu Plemion (dostosowane do stałego rozmiaru kontenera i większych grafik)
                    'main_building' => ['left' => '45%', 'top' => '35%', 'width' => '18%', 'height' => '28%'], // Ratusz (większy, przesunięty centralnie, dostosowany do tła)
                    'barracks' => ['left' => '25%', 'top' => '58%', 'width' => '12%', 'height' => '18%'], // Koszary
                    'stable' => ['left' => '60%', 'top' => '52%', 'width' => '12%', 'height' => '18%'], // Stajnia
                    'workshop' => ['left' => '34%', 'top' => '28%', 'width' => '12%', 'height' => '18%'], // Warsztat
                    'academy' => ['left' => '69%', 'top' => '64%', 'width' => '12%', 'height' => '18%'], // Akademia
                    'market' => ['left' => '24%', 'top' => '24%', 'width' => '12%', 'height' => '18%'], // Targ
                    'smithy' => ['left' => '17%', 'top' => '34%', 'width' => '12%', 'height' => '18%'], // Kuźnia
                    'wood_production' => ['left' => '7%', 'top' => '55%', 'width' => '12%', 'height' => '18%'], // Tartak
                    'clay_pit' => ['left' => '12%', 'top' => '75%', 'width' => '12%', 'height' => '18%'], // Cegielnia
                    'iron_mine' => ['left' => '77%', 'top' => '70%', 'width' => '12%', 'height' => '18%'], // Huta żelaza
                    'farm' => ['left' => '32%', 'top' => '60%', 'width' => '12%', 'height' => '18%'], // Farma
                    'warehouse' => ['left' => '46%', 'top' => '50%', 'width' => '12%', 'height' => '18%'], // Magazyn
                    'wall' => ['left' => '38%', 'top' => '88%', 'width' => '30%', 'height' => '10%'], // Mur (nieco wyżej i szerszy)
                    'statue' => ['left' => '72%', 'top' => '15%', 'width' => '10%', 'height' => '15%'], // Posąg
                    'church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'], // Kościół
                    'first_church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'], // Pierwszy Kościół
                    'watchtower' => ['left' => '2%', 'top' => '42%', 'width' => '10%', 'height' => '15%'], // Wieża
                ];
                // Sort positions by internal name for consistent rendering
                ksort($building_positions);

                foreach ($buildings_data as $building) {
                    $pos = $building_positions[$building['internal_name']] ?? null;
                    if ($pos) {
                        // Determine the image variant based on building level
                        $internalName = $building['internal_name'];
                        $buildingLevel = $building['level'];
                        $isUpgrading = $building['is_upgrading'] ?? false;

                        $image_variant = null; // Default to no image variant
                        $image_extension = '.png'; // Default to PNG

                        // Logic for production buildings (wood_production, clay_pit, iron_mine, farm)
                        $production_buildings = ['wood_production', 'clay_pit', 'iron_mine', 'farm'];
                        if (in_array($internalName, $production_buildings)) {
                            // Specific logic for Farm image variant based on level
                            if ($internalName === 'farm') {
                                 if ($buildingLevel >= 15) {
                                     $image_variant = 3;
                                 } elseif ($buildingLevel >= 5) { // Farm image starts from level 5 (variant 2)
                                     $image_variant = 2;
                                 }
                                 // No image variant for farm levels 0-4 (image_variant remains null)
                            } else { // Logic for other production buildings (wood_production, clay_pit, iron_mine)
                                 if ($buildingLevel >= 15) {
                                     $image_variant = 3;
                                 } elseif ($buildingLevel >= 10) {
                                     $image_variant = 2;
                                 } elseif ($buildingLevel >= 1) { // Variant 1 for levels 1-9
                                      $image_variant = 1;
                                 }
                            }
                            
                            // If building from level 0, use variant 0 and GIF (applies to all production buildings)
                            if ($buildingLevel === 0 && $isUpgrading) {
                                 $image_variant = 0;
                                 $image_extension = '.gif';
                            }
                            
                            // TODO: Implement logic to switch between GIF (working) and PNG (idle/full) based on resource levels/warehouse capacity.
                            // This requires access to current resource levels and warehouse capacity for the village.
                            // For now, defaulting to PNG unless building from level 0.

                        } else {
                            // Default logic for other buildings (non-production or level 0 not building)
                            if ($buildingLevel >= 15) {
                                $image_variant = 3;
                            } elseif ($buildingLevel >= 10) {
                                $image_variant = 2;
                            } elseif ($buildingLevel >= 1) { // For levels 1-9, use variant 1
                                 $image_variant = 1;
                            } elseif ($buildingLevel === 0 && $isUpgrading) {
                                 // Special case for building from level 0 (e.g., barracks, church) - use variant 0
                                 $image_variant = 0;
                                 $image_extension = '.gif'; // Assume building from 0 is animated
                            } elseif ($buildingLevel === 0 && !$isUpgrading) {
                                 // Level 0 and not building - no image
                                 $image_variant = null;
                            }
                        }

                        // Pobierz bazową nazwę pliku grafiki z mapowania
                        $graphic_base_name = $building_graphic_map[$internalName] ?? $internalName; // Użyj internal_name jako fallback

                        // Determine the base path based on time of day
                        $base_building_image_path = ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? '/img/ds_graphic/visual/' : '/img/ds_graphic/visual_night/'; // Corrected path

                        // Construct the image path
                        $building_image_path = null;
                        if ($image_variant !== null) {
                            $building_image_path = $base_building_image_path . htmlspecialchars($graphic_base_name) . $image_variant . $image_extension;
                        }

                        // Obsługa specjalnych przypadków i grafik GIF w JS (tymczasowo tylko PNG)
                        // TODO: W przyszłości dodaj logikę sprawdzania, czy budynek jest aktywny (produkcja, rekrutacja, badania)
                        // i wybieraj .gif jeśli istnieje i jest aktywny.

                        // Sprawdzenie istnienia grafiki .png (opcjonalne, ale pomaga uniknąć błędów 404 w HTML)
                        // Wymagałoby to sprawdzenia systemu plików na serwerze, czego nie możemy zrobić bezpośrednio w PHP w tym miejscu.
                        // Zostawiamy ścieżkę, zakładając, że grafika PNG/GIF istnieje dla danego wariantu/poziomu.


                        $is_upgrading_class = $isUpgrading ? 'building-upgrading' : '';
                        ?>
                        <div class="building-placeholder <?= $is_upgrading_class ?>" 
                             style="left: <?= $pos['left'] ?>; top: <?= $pos['top'] ?>; width: <?= $pos['width'] ?>; height: <?= $pos['height'] ?>;"
                             data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                             data-village-id="<?= $village_id ?>">
                            <?php if ($building_image_path): ?>
                                <img src="<?= $building_image_path ?>" alt="<?= htmlspecialchars($building['name_pl']) ?>" class="building-icon building-graphic" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-building-level="<?= $building['level'] ?>">
                            <?php else: ?>
                                <!-- No image for this building at this level -->
                                <div class="building-icon building-graphic no-image" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-building-level="<?= $building['level'] ?>"></div>
                            <?php endif; ?>
                            <span class="placeholder-text"><?= htmlspecialchars($building['name_pl']) ?> (<?= $building['level'] ?>)</span>
                        </div>
                        <?php
                    }
                }

                 // Dodaj placeholder dla flagi Ratusza, jeśli Ratusz istnieje
                 if (isset($buildings_data['main_building']) && $buildings_data['main_building']['level'] > 0) {
                     $main_building_level = $buildings_data['main_building']['level'];
                     // Określ wariant flagi na podstawie poziomu Ratusza
                     $flag_variant = 1;
                     if ($main_building_level >= 15) {
                         $flag_variant = 3;
                     } elseif ($main_building_level >= 10) {
                         $flag_variant = 2;
                     }
                     // Ścieżka do grafiki flagi (domyślnie GIF)
                     $flag_image_path = '/img/ds_graphic/visual/' . 'mainflag' . $flag_variant . '.gif'; // Corrected path

                     // Tymczasowo używamy GIFa flagi zawsze, docelowo powinna być animowana tylko gdy ratusz pracuje (buduje coś)

                     // Pozycja dla flagi Ratusza (może wymagać dostosowania)
                     $flag_position = ['left' => '48%', 'top' => '25%', 'width' => '4%', 'height' => '5%']; // Przykładowa pozycja
                      // Sprawdź, czy Ratusz jest w trakcie rozbudowy, aby ew. użyć GIF flagi
                     $main_building_is_upgrading = $buildings_data['main_building']['is_upgrading'] ?? false;

                     // Domyślnie PNG flagi, GIF tylko gdy Ratusz buduje
                     $flag_image_name = 'mainflag' . $flag_variant; // Nazwa pliku bez rozszerzenia
                     $flag_image_path = '/img/ds_graphic/visual/' . $flag_image_name . ($main_building_is_upgrading ? '.gif' : '.gif'); // Corrected path

                      // Sprawdź, czy plik PNG/GIF flagi istnieje? Wymagałoby dostępu do systemu plików
                      // Na razie zakładamy, że istnieją.

                     ?>
                     <div class="building-placeholder main-flag" 
                          style="left: <?= $flag_position['left'] ?>; top: <?= $flag_position['top'] ?>; width: <?= $flag_position['width'] ?>; height: <?= $flag_position['height'] ?>;"
                          data-building-internal-name="main_building_flag" data-village-id="<?= $village_id ?>">
                         <img src="<?= $flag_image_path ?>" alt="Flaga Ratusza" class="building-icon building-graphic" data-building-internal-name="main_building_flag" data-building-level="<?= $main_building_level ?>">
                     </div>
                     <?php
                 }

                ?>
            </div>

            <section class="building-list-section">
                <?php
                // Separate buildings into resource and other categories
                $resource_buildings_data = [];
                $other_buildings_data = [];
                $resource_building_keys = ['sawmill', 'clay_pit', 'iron_mine', 'farm', 'warehouse'];

                foreach ($buildings_data as $internal_name => $building) {
                    if (in_array($internal_name, $resource_building_keys)) {
                        $resource_buildings_data[$internal_name] = $building;
                    } else {
                        $other_buildings_data[$internal_name] = $building;
                    }
                }

                // Function to render a single building item to avoid code duplication
                function render_building_item($building, $village, $buildingManager, $village_id) {
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
                    $production_info = '';
                    if ($production_type) {
                         $hourly_production = $buildingManager->getHourlyProduction($building['internal_name'], $current_level);
                         $production_info = "<p>Produkcja: " . formatNumber($hourly_production) . "/godz.</p>";
                    }
                    $population_info = '';
                    if ($population_cost !== null) {
                         $population_info = "<p>Populacja: " . formatNumber($population_cost) . "</p>";
                    }
                    $upgrade_time_formatted = ($upgrade_time_seconds !== null) ? formatDuration($upgrade_time_seconds) : '';
                    ?>
                    <div class="building-item" data-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">
                        <h3><?= htmlspecialchars($building['name_pl']) ?> (Poziom <?= $building['level'] ?>)</h3>
                        <p><?= htmlspecialchars($building['description_pl']) ?></p>
                        <?= $production_info ?>
                        <?= $population_info ?>

                        <?php if ($is_upgrading): ?>
                            <p class="upgrade-status">W trakcie rozbudowy do poziomu <?= $queue_level_after ?>.</p>
                            <p class="upgrade-timer" data-finish-time="<?= $queue_finish_time ?>"><?= getRemainingTimeText($queue_finish_time) ?></p>
                            <div class="progress-bar-container" data-finish-time="<?= $queue_finish_time ?>"><div class="progress-bar"></div><span class="progress-text"></span></div>
                            <button class="btn btn-secondary cancel-upgrade-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">Anuluj</button>
                        <?php elseif ($current_level >= $max_level): ?>
                             <p class="upgrade-status">Osiągnięto maksymalny poziom (<?= $max_level ?>).</p>
                             <button class="btn btn-secondary" disabled>Maksymalny poziom</button>
                        <?php else: ?>
                            <p class="upgrade-status">Rozbudowa do poziomu <?= $next_level ?>:</p>
                            <?php if ($upgrade_costs): ?>
                                 <p>Koszt:
                                    <span class="resource wood <?= ($village['wood'] < $upgrade_costs['wood']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/wood.png" alt="Drewno"><?= formatNumber($upgrade_costs['wood']) ?></span>
                                    <span class="resource clay <?= ($village['clay'] < $upgrade_costs['clay']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/stone.png" alt="Glina"><?= formatNumber($upgrade_costs['clay']) ?></span>
                                    <span class="resource iron <?= ($village['iron'] < $upgrade_costs['iron']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/iron.png" alt="Żelazo"><?= formatNumber($upgrade_costs['iron']) ?></span>
                                 </p>
                                 <?php if ($next_level_population_cost > 0): ?>
                                     <p>Wymagana wolna populacja: <span class="resource population <?= (($village['farm_capacity'] - $village['population']) < $next_level_population_cost) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/resources/population.png" alt="Populacja"><?= formatNumber($next_level_population_cost) ?></span></p>
                                 <?php endif; ?>
                                 <p>Czas budowy: <span class="upgrade-time-formatted"><?= $upgrade_time_formatted ?></span></p>

                                 <?php if ($can_upgrade): ?>
                                     <button class="btn btn-primary upgrade-building-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-village-id="<?= $village_id ?>">Rozbuduj do poziomu <?= $next_level ?></button>
                                 <?php else: ?>
                                     <p class="error-message"><?= htmlspecialchars($upgrade_not_available_reason) ?></p>
                                     <button class="btn btn-primary" disabled>Rozbuduj do poziomu <?= $next_level ?></button>
                                 <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                             $buildings_with_panels = ['main_building', 'barracks', 'stable', 'workshop', 'smithy', 'academy', 'market', 'statue', 'church', 'first_church', 'mint'];
                             if (in_array($building['internal_name'], $buildings_with_panels) && $building['level'] > 0):
                                 $actionText = getBuildingActionText($building['internal_name']);
                        ?>
                                <button class="btn btn-secondary building-action-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-village-id="<?= $village_id ?>"><?= htmlspecialchars($actionText) ?></button>
                            <?php endif; ?>
                    </div>
                    <?php
                }
                ?>

                <h2>Budynki Surowcowe</h2>
                <div class="buildings-list">
                    <?php foreach ($resource_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
                </div>

                <h2 style="margin-top: 30px;">Budynki Miejskie i Wojskowe</h2>
                <div class="buildings-list">
                    <?php foreach ($other_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
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

<?php require '../footer.php'; ?>

<!-- Scripts -->
<script src="/js/resources.js" defer></script>
<script src="/js/notifications.js" defer></script>
<script src="/js/buildings.js" defer></script>
<script src="/js/units.js" defer></script>
<script src="/js/research.js" defer></script>
<script src="/js/market.js" defer></script>
<script src="/js/main_building.js" defer></script>
<script src="/js/noble.js" defer></script>
<script src="/js/mint.js" defer></script>
<script src="/js/info_panel.js" defer></script> <!-- Generic panel for info -->
<script src="/js/main.js" defer></script> // Add main.js with absolute path



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
