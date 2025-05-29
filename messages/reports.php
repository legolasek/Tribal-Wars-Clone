<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/BuildingManager.php'; // Zaktualizowana ścieżka
require_once __DIR__ . '/../lib/managers/BattleManager.php'; // Zaktualizowana ścieżka

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Sprawdź, czy to żądanie AJAX o szczegóły raportu
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    
    $villageManager = new VillageManager($conn); // Utworzenie instancji VillageManager
    $battleManager = new BattleManager($conn, $villageManager);
    
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit(); // Zakończ wykonywanie skryptu po zwróceniu JSON
}

// Usunięto zduplikowane połączenie z bazą danych
// $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// $conn = $database->getConnection(); // Używamy $conn z init.php

$villageManager = new VillageManager($conn); // Utworzenie instancji VillageManager
$battleManager = new BattleManager($conn, $villageManager);

$username = $_SESSION['username'];

// Przetwórz zakończone ataki (upewnij się, że BattleManager używa $conn z init.php)
$battleManager->processCompletedAttacks($user_id);

// === Paginacja ===
$reportsPerPage = 20; // Liczba raportów na stronę
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $reportsPerPage;
$totalReports = 0;
$totalPages = 1;

// Pobierz liczbę raportów dla paginacji przy użyciu BattleManager
$totalReports = $battleManager->getTotalBattleReportsForUser($user_id);

if ($totalReports > 0) {
    $totalPages = ceil($totalReports / $reportsPerPage);

    // Upewnij się, że aktualna strona nie przekracza liczby stron
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $reportsPerPage;
    }
}

