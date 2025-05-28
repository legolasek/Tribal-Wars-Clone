<?php
require 'init.php';
require_once 'lib/VillageManager.php';

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    // Przekieruj do logowania, jeśli to nie jest żądanie AJAX
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header("Location: login.php");
    exit();
    } else {
        // Zwróć błąd JSON dla żądań AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Użytkownik niezalogowany.', 'redirect' => 'login.php']);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Inicjalizacja menedżera wiosek i pobranie zasobów
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

if (!isset($_GET['id'])) {
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header("Location: messages.php");
    exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Brak identyfikatora wiadomości.']);
        exit();
    }
}
$msg_id = (int)$_GET['id'];

// Pobierz aktywną zakładkę, jeśli została przekazana
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$validTabs = ['inbox', 'sent', 'archive'];

if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'inbox';
}

// Pobierz wiadomość wraz z nadawcą i odbiorcą
$stmt = $conn->prepare(
    "SELECT m.id, m.subject, m.body, m.sent_at, m.is_read, m.sender_id, m.receiver_id,
            u_sender.username AS sender_username, u_sender.id AS sender_id,
            u_receiver.username AS receiver_username, u_receiver.id AS receiver_id
     FROM messages m
     JOIN users u_sender ON m.sender_id = u_sender.id
     JOIN users u_receiver ON m.receiver_id = u_receiver.id
     WHERE m.id = ? AND (m.receiver_id = ? OR m.sender_id = ?) LIMIT 1"
);
$stmt->bind_param("iii", $msg_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header("Location: messages.php");
    exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Wiadomość nie znaleziona lub brak dostępu.']);
        exit();
    }
}
$msg = $result->fetch_assoc();
$stmt->close();

// Oznacz jako przeczytane, jeśli to odbiorca i wiadomość jest nieprzeczytana
if ($msg['receiver_id'] === $user_id && !$msg['is_read']) {
    $stmt2 = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $stmt2->bind_param("i", $msg_id);
    if ($stmt2->execute()) {
        // Jeśli pomyślnie oznaczono jako przeczytane, zaktualizuj zmienną
        $msg['is_read'] = 1;
    }
    $stmt2->close();
}

// Sprawdź, czy użytkownik jest nadawcą czy odbiorcą
$is_sender = ($msg['sender_id'] == $user_id);
$is_receiver = ($msg['receiver_id'] == $user_id);

