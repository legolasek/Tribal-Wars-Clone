<?php
require '../init.php';
validateCSRF();

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$conn = $GLOBALS['conn']; // Use global connection from init.php
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';

// Instantiate managers
$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Obsługa anulowania ataku
if (isset($_POST['cancel_attack']) && isset($_POST['attack_id'])) {
    $attack_id = (int)$_POST['attack_id'];
    
    $result = $battleManager->cancelAttack($attack_id, $user_id);
    
    if ($result['success']) {
        $message = $result['message'];
        $message_type = 'success';
    } else {
        $message = $result['error'];
        $message_type = 'error';
    }
    
    // Przekierowanie z komunikatem
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    
    // Jeśli żądanie AJAX, zwróć JSON
    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
        echo json_encode($result);
        exit();
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Sprawdź ID wioski źródłowej
if (!isset($_GET['source_village']) && !isset($_POST['source_village'])) {
    // Pobierz pierwszą wioskę użytkownika jako domyślną
    $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['message'] = "Nie masz jeszcze żadnej wioski.";
        $_SESSION['message_type'] = "error";
        header("Location: ../game/game.php");
        exit();
    }
    
    $row = $result->fetch_assoc();
    $source_village_id = $row['id'];
    $stmt->close();
} else {
    $source_village_id = isset($_GET['source_village']) ? (int)$_GET['source_village'] : (int)$_POST['source_village'];
    
    // Sprawdź, czy wioska należy do użytkownika
    $stmt = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $source_village_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $_SESSION['message'] = "Nie masz dostępu do tej wioski.";
        $_SESSION['message_type'] = "error";
        header("Location: ../game/game.php");
        exit();
    }
    $stmt->close();
}

// Pobierz jednostki dostępne w wiosce źródłowej
$units = $unitManager->getVillageUnits($source_village_id);

// Obsługa wysyłania ataku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attack'])) {
    $target_village_id = (int)$_POST['target_village'];
    $attack_type = $_POST['attack_type'];
    $target_building = !empty($_POST['target_building']) ? $_POST['target_building'] : null;
    
    // Sprawdź, czy wybrano typ ataku
    if (!in_array($attack_type, ['attack', 'raid', 'support'])) {
        $message = "Wybierz poprawny typ ataku.";
        $message_type = "error";
    } else {
        // Zbierz dane o wysyłanych jednostkach
        $units_sent = [];
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'unit_') === 0 && (int)$value > 0) {
                $unit_id = (int)substr($key, 5);
                $units_sent[$unit_id] = (int)$value;
            }
        }
        
        // Sprawdź, czy wysyłane są jakiekolwiek jednostki
        if (empty($units_sent)) {
            $message = "Musisz wysłać co najmniej jedną jednostkę.";
            $message_type = "error";
        } else {
            // Wyślij atak
            $result = $battleManager->sendAttack($source_village_id, $target_village_id, $units_sent, $attack_type, $target_building);
            
            if ($result['success']) {
                $message = $result['message'];
                $message_type = "success";
                
                // Odśwież listę jednostek
                $units = $unitManager->getVillageUnits($source_village_id);
            } else {
                $message = $result['error'];
                $message_type = "error";
            }
        }
    }
    
    // Jeśli żądanie AJAX, zwróć JSON
    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
        echo json_encode([
            'success' => ($message_type == 'success'),
            'message' => $message,
            'units' => $units
        ]);
        exit();
    }
}

// Pobierz informacje o wiosce źródłowej
$source_village = $villageManager->getVillageDetails($source_village_id);

