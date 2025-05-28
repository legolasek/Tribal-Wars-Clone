<?php
require 'init.php';
require_once 'lib/BuildingManager.php';
require_once 'lib/managers/BuildingConfigManager.php'; // Dołącz BuildingConfigManager
require_once 'lib/VillageManager.php'; // Potrzebny do pobrania danych wioski i poziomu Ratusza
require_once 'lib/ResourceManager.php'; // Dołącz ResourceManager

// Stwórz instancję BuildingConfigManager
$buildingConfigManager = new BuildingConfigManager($conn);
// Stwórz instancję BuildingManager, przekazując połączenie i BuildingConfigManager
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
// Stwórz instancję VillageManager
$villageManager = new VillageManager($conn);
// Stwórz instancję ResourceManager
$resourceManager = new ResourceManager($conn, $buildingManager);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = ''; // Do wyświetlania komunikatów

// --- POBIERANIE DANYCH WIOSKI ---
// Użyj VillageManager do pobrania danych wioski
$village = $villageManager->getFirstVillage($user_id); // Zmieniono getFirstVillageInfo na getFirstVillage

if (!$village) {
    // To nie powinno się zdarzyć po wprowadzeniu create_village.php, ale zostawiamy jako zabezpieczenie
    header("Location: create_village.php");
    exit();
}
$village_id = $village['id'];

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

// --- OBSŁUGA REKRUTACJI JEDNOSTEK (tylko inicjalizacja managera) ---
require_once 'lib/UnitManager.php';
// UnitManager może potrzebować BuildingConfigManager/UnitConfigManager w przyszłości
$unitManager = new UnitManager($conn); 

// --- OBSŁUGA ATAKÓW (inicjalizacja managera i przetwarzanie zakończonych) ---
require_once 'lib/BattleManager.php';
// BattleManager może potrzebować UnitConfigManager i VillageManager w przyszłości
$battleManager = new BattleManager($conn, $villageManager); // Pass VillageManager

// Przetwarzanie zakończonych ataków i zbieranie komunikatów
// Ta logika została przeniesiona z game.php do BattleManager::processCompletedAttacks
$attackMessages = $battleManager->processCompletedAttacks($user_id); // Zakładamy, że processCompletedAttacks przyjmuje user_id
// Dodaj komunikaty o atakach do głównych komunikatów
if (!empty($attackMessages)) {
    $message .= implode('', $attackMessages);
}

// --- OBSŁUGA BADAŃ (tylko inicjalizacja managera) ---
require_once 'lib/ResearchManager.php';
// ResearchManager może potrzebować UnitConfigManager i BuildingConfigManager w przyszłości
$researchManager = new ResearchManager($conn);

// Helper function to get building action button text (PHP)
function getBuildingActionText(string $internalName): string {
    switch ($internalName) {
        case 'main_building': return 'Zarządzaj wioską';
        case 'barracks': return 'Rekrutuj jednostki';
        case 'stable': return 'Rekrutuj jednostki';
        case 'garage': return 'Produkuj maszyny oblężnicze';
        case 'smithy': return 'Badaj technologie';
        case 'academy': return 'Badaj technologie';
        case 'market': return 'Handluj surowcami';
        case 'statue': return 'Zarządzaj szlachcicem';
        case 'church':
        case 'first_church': return 'Wpływ religijny';
        case 'watchtower': return 'Widok z wieży strażniczej';
        case 'hospital': return 'Leczenie jednostek';
        case 'tavern': return 'Zarządzaj bohaterem'; // Example, if hero system added
        case 'university': return 'Badaj zaawansowane technologie'; // Example, if another research type added
        case 'workshop': return 'Produkuj specjalne przedmioty'; // Example
        case 'mint': return 'Wybijaj monety';
        case 'temple': return 'Zarządzaj kapłanami'; // Example
        case 'tower': return 'Widok z wieży'; // Example
        default: return 'Akcja';
    }
}

