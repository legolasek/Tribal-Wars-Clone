<?php
require 'init.php';
require_once 'lib/VillageManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

// Obsługa różnych zakładek wiadomości
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$validTabs = ['inbox', 'sent', 'archive'];

if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'inbox';
}

// === Paginacja ===
$messagesPerPage = 20; // Liczba wiadomości na stronę
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $messagesPerPage;
$totalMessages = 0;
$totalPages = 1;

// Obsługa masowych operacji na wiadomościach
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['message_ids'])) {
    $action = $_POST['action'];
    $message_ids = $_POST['message_ids'];
    
    if (!empty($message_ids) && is_array($message_ids)) {
        $ids = array_map('intval', $message_ids);
        $ids_str = implode(',', $ids);
        
        switch ($action) {
            case 'mark_read':
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'mark_unread':
                $stmt = $conn->prepare("UPDATE messages SET is_read = 0 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'archive':
                $stmt = $conn->prepare("UPDATE messages SET is_archived = 1 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'unarchive':
                $stmt = $conn->prepare("UPDATE messages SET is_archived = 0 WHERE id IN ($ids_str) AND receiver_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM messages WHERE id IN ($ids_str) AND (receiver_id = ? OR (sender_id = ? AND is_sender_deleted = 0))");
                $stmt->bind_param("ii", $user_id, $user_id);
                $stmt->execute();
                $stmt->close();
                break;
        }
        
        // Przekierowanie, aby odświeżyć stronę po wykonaniu akcji
        header("Location: messages.php?tab=$activeTab&action_success=1");
        exit();
    }
}

// Pobranie liczby wiadomości dla paginacji
switch ($activeTab) {
    case 'inbox':
        $countQuery = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_archived = 0";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("i", $user_id);
        break;
        
    case 'sent':
        $countQuery = "SELECT COUNT(*) as total FROM messages WHERE sender_id = ? AND is_sender_deleted = 0";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("i", $user_id);
        break;
        
    case 'archive':
        $countQuery = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_archived = 1";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("i", $user_id);
        break;
}

if (isset($countStmt)) {
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $totalMessages = $countResult['total'];
    $countStmt->close();
    
    $totalPages = ceil($totalMessages / $messagesPerPage);
    
    // Upewnij się, że aktualna strona nie przekracza liczby stron
    if ($currentPage > $totalPages && $totalPages > 0) {
        // Można przekierować lub ustawić na ostatnią stronę
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $messagesPerPage;
    }
}

// Pobranie wiadomości w zależności od aktywnej zakładki z paginacją
$messages = [];
$query = "";

switch ($activeTab) {
    case 'inbox':
        $query = "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, u.username AS sender_username, u.id AS sender_id
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.receiver_id = ? AND m.is_archived = 0
                 ORDER BY m.sent_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $messagesPerPage, $offset);
        break;
        
    case 'sent':
        $query = "SELECT m.id, m.subject, m.body, m.sent_at, 1 AS is_read, u.username AS receiver_username, u.id AS receiver_id
                 FROM messages m
                 JOIN users u ON m.receiver_id = u.id
                 WHERE m.sender_id = ? AND m.is_sender_deleted = 0
                 ORDER BY m.sent_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $messagesPerPage, $offset);
        break;
        
    case 'archive':
        $query = "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, u.username AS sender_username, u.id AS sender_id
     FROM messages m
     JOIN users u ON m.sender_id = u.id
                 WHERE m.receiver_id = ? AND m.is_archived = 1
                 ORDER BY m.sent_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $messagesPerPage, $offset);
        break;
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Pobierz liczbę nieprzeczytanych wiadomości
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_archived = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_result = $stmt->get_result()->fetch_assoc();
$unread_count = $unread_result['unread_count'];
$stmt->close();

