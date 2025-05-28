<?php
require 'init.php';
require_once __DIR__ . '/lib/VillageManager.php'; // VillageManager jest potrzebny
require_once __DIR__ . '/lib/BuildingManager.php'; // Poprawiona ścieżka
require_once 'lib/BattleManager.php';

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Usunięto zduplikowane połączenie z bazą danych
// $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// $conn = $database->getConnection(); // Używamy $conn z init.php

$villageManager = new VillageManager($conn); // Utworzenie instancji VillageManager
$battleManager = new BattleManager($conn, $villageManager);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Przetwórz zakończone ataki (upewnij się, że BattleManager używa $conn z init.php)
$battleManager->processCompletedAttacks($user_id);

// === Paginacja ===
$reportsPerPage = 20; // Liczba raportów na stronę
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $reportsPerPage;
$totalReports = 0;
$totalPages = 1;

// Pobierz liczbę raportów dla paginacji (tylko raporty walk na razie)
$countQuery = "SELECT COUNT(*) as total 
             FROM battle_reports br
             JOIN villages sv ON br.source_village_id = sv.id
             JOIN villages tv ON br.target_village_id = tv.id
             WHERE sv.user_id = ? OR tv.user_id = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("ii", $user_id, $user_id);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalReports = $countResult['total'];
$countStmt->close();

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
    }
}

// Pobierz raporty z walk, w których użytkownik brał udział (z paginacją)
$stmt = $conn->prepare("
    SELECT 
        br.report_id, br.attacker_won, br.battle_time as created_at,
        sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
        tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
        u_attacker.username as attacker_name, u_defender.username as defender_name,
        r.is_read -- Pobieramy status odczytania z tabeli reports
    FROM battle_reports br
    JOIN villages sv ON br.source_village_id = sv.id
    JOIN villages tv ON br.target_village_id = tv.id
    JOIN users u_attacker ON sv.user_id = u_attacker.id
    JOIN users u_defender ON tv.user_id = u_defender.id
    JOIN reports r ON br.report_id = r.id AND r.user_id = ? -- Łączymy z tabelą reports
    WHERE sv.user_id = ? OR tv.user_id = ?
    ORDER BY br.battle_time DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $reportsPerPage, $offset); // Zmieniamy parametry bindowania
$stmt->execute();
$reports_result = $stmt->get_result();

$reports = [];
while ($row = $reports_result->fetch_assoc()) {
    // Określ, czy użytkownik był atakującym czy broniącym
    $row['is_attacker'] = ($row['source_user_id'] == $user_id);
    
    // Formatuj datę
    $row['formatted_date'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
    
    $reports[] = $row;
}
$stmt->close();

// Usunięto zduplikowane zamykanie połączenia
// $database->closeConnection();

$pageTitle = 'Raporty z walk'; // Zmienimy na ogólne 'Raporty' gdy dodamy inne typy
require 'header.php';
?>

    <div id="game-container">
    <!-- Game header with resources -->
        <header id="main-header">
            <div class="header-title">
            <span class="game-logo">📄</span> <!-- Ikona dla raportów -->
            <span>Raporty</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?>
            <div class="header-links">
                <a href="game.php">Przegląd</a> | 
                <a href="logout.php">Wyloguj</a>
            </div>
        </div>
        </header>
        
        <div id="main-content">
        <!-- Sidebar navigation -->
        <nav id="sidebar">
                <ul>
                <li><a href="game.php">Przegląd</a></li>
                    <li><a href="map.php">Mapa</a></li>
                    <li><a href="reports.php" class="active">Raporty</a></li>
                <li><a href="messages.php">Wiadomości</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="settings.php">Ustawienia</a></li>
                    <li><a href="logout.php">Wyloguj</a></li>
                </ul>
        </nav>
            
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
                                    <?= $report['attacker_won'] ? 'Zwycięstwo' : 'Porażka' ?> - <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) atakuje <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['target_y'] ?>)
                                    </div>
                                    <div class="report-villages">
                                     Od: <?= htmlspecialchars($report['attacker_name']) ?> (<?= htmlspecialchars($report['source_village_name']) ?>) Do: <?= htmlspecialchars($report['defender_name']) ?> (<?= htmlspecialchars($report['target_village_name']) ?>)
                                    </div>
                                <div class="report-date">
                                    <?= $report['formatted_date'] ?>
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
                                                      <td class="unit-name"><img src="img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
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
                                      
                                      <h4>Jednostki obecne</h4>
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
                                                      <td class="unit-name"><img src="img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
                                                      <td><?= $count ?></td>
                                                      <td class="unit-lost"><?= $report_details['defender_losses'][$unit_type] ?? 0 ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                      <?php if (!empty($report_details['haul'])): ?>
                                      <h4>Zdobyte surowce</h4>
                                      <div>
                                          Drewno: <?= $report_details['haul']['wood'] ?? 0 ?><br>
                                          Glina: <?= $report_details['haul']['clay'] ?? 0 ?><br>
                                          Żelazo: <?= $report_details['haul']['iron'] ?? 0 ?>
                                      </div>
                                      <?php endif; ?>

                                      <?php if (isset($report_details['ram_level_change']) && $report_details['ram_level_change'] > 0): ?>
                                      <h4>Zmiany w wiosce obrońcy</h4>
                                      <div>
                                          Mury obronne zniszczone o <?= $report_details['ram_level_change'] ?> poziomów.
                                      </div>
                                <?php endif; ?>
                            </div>
                             </div>
                             
                             <div class="report-footer">
                                 Czas bitwy: <?= date('d.m.Y H:i:s', strtotime($report_details['battle_time'])) ?><br>
                                 Czas raportu: <?= date('d.m.Y H:i:s', strtotime($report_details['created_at'])) ?>
                             </div>
                             
                        <?php else: ?>
                                <p>Wybierz raport z listy, aby zobaczyć szczegóły.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Paginacja -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="reports.php?page=<?= $currentPage - 1 ?>" class="page-link">Poprzednia</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="reports.php?page=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="reports.php?page=<?= $currentPage + 1 ?>" class="page-link">Następna</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="no-reports">Brak raportów w tej kategorii.</p>
            <?php endif; ?>
            
            </main>
        </div>
    </div>