// Sprawdź, czy jest żądanie szczegółów raportu (może pozostać na razie)
$report_details = null;
if (isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    if ($result['success']) {
        $report_details = $result['report'];
        
        // Oznacz raport jako przeczytany
        // Ta logika oznaczania jako przeczytany może zostać zintegrowana w getBattleReport lub zmieniona na AJAX
        $stmt_read = $conn->prepare("
            UPDATE battle_reports 
            SET is_read_by_attacker = 1 
            WHERE id = ? AND source_village_id IN (SELECT id FROM villages WHERE user_id = ?)
        ");
        $stmt_read->bind_param("ii", $report_id, $user_id);
        $stmt_read->execute();
        $stmt_read->close();
        
        $stmt_read = $conn->prepare("
            UPDATE battle_reports 
            SET is_read_by_defender = 1 
            WHERE id = ? AND target_village_id IN (SELECT id FROM villages WHERE user_id = ?)
        ");
        $stmt_read->bind_param("ii", $report_id, $user_id);
        $stmt_read->execute();
        $stmt_read->close();
    } else {

    }
}

// Pobierz raporty z walk, w których użytkownik brał udział (z paginacją) przy użyciu BattleManager
$reports = $battleManager->getBattleReportsForUser($user_id, $reportsPerPage, $offset);

// Usunięto zduplikowane zamykanie połączenia
// $database->closeConnection();

$pageTitle = 'Raporty z walk'; // Zmienimy na ogólne 'Raporty' gdy dodamy inne typy
require '../header.php';
?>

    <div id="game-container">
    <!-- Game header with resources -->
        <header id="main-header">
            <div class="header-title">
            <span class="game-logo">📄</span> <!-- Ikona dla raportów -->
            <span>Raporty</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?><br>
            <?php if (!empty($firstVidData)): ?>
                <span class="village-name-display" data-village-id="<?= $firstVidData['id'] ?>"><?= htmlspecialchars($firstVidData['name']) ?> (<?= $firstVidData['x_coord'] ?>|<?= $firstVidData['y_coord'] ?>)</span>
            <?php else: ?>
                <span class="village-name-display">Brak wioski</span>
            <?php endif; ?>
        </div>
        </header>
        
        <div id="main-content">
        <!-- Sidebar navigation -->
        
            
            <main>
                <h2>Raporty z walk</h2>
                
            <?php if (isset($_GET['action_success'])): ?>
                <div class="success-message">Operacja wykonana pomyślnie.</div>
            <?php endif; ?>
            
            <!-- Tutaj w przyszłości mogą pojawić się zakładki dla różnych typów raportów -->
            <div class="reports-tabs">
                 <a href="reports.php" class="tab active">Raporty z walk</a>
                 <!-- <a href="reports.php?type=trade" class="tab">Raporty handlowe</a> -->
                 <!-- <a href="reports.php?type=support" class="tab">Raporty wsparcia</a> -->
                 <!-- <a href="reports.php?type=other" class="tab">Inne raporty</a> -->
            </div>

            <?php if (!empty($reports)): ?>
                <div class="reports-container">
                    <!-- Lista raportów -->
                    <div class="reports-list">
                            <?php foreach ($reports as $report): ?>
                            <div class="report-item <?= !$report['is_read'] ? 'unread' : '' ?>" data-report-id="<?= $report['report_id'] ?>">
                                    <div class="report-title">
                                    <span class="report-icon"><?= $report['attacker_won'] ? '⚔️' : '🛡️' ?></span>
                                    <?= $report['attacker_won'] ? 'Zwycięstwo' : 'Porażka' ?> - <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) atakuje <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['y_coord'] ?>)
                                    </div>
                                    <div class="report-villages">
                                     Od: <?= htmlspecialchars($report['attacker_name']) ?> (<?= htmlspecialchars($report['source_village_name']) ?>) Do: <?= htmlspecialchars($report['defender_name']) ?> (<?= htmlspecialchars($report['target_village_name']) ?>)
                                    </div>
                                <div class="report-date">
                                    <?= date('d.m.Y H:i:s', strtotime($report['created_at'])) ?>
                                </div>
                                </div>
                            <?php endforeach; ?>
                    </div>
                    
                    <!-- Szczegóły raportu (ładowane dynamicznie lub wyświetlane po wybraniu) -->
                    <div class="report-details" id="report-details">
                        <?php if ($report_details): ?>
                             <!-- Renderowanie szczegółów raportu walki -->
                             <h3>Raport z walki #<?= $report_details['report_id'] ?></h3>
                            
                            <div class="battle-summary">
                                 <div class="battle-side <?= $report_details['attacker_won'] ? 'winner' : 'loser' ?>">
                                    <h4>Atakujący</h4>
                                     <p class="battle-village"><?= htmlspecialchars($report_details['attacker_name']) ?> z wioski <?= htmlspecialchars($report_details['source_village_name']) ?> (<?= $report_details['source_x'] ?>|<?= $report_details['source_y'] ?>)</p>
                                     <!-- Tutaj można dodać siłę ataku/obrony, jeśli jest obliczana i zapisana -->
                                      <p class="battle-strength">Siła ataku: ???</p>
                                     
                                      <h4>Jednostki wysłane</h4>
                                    <table class="units-table">
                                        <thead>
                                            <tr>
                                                <th>Jednostka</th>
                                                  <th>Ilość</th>
                                                <th>Straty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                              <?php foreach ($report_details['attacker_units'] as $unit_type => $count): ?>
                                                  <tr>
                                                      <td class="unit-name"><img src="../img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
                                                      <td><?= $count ?></td>
                                                      <td class="unit-lost"><?= $report_details['attacker_losses'][$unit_type] ?? 0 ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                 </div>
                                 
                                 <div class="battle-side <?= $report_details['attacker_won'] ? 'loser' : 'winner' ?>">
                                     <h4>Obrońca</h4>
                                     <p class="battle-village"><?= htmlspecialchars($report_details['defender_name']) ?> z wioski <?= htmlspecialchars($report_details['target_village_name']) ?> (<?= $report_details['target_x'] ?>|<?= $report_details['target_y'] ?>)</p>
                                     <!-- Tutaj można dodać siłę ataku/obrony, jeśli jest obliczana i zapisana -->
                                      <p class="battle-strength">Siła obrony: ???</p>
                                      
                                      <h4>Jednostki obecne (po bitwie)</h4>
                                    <table class="units-table">
                                        <thead>
                                            <tr>
                                                <th>Jednostka</th>
                                                  <th>Ilość</th>
                                                <th>Straty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                             <?php foreach ($report_details['defender_units'] as $unit_type => $count): ?>
                                                  <tr>
                                                      <td class="unit-name"><img src="../img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
                                                      <td><?= $count ?></td>
                                                      <td class="unit-lost"><?= $report_details['defender_losses'][$unit_type] ?? 0 ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                 </div>
                                 
                                 <div class="battle-loot">
                                     <h4>Łup:</h4>
                                     <p>Drewno: <?= $report_details['loot_wood'] ?? 0 ?>, Glina: <?= $report_details['loot_clay'] ?? 0 ?>, Żelazo: <?= $report_details['loot_iron'] ?? 0 ?></p>
                                 </div>
                                 
                                 <?php if ($report_details['ram_level_change'] > 0): ?>
                 <div class="village-changes">
                      <h4>Zmiany w wiosce obrońcy</h4>
                      <p>Mury obronne zniszczone o <?= $report_details['ram_level_change'] ?> poziom<?= $report_details['ram_level_change'] > 1 ? 'y' : '' ?>.</p>
                 </div>
                 <?php endif; ?>
            </div>
             
             <div class="report-footer">
                 Czas bitwy: <?= $report_details['formatted_date'] ?>
             </div>

                        <?php else: ?>
                            <p>Wybierz raport z listy, aby zobaczyć szczegóły.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Paginacja -->
                <div class="pagination">
                     <?php if ($totalPages > 1): ?>
                        Strona: 
                         <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="reports.php?page=<?= $i ?>" class="page-link <?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                         <?php endfor; ?>
                        
                         <?php if ($currentPage < $totalPages): ?>
                             <a href="reports.php?page=<?= $currentPage + 1 ?>" class="page-link">Następna</a>
                         <?php endif; ?>
                     <?php endif; ?>
                </div>

            <?php else: ?>
                <p>Brak raportów walki.</p>
            <?php endif; ?>
            </main>
        </div>
    </div>