// Pobierz liczbę wiadomości w archiwum
$stmt = $conn->prepare("SELECT COUNT(*) as archive_count FROM messages WHERE receiver_id = ? AND is_archived = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$archive_result = $stmt->get_result()->fetch_assoc();
$archive_count = $archive_result['archive_count'];
$stmt->close();

// Pobierz liczbę wysłanych wiadomości
$stmt = $conn->prepare("SELECT COUNT(*) as sent_count FROM messages WHERE sender_id = ? AND is_sender_deleted = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_result = $stmt->get_result()->fetch_assoc();
$sent_count = $sent_result['sent_count'];
$stmt->close();

$pageTitle = 'Wiadomości';
require 'header.php';
?>

<div id="game-container">
    <!-- Game header with resources -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">✉️</span>
            <span>Wiadomości</span>
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
                <li><a href="reports.php">Raporty</a></li>
                <li><a href="messages.php" class="active">Wiadomości</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="settings.php">Ustawienia</a></li>
                <li><a href="logout.php">Wyloguj</a></li>
            </ul>
        </nav>
        
        <main>
            <h2>Wiadomości</h2>
            
            <?php if (isset($_GET['action_success'])): ?>
                <div class="success-message">Operacja wykonana pomyślnie.</div>
            <?php endif; ?>
            
            <div class="messages-tabs">
                <a href="messages.php?tab=inbox" class="tab <?= $activeTab === 'inbox' ? 'active' : '' ?>">
                    Odebrane 
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="messages.php?tab=sent" class="tab <?= $activeTab === 'sent' ? 'active' : '' ?>">
                    Wysłane
                    <?php if ($sent_count > 0): ?>
                        <span class="badge"><?= $sent_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="messages.php?tab=archive" class="tab <?= $activeTab === 'archive' ? 'active' : '' ?>">
                    Archiwum
                    <?php if ($archive_count > 0): ?>
                        <span class="badge"><?= $archive_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="messages-toolbar">
                <a href="send_message.php" class="btn btn-primary">
                    <i class="fas fa-pen"></i> Napisz wiadomość
                </a>
                
                <?php if (!empty($messages)): ?>
                    <form method="post" id="messages-form" action="messages.php?tab=<?= $activeTab ?>">
                        <div class="bulk-actions">
                            <select name="action" id="bulk-action">
                                <option value="">Wybierz akcję...</option>
                                <?php if ($activeTab === 'inbox'): ?>
                                    <option value="mark_read">Oznacz jako przeczytane</option>
                                    <option value="mark_unread">Oznacz jako nieprzeczytane</option>
                                    <option value="archive">Przenieś do archiwum</option>
                                <?php elseif ($activeTab === 'archive'): ?>
                                    <option value="unarchive">Przywróć do odebranych</option>
                                <?php endif; ?>
                                <option value="delete">Usuń</option>
                            </select>
                            <button type="submit" id="bulk-apply" class="btn btn-secondary" disabled>Wykonaj</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($messages)): ?>
                <div class="messages-container">
                <table class="messages-table">
                    <thead>
                        <tr>
                                <th class="checkbox-col">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th class="status-col"></th>
                                <th class="<?= $activeTab === 'sent' ? 'receiver-col' : 'sender-col' ?>">
                                    <?= $activeTab === 'sent' ? 'Do' : 'Od' ?>
                                </th>
                                <th class="subject-col">Temat</th>
                                <th class="date-col">Data</th>
                                <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                                <tr class="<?= ($activeTab !== 'sent' && !$msg['is_read']) ? 'unread' : '' ?>">
                                    <td>
                                        <input type="checkbox" name="message_ids[]" value="<?= $msg['id'] ?>" form="messages-form" class="message-checkbox">
                                    </td>
                                    <td class="status">
                                        <?php if ($activeTab !== 'sent' && !$msg['is_read']): ?>
                                            <i class="fas fa-envelope status-icon unread-icon" title="Nieprzeczytana"></i>
                                        <?php else: ?>
                                            <i class="fas fa-envelope-open status-icon read-icon" title="Przeczytana"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($activeTab === 'sent'): ?>
                                            <a href="player.php?id=<?= $msg['receiver_id'] ?>" class="player-link">
                                                <?= htmlspecialchars($msg['receiver_username']) ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="player.php?id=<?= $msg['sender_id'] ?>" class="player-link">
                                                <?= htmlspecialchars($msg['sender_username']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="subject">
                                        <a href="view_message.php?id=<?= $msg['id'] ?>&tab=<?= $activeTab ?>">
                                            <?= htmlspecialchars($msg['subject']) ?>
                                        </a>
                                    </td>
                                    <td class="date"><?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?></td>
                                    <td class="actions">
                                        <a href="view_message.php?id=<?= $msg['id'] ?>&tab=<?= $activeTab ?>" class="action-btn" title="Podgląd">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($activeTab === 'inbox'): ?>
                                            <a href="send_message.php?reply_to=<?= $msg['id'] ?>" class="action-btn" title="Odpowiedz">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="messages.php?tab=<?= $activeTab ?>&action=delete&message_ids[]=<?= $msg['id'] ?>" 
                                           class="action-btn delete-btn" 
                                           title="Usuń" 
                                           onclick="return confirm('Czy na pewno chcesz usunąć tę wiadomość?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                
                <!-- Paginacja -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $currentPage - 1 ?>" class="page-link">Poprzednia</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $currentPage + 1 ?>" class="page-link">Następna</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-messages">
                    <p>Brak wiadomości w tej skrzynce.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<style>
.messages-tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--beige-darker);
}