// --- POBIERANIE BUDYNKÓW WIOSKI ---
// Użyj VillageManager do pobrania budynków i ich poziomów, w tym statusu kolejki
// VillageManager::getVillageBuildings zostało zaktualizowane, aby zwrócić pełniejsze dane
$village_buildings_data = $villageManager->getVillageBuildings($village_id); // Zmieniono wywołanie na $villageManager

// Pobierz wszystkie konfiguracje budynków, aby mieć nazwy PL, opisy, max poziomy itp.
$allBuildingConfigs = $buildingConfigManager->getAllBuildingConfigs(); // Upewnij się, że ta metoda jest publiczna i działa

// Połącz dane budynków z wioski z ich konfiguracją (jeśli getVillageBuildings nie zwraca wszystkiego)
// Jeśli getVillageBuildings zwraca wszystko, ten krok można uprościć.
$buildings_data = [];
foreach ($allBuildingConfigs as $config) {
    $internal_name = $config['internal_name'];
    
    // Znajdź dane dla tego budynku w tablicy zwróconej przez VillageManager
    $village_building = $village_buildings_data[$internal_name] ?? null; // VillageManager zwraca keyed by internal_name

    $level = $village_building['level'] ?? 0;
    $is_upgrading = $village_building['is_upgrading'] ?? false;
    $queue_finish_time = $village_building['queue_finish_time'] ?? null;
    $queue_level_after = $village_building['queue_level_after'] ?? null;

    $buildings_data[$internal_name] = [
        'internal_name' => $internal_name,
        'name_pl' => $config['name_pl'],
        'level' => (int)$level,
        'description_pl' => $config['description_pl'] ?? 'Brak opisu.',
        'max_level' => (int)$config['max_level'],
        'is_upgrading' => $is_upgrading,
        'queue_finish_time' => $queue_finish_time,
        'queue_level_after' => $queue_level_after,
        // Dodaj inne potrzebne dane konfiguracyjne, np. production_type
        'production_type' => $config['production_type'] ?? null, // Dodaj production_type
    ];
}

// Sortowanie budynków według internal_name lub zdefiniowanej kolejności (np. z BuildingConfigManager)
ksort($buildings_data); // Sortowanie po kluczu (internal_name) jako domyślne

// Pobierz aktualny element kolejki budowy dla tej wioski
$queue_item = $buildingManager->getBuildingQueueItem($village_id);

// --- WIDOK WIOSKI (HTML) ---
$pageTitle = htmlspecialchars($village['name']) . ' - Widok Wioski';
require 'header.php';
?>