<?php require 'footer.php'; ?>

<script>
// Skrypt do ładowania szczegółów raportu za pomocą AJAX
document.addEventListener('DOMContentLoaded', function() {
    const reportsList = document.querySelector('.reports-list');
    const reportDetailsContainer = document.getElementById('report-details');
    
    if (!reportsList || !reportDetailsContainer) return;

    reportsList.addEventListener('click', function(event) {
        const reportItem = event.target.closest('.report-item');
        if (!reportItem) return;

        const reportId = reportItem.dataset.reportId;
        if (!reportId) return;

        // Usuń klasę 'active' z poprzedniego aktywnego elementu i dodaj do klikniętego
        const activeItem = reportsList.querySelector('.report-item.active');
        if (activeItem) {
            activeItem.classList.remove('active');
        }
        reportItem.classList.add('active');
        
        // Usuń klasę 'unread' po kliknięciu
        reportItem.classList.remove('unread');

        // Pokaż loader w sekcji szczegółów
        reportDetailsContainer.innerHTML = '<div class="loader">Ładowanie raportu...</div>'; // Dodaj prosty loader

        fetch('reports.php?report_id=' + reportId, { // Wysłanie żądania GET z ID raportu
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Nagłówek informujący, że to żądanie AJAX
            }
        })
        .then(response => response.text()) // Oczekujemy samego HTML
        .then(html => {
            // Wstrzyknij pobrany HTML do kontenera szczegółów
            reportDetailsContainer.innerHTML = html;
             // Zaktualizuj licznik nieprzeczytanych raportów w menu/zakładce, jeśli taki istnieje
             updateUnreadReportsCount(); // Funkcja do zaimplementowania
        })
        .catch(error => {
            console.error('Błąd ładowania raportu:', error);
            reportDetailsContainer.innerHTML = '<p class="error-message">Nie udało się załadować raportu.</p>';
        });
    });

     // Funkcja do aktualizacji licznika nieprzeczytanych raportów
     function updateUnreadReportsCount() {
         // Implementacja: Pobierz liczbę nieprzeczytanych raportów (np. przez AJAX) i zaktualizuj element na stronie.
         // Można to zrobić analogicznie do pobierania liczby nieprzeczytanych wiadomości.
     }
});

// Style dla loadera (dodaj do main.css)
/*
.report-details .loader {
    text-align: center;
    padding: 20px;
    font-style: italic;
}
*/

/* Style paginacji (skopiowane z messages.php, jeśli spójne) */
/*
.pagination {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-md);
    gap: var(--spacing-sm);
}

.pagination .page-link {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
    text-decoration: none;
    color: var(--brown-primary);
    background-color: var(--beige-light);
    transition: background-color var(--transition-fast), border-color var(--transition-fast);
}

.pagination .page-link:hover {
    background-color: var(--beige-dark);
    border-color: var(--brown-primary);
}

.pagination .page-link.active {
    background-color: var(--brown-primary);
    color: white;
    border-color: var(--brown-primary);
    cursor: default;
}
*/

</style> 