<?php require '../footer.php'; ?>

<script>\n    // js/reports.js - embedded\n\n    document.addEventListener(\'DOMContentLoaded\', function() {\n        const reportsList = document.querySelector(\'.reports-list\');\n        const reportDetailsArea = document.getElementById(\'report-details\'); // Assuming this div exists in reports.php\n\n        if (reportsList && reportDetailsArea) {\n            // Event listener for clicking on report items in the list\n            reportsList.addEventListener(\'click\', function(event) {\n                const reportItem = event.target.closest(\'.report-item\');\n                if (reportItem) {\n                    const reportId = reportItem.dataset.reportId;\n\n                    if (reportId) {\n                        // Load report details\n                        loadReportDetails(reportId);\n                    }\n                }\n            });\n\n            // Function to load report details via AJAX\n            function loadReportDetails(reportId) {\n                // Show a loading indicator (optional)\n                reportDetailsArea.innerHTML = \'<p>Ładowanie raportu...</p>\';\n                reportDetailsArea.classList.add(\'loading\');\n\n                // Fetch report details from reports.php itself (it handles AJAX GET for details)\n                fetch(`reports.php?report_id=${reportId}`, {\n                    method: \'GET\',\n                    headers: {\n                        \'X-Requested-With\': \'XMLHttpRequest\' // Indicate AJAX request\n                    }\n                })\n                .then(response => {\n                     if (!response.ok) {\n                         throw new Error(`HTTP error! status: ${response.status}`);\n                     }\n                     return response.json();\n                })\n                .then(data => {\n                    reportDetailsArea.classList.remove(\'loading\');\n\n                    if (data.success) {\n                        // Render report details HTML\n                        renderReportDetails(data.report);\n                         // Mark report as read in the list UI (if applicable, reports don't have read status yet)\n                        // const reportItem = reportsList.querySelector(\`.report-item[data-report-id=\'${reportId}\']\`);\n                        // if (reportItem) { reportItem.classList.remove(\'unread\'); }\n\n                    } else {\n                        // Handle error loading details\n                        reportDetailsArea.innerHTML = `<p class=\"error-message\">${data.message || \'Nie udało się załadować raportu.\'}</p>`;\n                        console.error(\'Error loading report details:\', data.message);\n                    }\n                })\n                .catch(error => {\n                    reportDetailsArea.classList.remove(\'loading\');\n                    reportDetailsArea.innerHTML = \'<p class=\"error-message\">Wystąpił błąd komunikacji podczas ładowania raportu.</p>\';\n                    console.error(\'Fetch error:\', error);\n                });\n            }\n\n            // Function to render report details HTML (create the HTML structure from JSON data)\n            function renderReportDetails(reportData) {\n                 // Determine winner class\n                 const attackerSideClass = reportData.winner === \'attacker\' ? \'winner\' : \'loser\';\n                 const defenderSideClass = reportData.winner === \'defender\' ? \'winner\' : \'loser\';\n\n                // Function to render units table\n                function renderUnitsTable(units) {\n                    let tableHtml = `\n                        <table class=\"units-table\">\n                            <thead>\n                                <tr>\n                                    <th>Jednostka</th>\n                                    <th>Ilość</th>\n                                    <th>Straty</th>\n                                    <th>Pozostało</th>\n                                </tr>\n                            </thead>\n                            <tbody>\n                    `;\n                    if (units && units.length > 0) {\n                         units.forEach(unit => {\n                             tableHtml += `\n                                 <tr>\n                                     <td class=\"unit-name\"><img src=\"../img/ds_graphic/unit/${unit.internal_name}.png\" alt=\"${unit.name_pl}\"> ${unit.name_pl}</td>\n                                     <td>${unit.initial_count}</td>\n                                     <td class=\"unit-lost\">${unit.lost_count}</td>\n                                     <td>${unit.remaining_count}</td>\n                                 </tr>\n                             `;\n                         });\n                    } else {\n                         tableHtml += `<tr><td colspan=\"4\">Brak jednostek</td></tr>`;\n                    }\n                    tableHtml += `\n                            </tbody>\n                        </table>\n                    `;\n                    return tableHtml;\n                }\n\n                // Parse details JSON (assuming it's a string in the reportData)\n                let details = {};\n                try {\n                    details = JSON.parse(reportData.details_json);\n                } catch (e) {\n                    console.error(\'Error parsing report details JSON:\', e);\n                    // Provide default empty structure if parsing fails\n                    details = { attacker_losses: {}, defender_losses: {}, loot: { wood: 0, clay: 0, iron: 0 }, ram_level_change: 0 };\n                }\n\n                // Basic HTML structure - adapt this to match desired look\n                let detailsHtml = `\n                    <div class=\"report-details-content\">\n                         <h3>Raport z walki #${reportData.id}</h3>\n\n                         <div class=\"battle-summary\">\n                             <div class=\"battle-side ${attackerSideClass}\">\n                                 <h4>Atakujący</h4>\n                                  <p class=\"battle-village\">${escapeHTML(reportData.attacker_name)} z wioski ${escapeHTML(reportData.source_village_name)} (${reportData.source_x}|${reportData.source_y})</p>\n                                   <p class=\"battle-strength\">Siła ataku: ${reportData.total_attack_strength}</p>\n\n                                  <h4>Jednostki atakujące</h4>\n                                  ${renderUnitsTable(reportData.attacker_units)}\n                             </div>\n\n                             <div class=\"battle-side ${defenderSideClass}\">\n                                  <h4>Obrońca</h4>\n                                   <p class=\"battle-village\">${escapeHTML(reportData.defender_name)} z wioski ${escapeHTML(reportData.target_village_name)} (${reportData.target_x}|${reportData.target_y})</p>\n                                   <p class=\"battle-strength\">Siła obrony: ${reportData.total_defense_strength}</p>\n\n                                   <h4>Jednostki obronne</h4>\n                                  ${renderUnitsTable(reportData.defender_units)}\n                             </div>\n\n                             ${reportData.winner === \'attacker\' && (details.loot.wood > 0 || details.loot.clay > 0 || details.loot.iron > 0) ? `\n                                 <div class=\"battle-loot\">\n                                     <h4>Łup:</h4>\n                                     <p>Drewno: ${details.loot.wood}, Glina: ${details.loot.clay}, Żelazo: ${details.loot.iron}</p>\n                                 </div>\n                             ` : \'\'}\n\n                             ${details.ram_level_change > 0 ? `\n                                 <div class=\"village-changes\">\n                                      <h4>Zmiany w wiosce obrońcy</h4>\n                                      <p>Mury obronne zniszczone o ${details.ram_level_change} poziom${details.ram_level_change > 1 ? \'y\' : \'\'}.</p>\n                                 </div>\n                             ` : \'\'}\n                         </div>\n\n                         <div class=\"report-footer\">\n                              Czas bitwy: ${formatDateTime(reportData.created_at)}\n                         </div>\n                    </div>\n                 `;\n\n                reportDetailsArea.innerHTML = detailsHtml;\n            }\n\n             // Helper function to format date and time (reused from messages.js)\n            function formatDateTime(datetimeString) {\n                const date = new Date(datetimeString);\n                const day = String(date.getDate()).padStart(2, \'0\');\n                const month = String(date.getMonth() + 1).padStart(2, \'0\'); // Month is 0-indexed\n                const year = date.getFullYear();\n                const hours = String(date.getHours()).padStart(2, \'0\');\n                const minutes = String(date.getMinutes()).padStart(2, \'0\');\n                return `${day}.${month}.${year} ${hours}:${minutes}`;\n            }\n\n            // Helper function to escape HTML characters to prevent XSS (reused from messages.js)\n            function escapeHTML(str) {\n                const div = document.createElement(\'div\');\n                div.appendChild(document.createTextNode(str));\n                return div.innerHTML;\n            }\n\n            // --- Bulk Actions for Reports --- (If applicable, need to add this HTML)\n            // const reportsForm = document.getElementById(\'reports-form\');\n            // const bulkActionSelect = document.getElementById(\'bulk-action\');\n            // const bulkApplyButton = document.getElementById(\'bulk-apply\');\n            // const reportCheckboxes = reportsList ? reportsList.querySelectorAll(\'.report-checkbox-input\') : [];\n\n            // if (reportsForm && bulkActionSelect && bulkApplyButton && reportCheckboxes.length > 0) {\n            //      // Add event listeners similar to messages.js bulk actions\n            // }\n\n\n            // Initial state: Check URL for a specific report ID and load it if present\n            const urlParams = new URLSearchParams(window.location.search);\n            const initialReportId = urlParams.get(\'report_id\');\n            if (initialReportId) {\n                loadReportDetails(initialReportId);\n            } else {\n                 // If no report ID in URL, show placeholder text in details area\n                 if(reportDetailsArea.innerHTML.trim() === \'<p>Wybierz raport z listy, aby zobaczyć szczegóły.</p>\' || reportDetailsArea.innerHTML.trim() === \'\') {\n                     reportDetailsArea.innerHTML = \'<p>Wybierz raport z listy, aby zobaczyć szczegóły.</p>\';\n                 }\n            }\n\n        }\n    });\n</script>\n\n<style>\n/* Style paginacji (skopiowane z messages.php, jeśli spójne) */\n/*\n.pagination {\n    display: flex;\n    justify-content: center;\n    margin-top: var(--spacing-md);\n    gap: var(--spacing-sm);\n}\n\n.pagination .page-link {\n    padding: var(--spacing-xs) var(--spacing-sm);\n    border: 1px solid var(--beige-darker);\n    border-radius: var(--border-radius-small);\n    text-decoration: none;\n    color: var(--brown-primary);\n    background-color: var(--beige-light);\n    transition: background-color var(--transition-fast), border-color var(--transition-fast);\n}\n\n.pagination .page-link:hover {\n    background-color: var(--beige-dark);\n    border-color: var(--brown-primary);\n}\n\n.pagination .page-link.active {\n    background-color: var(--brown-primary);\n    color: white;\n    border-color: var(--brown-primary);\n    cursor: default;\n}\n*/\n</style>