<div id="game-container">
    <main id="main-content">
        <aside id="sidebar">
            <div id="village-info">
                <h2><?= htmlspecialchars($village['name']) ?> <span class="coords">(<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span></h2>
                <p>Punkty: <?= formatNumber($village['points']) ?></p>
                <p class="last-update">Ostatnia aktualizacja: <?= date('H:i:s', strtotime($village['last_resource_update'])) ?></p>
                <button class="btn btn-primary" onclick="window.location.href='rename_village.php?village_id=<?= $village_id ?>'">Zmień nazwę</button>
            </div>

            <section id="building-queue">
                <h3>Kolejka budowy</h3>
                <div class="queue-content" id="building-queue-list">
                    <?php if ($queue_item): ?>
                        <div class="queue-item current">
                            <div class="item-header">
                                <div class="item-title">
                                    <span class="building-name"><?= htmlspecialchars($buildings_data[$queue_item['internal_name']]['name_pl'] ?? $queue_item['internal_name']) ?></span>
                                    <span class="building-level">Poziom <?= $queue_item['level'] ?></span>
                                </div>
                                <div class="item-actions">
                                    <button class="cancel-button" data-queue-id="<?= $queue_item['id'] ?>" title="Anuluj budowę">✖</button>
                                </div>
                            </div>
                            <div class="item-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%;"></div>
                                </div>
                                <div class="progress-time" data-ends-at="<?= strtotime($queue_item['finish_time']) ?>" data-start-time="<?= strtotime($queue_item['start_time']) ?>"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="queue-empty">Brak zadań w kolejce budowy.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="map-section">
                <h3>Mapa</h3>
                <p>Zobacz okoliczne wioski i planuj podboje.</p>
                <a href="map.php" class="btn map-link-btn">Przejdź do mapy</a>
            </section>
        </aside>

        <section class="main-game-content">
            <div id="village-view-graphic">
                <!-- Tutaj będzie grafika wioski z placeholderami budynków -->
                <img src="img/village_bg.jpg" alt="Widok wioski" style="width: 100%; height: auto; border-radius: var(--border-radius-medium);">
                <!-- Przykładowe placeholdery (pozycja będzie dynamiczna) -->
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
                    // Dodaj więcej budynków
                ];

                foreach ($buildings_data as $building) {
                    $pos = $building_positions[$building['internal_name']] ?? null;
                    if ($pos) {
                        $building_image_path = 'img/' . htmlspecialchars($building['internal_name']) . '.png';
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
                        $next_level = $current_level + 1;
                        $is_upgrading = $building['is_upgrading'];
                        $queue_finish_time = $building['queue_finish_time'] ?? null;
                        $queue_level_after = $building['queue_level_after'] ?? null;
                        $max_level = $building['max_level'];
                        
                        $upgrade_costs = null;
                        $upgrade_time_seconds = null;
                        $upgrade_time_formatted = '';
                        $requirements = [];
                        $can_upgrade = false;
                        $upgrade_not_available_reason = '';

                        if (!$is_upgrading && $current_level < $max_level) {
                             $can_upgrade_check = $buildingManager->canUpgradeBuilding($village_id, $building['internal_name']);
                             $can_upgrade = $can_upgrade_check['success'];
                             $upgrade_not_available_reason = $can_upgrade_check['message'];

                             if ($can_upgrade) {
                                $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($building['internal_name'], $current_level);
                                $main_building_level = $buildingManager->getBuildingLevel($village_id, 'main_building'); 
                                $upgrade_time_seconds = $buildingConfigManager->calculateUpgradeTime($building['internal_name'], $current_level, $main_building_level);
                                 if ($upgrade_time_seconds !== null) {
                                     $upgrade_time_formatted = formatDuration($upgrade_time_seconds);
                                 }
                                 $requirements = $buildingConfigManager->getBuildingRequirements($building['internal_name']);
                             }
                        }
                        ?>
                        <div class="building-item" data-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">
                            <h3><?= htmlspecialchars($building['name_pl']) ?> (Poziom <?= $building['level'] ?>)</h3>
                            <p><?= htmlspecialchars($building['description_pl']) ?></p>
                            
                            <?php if ($is_upgrading): ?>
                                <p class="upgrade-status">W trakcie rozbudowy do poziomu <?= $queue_level_after ?>.</p>
                                <p class="upgrade-timer" data-finish-time="<?= $queue_finish_time ?>"><?= getRemainingTimeText($queue_finish_time) ?></p>
                                <button class="btn btn-secondary" disabled>W kolejce...</button>
                            <?php elseif ($current_level >= $max_level): ?>
                                 <p class="upgrade-status">Osiągnięto maksymalny poziom (<?= $max_level ?>).</p>
                                 <button class="btn btn-secondary" disabled>Maksymalny poziom</button>
                            <?php else: ?>
                                <p class="upgrade-status">Rozbudowa do poziomu <?= $next_level ?>:</p>
                                <?php if ($upgrade_costs): ?>
                                     <p>Koszt: 
                                         <span class="resource-cost wood"><img src="img/wood.png" alt="Drewno"> <?= formatNumber($upgrade_costs['wood']) ?></span> 
                                         <span class="resource-cost clay"><img src="img/clay.png" alt="Glina"> <?= formatNumber($upgrade_costs['clay']) ?></span> 
                                         <span class="resource-cost iron"><img src="img/iron.png" alt="Żelazo"> <?= formatNumber($upgrade_costs['iron']) ?></span>
                                     </p>
                                     <p>Czas budowy: <?= $upgrade_time_formatted ?></p>
                                    
                                     <?php if (!empty($requirements)): ?>
                                        <div class="building-requirements">
                                            <p>Wymagania:</p>
                                            <ul>
                                                <?php foreach ($requirements as $req): ?>
                                                    <?php $reqBuildingName = $buildingConfigManager->getBuildingConfig($req['required_building'])['name_pl'] ?? $req['required_building']; ?>
                                                    <li><?= htmlspecialchars($reqBuildingName) ?> (Poziom <?= $req['required_level'] ?>)</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                     <?php endif; ?>

                                     <?php if ($can_upgrade): ?>
                                         <button class="btn btn-primary upgrade-button" 
                                                 data-village-id="<?= $village_id ?>"
                                                 data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                                                 data-current-level="<?= $current_level ?>">
                                             Rozbuduj do poziomu <?= $next_level ?>
                                         </button>
                                     <?php else: ?>
                                         <button class="btn btn-secondary" disabled title="<?= htmlspecialchars($upgrade_not_available_reason) ?>">Rozbuduj</button>
                                         <p class="upgrade-unavailable-reason">Powód: <?= htmlspecialchars($upgrade_not_available_reason) ?></p>
                                     <?php endif; ?>

                                <?php else: ?>
                                     <p class="upgrade-status">Nie można obliczyć kosztów/czasu rozbudowy.</p>
                                     <button class="btn btn-secondary" disabled>Niedostępne</button>
                                <?php endif; ?>

                            <?php endif; ?>
                            
                            <?php if ($building['production_type']): ?>
                                 <?php
                                 $prod_capacity_info = $buildingConfigManager->getProductionOrCapacityInfo($building['internal_name'], $current_level);
                                 if ($prod_capacity_info) {
                                     echo '<p class="building-info-details">';
                                     if ($prod_capacity_info['type'] === 'production') {
                                         echo 'Produkcja: ' . formatNumber($prod_capacity_info['amount_per_hour']) . '/godz. ' . htmlspecialchars(ucfirst($building['production_type']));
                                         if ($current_level < $max_level) {
                                              $next_level_info = $buildingConfigManager->getProductionOrCapacityInfo($building['internal_name'], $next_level);
                                              if ($next_level_info && isset($next_level_info['amount_per_hour'])) {
                                                   echo ' (Nast. poz.: +' . formatNumber($next_level_info['amount_per_hour']) . ')';
                                              }
                                         }
                                     } elseif ($prod_capacity_info['type'] === 'capacity') {
                                          if ($building['internal_name'] === 'warehouse') {
                                               echo 'Pojemność magazynu: ' . formatNumber($prod_capacity_info['amount']);
                                              if ($current_level < $max_level) {
                                                   $next_level_info = $buildingConfigManager->getProductionOrCapacityInfo($building['internal_name'], $next_level);
                                                   if ($next_level_info && isset($next_level_info['amount'])) {
                                                        echo ' (Nast. poz.: ' . formatNumber($next_level_info['amount']) . ')';
                                                   }
                                              }
                                          } elseif ($building['internal_name'] === 'farm') {
                                               echo 'Limit populacji: ' . formatNumber($prod_capacity_info['amount']);
                                              if ($current_level < $max_level) {
                                                   $next_level_info = $buildingConfigManager->getProductionOrCapacityInfo($building['internal_name'], $next_level);
                                                   if ($next_level_info && isset($next_level_info['amount'])) {
                                                        echo ' (Nast. poz.: ' . formatNumber($next_level_info['amount']) . ')';
                                                   }
                                              }
                                          }
                                     }
                                     echo '</p>';
                                 }
                             ?>
                        <?php endif; ?>

                        <?php if (in_array($building['internal_name'], ['barracks', 'stable', 'garage', 'smithy', 'academy', 'market', 'statue', 'church', 'first_church', 'watchtower', 'hospital', 'tavern', 'university', 'workshop', 'mint', 'temple', 'tower'])): ?>
                            <button class="btn btn-primary building-action-button" 
                                    data-village-building-id="<?= $building['id'] ?>" 
                                    data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">
                                <?= getBuildingActionText($building['internal_name']) ?>
                            </button>
                        <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    </main>
</div>

<!-- Popup dla szczegółów budynku -->
<div id="popup-overlay"></div>
<div id="building-details-popup">
    <button id="popup-close-btn" class="btn">&times;</button>
    <h3 id="popup-building-name"></h3>
    <p id="popup-building-description"></p>
    <div id="popup-building-info">
        <h4>Aktualny poziom: <span id="popup-current-level"></span></h4>
        <p id="popup-production-info"></p>
        <p id="popup-capacity-info"></p>
    </div>
    <div id="popup-upgrade-info">
        <h4>Rozbudowa do poziomu <span id="popup-next-level"></span>:</h4>
        <p id="popup-upgrade-costs"></p>
        <p id="popup-upgrade-time"></p>
        <div id="popup-requirements"></div>
        <p id="popup-upgrade-reason" class="upgrade-unavailable-reason"></p>
        <button id="popup-upgrade-button" class="btn btn-primary">Rozbuduj</button>
    </div>
    <div id="popup-action-content">
        <!-- Tutaj będzie ładowana zawartość AJAX dla akcji budynku (np. rekrutacja) -->
    </div>
</div>

<?php
require 'footer.php';
// Połączenie z bazą danych zostanie zamknięte automatycznie na końcu skryptu
// if (isset($database)) {
//     $database->closeConnection();
// }
?>

<script>
// Function to update timers on the page
function updateTimers() {
    document.querySelectorAll('.build-timer, .upgrade-timer, .trade-timer').forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt) * 1000; // Data attribute is Unix timestamp in seconds
        const currentTime = new Date().getTime();
        const remainingTime = finishTime - currentTime;

        if (remainingTime > 0) {
            const seconds = Math.floor((remainingTime / 1000) % 60);
            const minutes = Math.floor((remainingTime / (1000 * 60)) % 60);
            const hours = Math.floor((remainingTime / (1000 * 60 * 60)) % 24);
            const days = Math.floor(remainingTime / (1000 * 60 * 60 * 24));

            let timeString = '';
            if (days > 0) timeString += days + 'd ';
            if (hours > 0 || days > 0) timeString += hours + 'h ';
            timeString += minutes + 'm ' + seconds + 's';

            timerElement.textContent = timeString;
        } else {
            // Timer finished
            timerElement.textContent = 'Zakończono!';
            timerElement.classList.add('finished');
            // Optionally, trigger a page reload or update specific sections
            // console.log('Timer finished for:', timerElement.dataset.itemDescription);
             // Simple approach: reload the page after a short delay
             setTimeout(() => { location.reload(); }, 2000);
        }
    });
}

// Update timers every second
setInterval(updateTimers, 1000);

// Initial update
updateTimers();

// Handle building action button clicks (using event delegation)
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('building-action-button')) {
        event.preventDefault();
        const button = event.target;
        const villageBuildingId = button.dataset.villageBuildingId;
        const buildingInternalName = button.dataset.buildingInternalName;

        // Redirect or show modal/ AJAX content based on building type
        // For now, let's redirect to a generic action page with parameters
        window.location.href = `get_building_action.php?building_id=${villageBuildingId}&building_type=${buildingInternalName}`;
    }
});

// Optional: Add AJAX handling for forms (rename village, recruit units, start research, send resources) to avoid full page reloads
// This would involve preventing default form submission and using fetch API or XMLHttpRequest

</script>