// Pobierz wioski docelowe (wszystkie wioski poza własnymi)
$stmt = $conn->prepare("
    SELECT v.id, v.name, v.x_coord, v.y_coord, u.username 
    FROM villages v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.user_id != ?
    ORDER BY (POW(v.x_coord - ?, 2) + POW(v.y_coord - ?, 2)) ASC
    LIMIT 100
");
$stmt->bind_param("iii", $user_id, $source_village['x_coord'], $source_village['y_coord']);
$stmt->execute();
$target_villages_result = $stmt->get_result();
$target_villages = [];
while ($row = $target_villages_result->fetch_assoc()) {
    // Oblicz odległość
    $distance = sqrt(pow($row['x_coord'] - $source_village['x_coord'], 2) + pow($row['y_coord'] - $source_village['y_coord'], 2));
    $row['distance'] = round($distance, 2);
    $target_villages[] = $row;
}
$stmt->close();

// Pobierz aktywne ataki wychodzące
$outgoing_attacks = $battleManager->getOutgoingAttacks($source_village_id);

// Pobierz aktywne ataki przychodzące
$incoming_attacks = $battleManager->getIncomingAttacks($source_village_id);

// Pobierz wszystkie wioski użytkownika
$stmt = $conn->prepare("SELECT id, name FROM villages WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_villages_result = $stmt->get_result();
$user_villages = [];
while ($row = $user_villages_result->fetch_assoc()) {
    $user_villages[] = $row;
}
$stmt->close();

// Get all building types for the catapult target dropdown
$all_buildings = $buildingConfigManager->getAllBuildingConfigs();

$is_ajax = isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1;

// Fetch target village details if provided
$target_village_id = $_GET['target_village_id'] ?? null;

if (!$is_ajax) {
    $database->closeConnection();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wysyłanie ataków - Tribal Wars</title>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <style>
        .attack-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .available-units {
            background-color: var(--beige-light);
            padding: 15px;
            border-radius: var(--border-radius-small);
            border: 1px solid var(--beige-darker);
            margin-bottom: 15px;
        }
        .attack-options {
            background-color: var(--beige-light);
            padding: 15px;
            border-radius: var(--border-radius-small);
            border: 1px solid var(--beige-darker);
        }
        .unit-selector {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .unit-selector img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        .unit-selector input {
            width: 70px;
            padding: 5px;
            border: 1px solid var(--beige-darker);
            border-radius: var(--border-radius-small);
        }
        .attack-type-selector {
            margin-bottom: 15px;
        }
        .target-selector {
            margin-bottom: 15px;
        }
        .attack-submit {
            grid-column: span 2;
            text-align: center;
        }
        
        .active-attacks {
            margin-top: 30px;
        }
        .attack-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .attack-card {
            background-color: var(--beige-light);
            border-radius: var(--border-radius-small);
            border: 1px solid var(--beige-darker);
            padding: 15px;
        }
        .attack-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--brown-primary);
        }
        .attack-card .attack-target {
            font-weight: bold;
        }
        .attack-card .attack-time {
            color: var(--brown-dark);
            font-style: italic;
        }
        .attack-card .attack-units {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .attack-card .unit-count {
            display: inline-flex;
            align-items: center;
            background-color: rgba(255,255,255,0.5);
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.9em;
        }
        .attack-card .unit-count img {
            width: 20px;
            height: 20px;
            margin-right: 5px;
        }
        .attack-card .attack-actions {
            margin-top: 15px;
            text-align: right;
        }
        
        .tab-navigation {
            display: flex;
            border-bottom: 2px solid var(--beige-darker);
            margin-bottom: 20px;
        }
        .tab-link {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            color: var(--brown-dark);
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .tab-link.active {
            color: var(--brown-primary);
            border-bottom-color: var(--brown-primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Modyfikacje dla responsywności */
        @media (max-width: 768px) {
            .attack-form {
                grid-template-columns: 1fr;
            }
            .attack-submit {
                grid-column: 1;
            }
        }
    </style>
</head>
<body>
    <div id="game-container">
    <?php if (!$is_ajax): ?>
        <header id="main-header">
            <div class="header-title">
                <span class="game-logo">⚔️</span>
                <span>Wysyłanie wojsk</span>
            </div>
            <div class="header-user">Witaj, <b><?php echo htmlspecialchars($username); ?></b></div>
        </header>
        
        <div id="main-content">
            <div id="sidebar">
                <ul>
                    <li><a href="game.php">Przegląd wioski</a></li>
                    <li><a href="map.php">Mapa</a></li>
                    <li><a href="attack.php" class="active">Atak</a></li>
                    <li><a href="reports.php">Raporty</a></li>
                    <li><a href="player.php">Profil gracza</a></li>
                    <li><a href="logout.php">Wyloguj</a></li>
                </ul>
            </div>
            
            <main>
                <h2>Wysyłanie wojsk</h2>
                
                <?php if (isset($message)): ?>
                    <p class="<?php echo $message_type; ?>-message"><?php echo $message; ?></p>
                <?php elseif (isset($_SESSION['message'])): ?>
                    <p class="<?php echo $_SESSION['message_type']; ?>-message"><?php echo $_SESSION['message']; ?></p>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <!-- Wybór wioski -->
                <div class="village-selector">
                    <form method="get" action="attack.php">
                        <label for="source_village">Wybierz wioskę:</label>
                        <select name="source_village" id="source_village" onchange="this.form.submit()">
                            <?php foreach ($user_villages as $village): ?>
                                <option value="<?php echo $village['id']; ?>" <?php echo ($village['id'] == $source_village_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($village['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <!-- Nawigacja zakładek -->
                <div class="tab-navigation">
                    <button class="tab-link active" data-tab="send-attack">Wyślij atak</button>
                    <button class="tab-link" data-tab="outgoing-attacks">Wychodzące (<?php echo count($outgoing_attacks); ?>)</button>
                    <button class="tab-link" data-tab="incoming-attacks">Przychodzące (<?php echo count($incoming_attacks); ?>)</button>
                </div>
                
                <!-- Zakładka wysyłania ataku -->
                <div id="send-attack" class="tab-content active">
                    <?php if (empty($units)): ?>
                        <p class="error-message">Nie masz żadnych jednostek w tej wiosce.</p>
                    <?php elseif (empty($target_villages)): ?>
                        <p class="error-message">Brak dostępnych wiosek do ataku.</p>
                    <?php else: ?>
                        <form method="post" action="attack.php" id="attack-form">
                            <input type="hidden" name="source_village" value="<?php echo $source_village_id; ?>">
                            
                            <div class="attack-form">
                                <div class="available-units">
                                    <h3>Dostępne jednostki</h3>
                                    <?php foreach ($units as $unit): ?>
                                        <div class="unit-selector">
                                            <img src="../img/ds_graphic/unit/<?= $unit['internal_name'] ?>.png" alt="<?= $unit['name_pl'] ?>">
                                            <span><?= $unit['name_pl'] ?> (Dostępnych: <?= $unit['count'] ?>)</span>
                                            <input type="number" name="unit_<?= $unit['unit_type_id'] ?>" value="0" min="0" max="<?= $unit['count'] ?>" data-internal-name="<?= htmlspecialchars($unit['internal_name']) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div style="margin-top: 15px;">
                                        <button type="button" id="select-all">Zaznacz wszystkie</button>
                                        <button type="button" id="select-none">Odznacz wszystkie</button>
                                    </div>
                                </div>
                                
                                <div class="attack-options">
                                    <div class="attack-type-selector">
                                        <h3>Typ ataku</h3>
                                        <label>
                                            <input type="radio" name="attack_type" value="attack" checked> Atak (normalne)
                                        </label><br>
                                        <label>
                                            <input type="radio" name="attack_type" value="raid"> Grabież (tylko zasoby)
                                        </label><br>
                                        <label>
                                            <input type="radio" name="attack_type" value="support"> Wsparcie (dla sojusznika)
                                        </label>
                                    </div>
                                    
                                    <div class="target-selector">
                                        <h3>Wybierz cel</h3>
                                        <select name="target_village" required>
                                            <option value="">-- Wybierz wioskę --</option>
                                            <?php foreach ($target_villages as $village): ?>
                                                <option value="<?php echo $village['id']; ?>" <?php if (isset($target_village_id) && $village['id'] == $target_village_id) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($village['name']); ?> (<?php echo $village['x_coord']; ?>|<?php echo $village['y_coord']; ?>) - 
                                                    <?php echo htmlspecialchars($village['username']); ?> - 
                                                    Odl: <?php echo $village['distance']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="target-selector" id="catapult-target-selector" style="display: none;">
                                        <h3>Cel dla katapult</h3>
                                        <select name="target_building">
                                            <option value="">-- Losowy --</option>
                                            <?php foreach ($all_buildings as $building): ?>
                                                <option value="<?php echo htmlspecialchars($building['internal_name']); ?>">
                                                    <?php echo htmlspecialchars($building['name_pl']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="attack-submit">
                                    <button type="submit" name="attack" class="styled-btn">Wyślij wojska</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <!-- Zakładka wychodzących ataków -->
                <div id="outgoing-attacks" class="tab-content">
                    <h3>Wychodzące ataki</h3>
                    
                    <?php if (empty($outgoing_attacks)): ?>
                        <p>Brak wychodzących ataków.</p>
                    <?php else: ?>
                        <div class="attack-list">
                            <?php foreach ($outgoing_attacks as $attack): ?>
                                <div class="attack-card">
                                    <h4>
                                        <?php 
                                        switch ($attack['attack_type']) {
                                            case 'attack': echo '⚔️ Atak'; break;
                                            case 'raid': echo '💰 Grabież'; break;
                                            case 'support': echo '🛡️ Wsparcie'; break;
                                        }
                                        ?>
                                    </h4>
                                    <div class="attack-target">
                                        Cel: <?php echo htmlspecialchars($attack['target_village_name']); ?> (<?php echo $attack['target_x']; ?>|<?php echo $attack['target_y']; ?>)
                                    </div>
                                    <div class="attack-owner">
                                        Właściciel: <?php echo htmlspecialchars($attack['defender_name']); ?>
                                    </div>
                                    <div class="attack-time">
                                        Dotarcie: <?php echo $attack['formatted_remaining_time']; ?>
                                    </div>
                                    
                                    <div class="attack-units">
                                        <?php foreach ($attack['units'] as $unit): ?>
                                            <span class="unit-count">
                                                <img src="../img/units/<?php echo htmlspecialchars($unit['internal_name']); ?>.png" alt="<?php echo htmlspecialchars($unit['name_pl']); ?>">
                                                <?php echo $unit['count']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="attack-actions">
                                        <form method="post" action="attack.php" class="cancel-attack-form">
                                            <input type="hidden" name="attack_id" value="<?php echo $attack['id']; ?>">
                                            <button type="submit" name="cancel_attack" class="cancel-button">Anuluj</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Zakładka przychodzących ataków -->
                <div id="incoming-attacks" class="tab-content">
                    <h3>Przychodzące ataki</h3>
                    
                    <?php if (empty($incoming_attacks)): ?>
                        <p>Brak przychodzących ataków.</p>
                    <?php else: ?>
                        <div class="attack-list">
                            <?php foreach ($incoming_attacks as $attack): ?>
                                <div class="attack-card">
                                    <h4>
                                        <?php 
                                        switch ($attack['attack_type']) {
                                            case 'attack': echo '⚔️ Atak'; break;
                                            case 'raid': echo '💰 Grabież'; break;
                                            case 'support': echo '🛡️ Wsparcie'; break;
                                        }
                                        ?>
                                    </h4>
                                    <div class="attack-target">
                                        Z: <?php echo htmlspecialchars($attack['source_village_name']); ?> (<?php echo $attack['source_x']; ?>|<?php echo $attack['source_y']; ?>)
                                    </div>
                                    <div class="attack-owner">
                                        Atakujący: <?php echo htmlspecialchars($attack['attacker_name']); ?>
                                    </div>
                                    <div class="attack-time">
                                        Dotarcie: <?php echo $attack['formatted_remaining_time']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php if (!$is_ajax): ?>
            </main>
        </div>
    </div>
            <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Obsługa zakładek
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Deaktywuj wszystkie zakładki
                    tabLinks.forEach(el => el.classList.remove('active'));
                    tabContents.forEach(el => el.classList.remove('active'));
                    
                    // Aktywuj wybraną zakładkę
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Przyciski zaznaczania jednostek
            const selectAllBtn = document.getElementById('select-all');
            const selectNoneBtn = document.getElementById('select-none');
            
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.unit-selector input[type="number"]').forEach(input => {
                        input.value = input.max;
                    });
                });
            }
            
            if (selectNoneBtn) {
                selectNoneBtn.addEventListener('click', function() {
                    document.querySelectorAll('.unit-selector input[type="number"]').forEach(input => {
                        input.value = 0;
                        // Manually trigger the input event to update the catapult selector
                        input.dispatchEvent(new Event('input'));
                    });
                });
            }

            // Catapult target selector logic
            const catapultTargetSelector = document.getElementById('catapult-target-selector');
            const unitInputs = document.querySelectorAll('.unit-selector input[type="number"]');

            function checkCatapultSelection() {
                let catapultsSelected = false;
                unitInputs.forEach(input => {
                    if (input.dataset.internalName === 'catapult' && parseInt(input.value, 10) > 0) {
                        catapultsSelected = true;
                    }
                });
                catapultTargetSelector.style.display = catapultsSelected ? 'block' : 'none';
            }

            unitInputs.forEach(input => {
                input.addEventListener('input', checkCatapultSelection);
            });

            // Initial check in case the form is pre-filled
            checkCatapultSelection();
            
            // Obsługa formularza ataku przez AJAX
            const attackForm = document.getElementById('attack-form');
            
            if (attackForm) {
                attackForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('ajax', 1);
                    
                    fetch('attack.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Wyświetl komunikat sukcesu
                            const successMessage = document.createElement('p');
                            successMessage.className = 'success-message';
                            successMessage.textContent = data.message;
                            
                            // Dodaj komunikat przed formularzem
                            attackForm.parentNode.insertBefore(successMessage, attackForm);
                            
                            // Odśwież stronę po 2 sekundach
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // Wyświetl komunikat błędu
                            const errorMessage = document.createElement('p');
                            errorMessage.className = 'error-message';
                            errorMessage.textContent = data.message;
                            
                            // Dodaj komunikat przed formularzem
                            attackForm.parentNode.insertBefore(errorMessage, attackForm);
                            
                            // Usuń komunikat po 3 sekundach
                            setTimeout(() => {
                                errorMessage.remove();
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Błąd wysyłania ataku:', error);
                        alert('Wystąpił błąd podczas wysyłania ataku. Spróbuj ponownie później.');
                    });
                });
            }
            
            // Obsługa anulowania ataku przez AJAX
            const cancelForms = document.querySelectorAll('.cancel-attack-form');
            
            cancelForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('Czy na pewno chcesz anulować ten atak? Jednostki wrócą do wioski źródłowej.')) {
                        return;
                    }
                    
                    const formData = new FormData(this);
                    formData.append('ajax', 1);
                    
                    fetch('attack.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Ukryj kartę ataku
                            this.closest('.attack-card').style.display = 'none';
                            
                            // Wyświetl komunikat sukcesu
                            const successMessage = document.createElement('p');
                            successMessage.className = 'success-message';
                            successMessage.textContent = data.message;
                            
                            document.querySelector('#outgoing-attacks').insertBefore(
                                successMessage, 
                                document.querySelector('#outgoing-attacks h3').nextSibling
                            );
                            
                            // Odśwież stronę po 2 sekundach
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // Wyświetl komunikat błędu
                            alert(data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Błąd anulowania ataku:', error);
                        alert('Wystąpił błąd podczas anulowania ataku. Spróbuj ponownie później.');
                    });
                });
            });
        });
    </script>
</body>
</html> 