// Obsługa akcji na wiadomości (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    validateCSRF(); // Sprawdź token CSRF
    
    $action = $_POST['action'] ?? '';
    $message_id = (int)$_POST['message_id'] ?? 0;
    
    $response = ['success' => false, 'message' => 'Nieznana akcja.'];
    
    // Sprawdź, czy wiadomość należy do użytkownika
    if ($message_id == $msg_id) {
        switch ($action) {
            case 'delete':
                if ($is_sender) {
                    // Jeśli nadawca usuwa, oznacz jako usunięta przez nadawcę
                    $stmt = $conn->prepare("UPDATE messages SET is_sender_deleted = 1 WHERE id = ? AND sender_id = ?");
                    $stmt->bind_param("ii", $message_id, $user_id);
                } elseif ($is_receiver) {
                    // Jeśli odbiorca usuwa, usuń wiadomość
                     // Należy również sprawdzić czy nadawca jej nie usunął, wtedy całkowicie ją usuwamy
                    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?"); // To usunie wiadomość tylko dla odbiorcy w tym scenariuszu
                     // Poprawiona logika: Oznacz jako usunięta przez odbiorcę. Wiadomość będzie fizycznie usunięta, gdy oba flagi (is_sender_deleted i is_archived lub inne w przyszłości) będą ustawione lub gdy administrator ją usunie.
                     // Na potrzeby tej wersji, upraszczamy i oznaczamy flaga. Fizyczne usuwanie można dodać później.
                     // Zmieniam logikę usuwania na oznaczenie obu stron
                    $stmt = $conn->prepare("UPDATE messages SET is_receiver_deleted = 1 WHERE id = ? AND receiver_id = ?"); // Potrzebna kolumna is_receiver_deleted
                     // === WAŻNE === Wymagana kolumna `is_receiver_deleted` w tabeli `messages`. Trzeba dodać to w skrypcie instalacyjnym/aktualizacyjnym.
                     // Na razie zaimplementuję proste usunięcie dla odbiorcy, jak było, ale docelowo lepiej użyć flag.
                    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");

                }
                
                if (isset($stmt) && $stmt->execute()) {
                    $stmt->close();
                    $response = ['success' => true, 'message' => 'Wiadomość usunięta.', 'redirect' => "messages.php?tab={$activeTab}"];
                     if ($is_sender) { $response['message'] = 'Wiadomość oznaczona jako usunięta (dla Ciebie). Jeśli odbiorca jej nie usunął, nadal będzie widoczna u niego.'; }
                     // Docelowo w odpowiedzi AJAX nie będzie redirectu, a JS obsłuży usunięcie elementu z listy
                } else {
                    $response = ['success' => false, 'message' => 'Błąd podczas usuwania wiadomości.'];
                }
                break;
                
            case 'archive':
                if ($is_receiver) {
                    $stmt = $conn->prepare("UPDATE messages SET is_archived = 1 WHERE id = ? AND receiver_id = ?");
                    $stmt->bind_param("ii", $message_id, $user_id);
                    if ($stmt->execute()) {
                         $stmt->close();
                         $response = ['success' => true, 'message' => 'Wiadomość przeniesiona do archiwum.', 'redirect' => "messages.php?tab=archive"];
                         // Docelowo w odpowiedzi AJAX nie będzie redirectu
                    } else {
                        $response = ['success' => false, 'message' => 'Błąd podczas archiwizacji wiadomości.'];
                    }
                }
                break;
                
            case 'unarchive':
                if ($is_receiver) {
                    $stmt = $conn->prepare("UPDATE messages SET is_archived = 0 WHERE id = ? AND receiver_id = ?");
                    $stmt->bind_param("ii", $message_id, $user_id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        $response = ['success' => true, 'message' => 'Wiadomość przywrócona z archiwum.', 'redirect' => "messages.php?tab=inbox"];
                         // Docelowo w odpowiedzi AJAX nie będzie redirectu
                    } else {
                        $response = ['success' => false, 'message' => 'Błąd podczas przywracania wiadomości z archiwum.'];
                    }
                }
                break;

            default:
                 $response = ['success' => false, 'message' => 'Nieznana akcja.'];
                break;
        }
    }
    
    echo json_encode($response);
    exit(); // Zakończ skrypt po odpowiedzi AJAX POST
}

// Przygotowanie danych do wyświetlenia (dla widoku HTML lub JSON)
$messageHtml = '';
ob_start(); // Rozpocznij buforowanie wyjścia
?>

<div class="message-view-container" data-message-id="<?= $msg['id'] ?>">
    <div class="message-header">
        <div class="message-nav">
            <a href="messages.php?tab=<?= $activeTab ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Powrót do <?= $activeTab === 'inbox' ? 'Odebranych' : ($activeTab === 'sent' ? 'Wysłanych' : 'Archiwum') ?>
            </a>
            
            <div class="message-actions">
                <?php if ($is_receiver): ?>
                    <a href="send_message.php?reply_to=<?= $msg_id ?>" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Odpowiedz
                    </a>
                <?php endif; ?>
                
                <button class="btn btn-danger action-button" data-action="delete" data-message-id="<?= $msg_id ?>" data-confirm="Czy na pewno chcesz usunąć tę wiadomość?">
                    <i class="fas fa-trash"></i> Usuń
                </button>
                
                <?php if ($is_receiver): ?>
                    <?php if ($activeTab !== 'archive'): ?>
                        <button class="btn btn-secondary action-button" data-action="archive" data-message-id="<?= $msg_id ?>">
                            <i class="fas fa-archive"></i> Archiwizuj
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary action-button" data-action="unarchive" data-message-id="<?= $msg_id ?>">
                            <i class="fas fa-inbox"></i> Przywróć
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <h2><?= htmlspecialchars($msg['subject']) ?></h2>
    </div>
    
    <div class="message-meta">
        <div class="message-participants">
            <div class="sender">
                <strong>Od:</strong> 
                <a href="player.php?id=<?= $msg['sender_id'] ?>" class="player-link">
                    <?= htmlspecialchars($msg['sender_username']) ?>
                </a>
            </div>
            <div class="receiver">
                <strong>Do:</strong> 
                <a href="player.php?id=<?= $msg['receiver_id'] ?>" class="player-link">
                    <?= htmlspecialchars($msg['receiver_username']) ?>
                </a>
            </div>
        </div>
        <div class="message-date">
            <strong>Data:</strong> <?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?>
        </div>
    </div>
    
    <div class="message-content">
        <?= nl2br(htmlspecialchars($msg['body'])) ?>
    </div>