.messages-tabs .tab {
    padding: 10px 20px;
    text-decoration: none;
    color: var(--brown-secondary);
    border: 1px solid var(--beige-darker);
    border-bottom: none;
    border-radius: 5px 5px 0 0;
    margin-right: 5px;
    background-color: var(--beige-medium);
    position: relative;
    transition: all 0.3s ease;
}

.messages-tabs .tab.active {
    background-color: var(--beige-light);
    color: var(--brown-primary);
    border-bottom: 1px solid var(--beige-light);
    margin-bottom: -1px;
    font-weight: bold;
}

.messages-tabs .tab:hover {
    background-color: var(--beige-light);
}

.badge {
    background-color: var(--brown-primary);
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    margin-left: 5px;
}

.messages-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.bulk-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.messages-container {
    background-color: var(--beige-light);
    border-radius: var(--border-radius-medium);
    box-shadow: var(--box-shadow-default);
    overflow: hidden;
}

.messages-table {
    width: 100%;
    border-collapse: collapse;
}

.messages-table th, .messages-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--beige-darker);
}

.messages-table th {
    background-color: var(--beige-dark);
    color: var(--brown-secondary);
    font-weight: bold;
}

.messages-table tr:hover {
    background-color: rgba(255, 255, 255, 0.4);
}

.messages-table tr.unread {
    background-color: rgba(255, 255, 255, 0.7);
    font-weight: bold;
}

.messages-table .checkbox-col {
    width: 30px;
}

.messages-table .status-col {
    width: 30px;
}

.messages-table .sender-col,
.messages-table .receiver-col {
    width: 150px;
}

.messages-table .date-col {
    width: 120px;
}

.messages-table .actions-col {
    width: 100px;
    text-align: right;
}

.status-icon {
    font-size: 16px;
}

.unread-icon {
    color: var(--brown-primary);
}

.read-icon {
    color: #999;
}

.action-btn {
    color: var(--brown-secondary);
    text-decoration: none;
    margin-left: 10px;
    font-size: 14px;
}

.action-btn:hover {
    color: var(--brown-primary);
}

.delete-btn:hover {
    color: var(--red-error);
}

.player-link {
    color: var(--brown-secondary);
    text-decoration: none;
}

.player-link:hover {
    text-decoration: underline;
    color: var(--brown-primary);
}

.no-messages {
    background-color: var(--beige-light);
    padding: 30px;
    text-align: center;
    border-radius: var(--border-radius-medium);
    color: #999;
    font-style: italic;
}

/* Pagination styles */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    gap: 10px;
}

.pagination .page-link {
    padding: 5px 10px;
    border: 1px solid var(--beige-darker);
    border-radius: 5px;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Obsługa "Zaznacz wszystkie"
    const selectAll = document.getElementById('select-all');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const bulkAction = document.getElementById('bulk-action');
    const bulkApply = document.getElementById('bulk-apply');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkActionsState();
        });
    }
    
    // Aktualizacja stanu przycisku "Wykonaj" w zależności od zaznaczonych wiadomości
    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkActionsState();
            
            // Zaktualizuj stan "Zaznacz wszystkie", jeśli wszystkie wiadomości są zaznaczone
            if (selectAll) {
                let allChecked = true;
                messageCheckboxes.forEach(cb => {
                    if (!cb.checked) allChecked = false;
                });
                selectAll.checked = allChecked;
            }
        });
    });
    
    function updateBulkActionsState() {
        let anyChecked = false;
        messageCheckboxes.forEach(checkbox => {
            if (checkbox.checked) anyChecked = true;
        });
        
        bulkApply.disabled = !anyChecked || bulkAction.value === '';
    }
    
    // Aktualizacja stanu przycisku po zmianie wybranej akcji
    if (bulkAction) {
        bulkAction.addEventListener('change', function() {
            updateBulkActionsState();
        });
    }
});
</script>

<?php require 'footer.php'; ?> 