</div>

<?php
$messageHtml = ob_get_clean(); // Pobierz zawartość bufora i zakończ buforowanie

// Sprawdź, czy żądanie jest AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    // Zwróć tylko treść HTML i ewentualne dane dodatkowe w JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $messageHtml,
        'messageId' => $msg['id'],
        'isRead' => $msg['is_read'], // Zwróć zaktualizowany status przeczytania
        'message' => 'Wiadomość załadowana pomyślnie.'
    ]);
    exit();
} else {
    // === Renderowanie strony HTML ===
    $pageTitle = 'Wiadomość: ' . htmlspecialchars($msg['subject']);
    require 'header.php';
?>

<div id="game-container">
    <!-- Game header z zasobami i nawigacją boczną -->
     <?php if (isset($_SESSION['user_id'])): ?>
        <header id="main-header">
            <div class="header-title">
                <span class="game-logo">✉️</span>
                <span>Wiadomości</span>
            </div>
            <div class="header-user">
                Gracz: <?= htmlspecialchars($username) ?>
                 <?php if (isset($_SESSION['user_id']) && !empty($currentRes)): ?>
                    <div id="resource-bar" class="resource-bar" data-village-id="<?= $village_id ?>">
                        <ul>
                            <li class="resource-wood">
                                <img src="img/ds_graphic/resources/wood.png" alt="Drewno">
                                <span class="resource-value" id="current-wood"><?= floor($currentRes['wood']) ?></span> / <span class="resource-capacity"><?= $currentRes['warehouse_capacity'] ?></span>
                                <span class="resource-production">(+<?= round($villageManager->getResourceProduction($village_id, 'wood'), 1) ?>/h)</span>
                            </li>
                            <li class="resource-clay">
                                <img src="img/ds_graphic/resources/clay.png" alt="Glina">
                                <span class="resource-value" id="current-clay"><?= floor($currentRes['clay']) ?></span> / <span class="resource-capacity"><?= $currentRes['warehouse_capacity'] ?></span>
                                <span class="resource-production">(+<?= round($villageManager->getResourceProduction($village_id, 'clay'), 1) ?>/h)</span>
                            </li>
                            <li class="resource-iron">
                                <img src="img/ds_graphic/resources/iron.png" alt="Żelazo">
                                <span class="resource-value" id="current-iron"><?= floor($currentRes['iron']) ?></span> / <span class="resource-capacity"><?= $currentRes['warehouse_capacity'] ?></span>
                                <span class="resource-production">(+<?= round($villageManager->getResourceProduction($village_id, 'iron'), 1) ?>/h)</span>
                            </li>
                            <li class="resource-population">
                                <img src="img/ds_graphic/resources/population.png" alt="Populacja">
                                <span class="resource-value"><?= formatNumber($currentRes['population']) ?></span>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="header-links">
                     <a href="game.php">Przegląd</a> | 
                     <a href="logout.php">Wyloguj</a>
                 </div>
            </div>
         <?php endif; ?>

    <div id="main-content">
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
            <h2>Wiadomość</h2>
            
            <?= $messageHtml // Wyświetl przygotowany HTML wiadomości ?>
        
        </main>
    </div>
</div>

<?php require 'footer.php'; ?>

<script>
// Funkcja do obsługi akcji na wiadomości przez AJAX
document.addEventListener('DOMContentLoaded', function() {
    const actionButtons = document.querySelectorAll('.action-button');

    actionButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();

            const action = this.dataset.action;
            const messageId = this.dataset.messageId;
            const confirmMessage = this.dataset.confirm;

            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            // Wyślij żądanie AJAX
            fetch('view_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') // Dodaj token CSRF
                },
                body: `action=${action}&message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Po sukcesie, przekieruj do listy wiadomości (tymczasowo)
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                         // W przyszłości tutaj można by dynamicznie zaktualizować UI bez przeładowania
                         // Na razie odświeżamy, jeśli nie ma specjalnego redirectu w odpowiedzi (np. dla archiwizacji)
                         window.location.reload();
                    }
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Błąd podczas wykonywania akcji na wiadomości:', error);
                alert('Wystąpił błąd podczas wykonywania akcji na wiadomości.');
            });
        });
    });
});
</script>

<?php
}
?> 