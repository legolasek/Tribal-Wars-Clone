<?php
require '../init.php';
validateCSRF();

require_once __DIR__ . '/../lib/managers/UserManager.php'; // For getting recipient user ID
require_once __DIR__ . '/../lib/managers/MessageManager.php'; // For message operations
require_once __DIR__ . '/../lib/managers/NotificationManager.php'; // For notifications
require_once __DIR__ . '/../lib/functions.php'; // For addNotification (if still needed, or move to manager)

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    // Jeśli to żądanie AJAX, zwróć błąd JSON; w przeciwnym przypadku przekieruj
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Użytkownik niezalogowany.', 'redirect' => 'auth/login.php']);
        exit();
    } else {
    header("Location: ../auth/login.php");
    exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Inicjalizacja menedżerów
$userManager = new UserManager($conn);
$messageManager = new MessageManager($conn);
$notificationManager = new NotificationManager($conn); // Assuming constructor takes $conn

// Obsługa odpowiedzi na wiadomość (pre-fill formularza)
$reply_to = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : 0;
$recipient_username = '';
$original_subject = '';
$original_body = '';
$prefilled_subject = '';

if ($reply_to > 0) {
    // Pobierz oryginalną wiadomość (tylko jeśli użytkownik jest odbiorcą)
    // Użyj MessageManager do pobrania wiadomości
    $original_message = $messageManager->getMessageByIdForUser($reply_to, $user_id); // Assuming this method checks receiver_id

    if ($original_message) {
        $recipient_username = $userManager->getUserById($original_message['sender_id'])['username'] ?? ''; // Get sender's username
        $original_subject = $original_message['subject'];
        $original_body = $original_message['body'];
        
        // Dodaj "Re:" na początku tematu, jeśli jeszcze go nie ma
        if (strpos($original_subject, 'Re:') !== 0) {
            $prefilled_subject = 'Re: ' . $original_subject;
        } else {
            $prefilled_subject = $original_subject;
        }
    }
    // Remove manual database query
    /*
    $stmt = $conn->prepare("
        SELECT m.subject, m.body, u.username 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ? AND m.receiver_id = ? LIMIT 1
    ");
    $stmt->bind_param("ii", $reply_to, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $recipient_username = $row['username'];
        $original_subject = $row['subject'];
        $original_body = $row['body'];
        
        // Dodaj "Re:" na początku tematu, jeśli jeszcze go nie ma
        if (strpos($original_subject, 'Re:') !== 0) {
            $prefilled_subject = 'Re: ' . $original_subject;
        } else {
            $prefilled_subject = $original_subject;
        }
    }
    $stmt->close();
    */
}

// Obsługa wysyłania wiadomości (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    validateCSRF(); // Sprawdź token CSRF
    
    $receiver_username = trim($_POST['receiver_username'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    $response = ['success' => false, 'message' => ''];

    if (empty($receiver_username) || empty($subject) || empty($body)) {
        $response['message'] = 'Wszystkie pola są wymagane!';
    } else {
        // Znajdź odbiorcę po nazwie użytkownika
        // Użyj UserManager do znalezienia odbiorcy
        $receiver = $userManager->getUserByUsername($receiver_username);

        if ($receiver) {
            $receiver_id = $receiver['id'];

            // Sprawdź, czy nie wysyłasz wiadomości do samego siebie
            if ($receiver_id == $user_id) {
                $response['message'] = 'Nie możesz wysłać wiadomości do samego siebie.';
            } else {
                // Wyślij wiadomość przy użyciu MessageManager
                $sendMessageResult = $messageManager->sendMessage($user_id, $receiver_id, $subject, $body);

                if ($sendMessageResult['success']) {
                    $response['success'] = true;
                    $response['message'] = 'Wiadomość wysłana pomyślnie!';
                    $response['newMessageId'] = $sendMessageResult['message_id'];
                    $response['redirect'] = 'messages.php?tab=sent'; // Tymczasowe przekierowanie

                     // Dodaj powiadomienie dla odbiorcy przy użyciu NotificationManager
                     $notification_message = "Otrzymałeś nową wiadomość od {$username}";
                     $notification_link = "view_message.php?id=" . $sendMessageResult['message_id'];
                     $notificationManager->addNotification($receiver_id, 'info', $notification_message, $notification_link); // Assuming method signature

                } else {
                    $response['message'] = 'Błąd podczas wysyłania wiadomości.';
                    // Optionally, get more detailed error from $sendMessageResult if provided
                }
            }
        } else {
            $response['message'] = 'Nie znaleziono użytkownika: ' . htmlspecialchars($receiver_username);
        }
    }
    
    echo json_encode($response);
    exit(); // Zakończ skrypt po odpowiedzi AJAX
}

// Przygotowanie danych i renderowanie HTML formularza (dla bezpośredniego dostępu lub AJAX do wstrzyknięcia)
$formHtml = '';
ob_start(); // Rozpocznij buforowanie wyjścia
?>

<div class="message-compose-container" data-reply-to="<?= $reply_to ?>">
    <h2>Napisz nową wiadomość</h2>
    
    <!-- Wiadomości o błędach/sukcesach będą wyświetlane przez toasty/powiadomienia -->
    <?php /* if (!empty($message)): ?>
        <div class="message-container">
            <?= $message ?>
        </div>
    <?php endif; */ ?>
    
    <form action="send_message.php<?= $reply_to ? '?reply_to=' . $reply_to : '' ?>" method="POST" class="message-compose-form" id="send-message-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <div class="form-group">
            <label for="receiver_username">Odbiorca:</label>
            <input type="text" id="receiver_username" name="receiver_username" value="<?= htmlspecialchars($recipient_username) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="subject">Temat:</label>
            <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($prefilled_subject) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="body">Treść wiadomości:</label>
            <textarea id="body" name="body" rows="10" required><?php 
            if ($reply_to > 0 && !empty($original_body)) {
                echo "\n\n---\nW dniu " . date('d.m.Y', strtotime($original_message['sent_at'] ?? 'now')) . ", " . htmlspecialchars($recipient_username) . " napisał(a):\n"; // Użyj daty wysłania originalnej wiadomości
                // Dodaj cytowany tekst z wcięciem
                $quoted_body = '';
                $lines = explode("\n", $original_body);
                foreach ($lines as $line) {
                    $quoted_body .= "> " . $line . "\n";
                }
                echo htmlspecialchars($quoted_body);
            }
            ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Wyślij wiadomość
            </button>
            <!-- Przycisk Anuluj powinien wracać do listy wiadomości -->
            <a href="messages.php" class="btn btn-secondary">Anuluj</a>
        </div>
    </form>
    
    <?php if ($reply_to > 0 && !empty($original_body)): ?>
    <div class="original-message">
        <h3>Oryginalna wiadomość</h3>
        <div class="original-message-content">
            <div class="original-message-header">
                <div><strong>Od:</strong> <?= htmlspecialchars($recipient_username) ?></div>
                <div><strong>Temat:</strong> <?= htmlspecialchars($original_subject) ?></div>
            </div>
            <div class="original-message-body">
                <?= nl2br(htmlspecialchars($original_body)) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$formHtml = ob_get_clean(); // Pobierz zawartość bufora i zakończ buforowanie

// Sprawdź, czy żądanie jest AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    // Zwróć tylko HTML formularza (jeśli potrzebne np. do popupu) lub tylko JSON po wysyłce POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
         // If it's a GET request (e.g., to get the form HTML for a popup)
         echo json_encode([
             'status' => 'success',
             'html' => $formHtml
         ]);
         exit();
    }
    // POST requests are handled and exit earlier
} else {
    // If it's a standard page request (not AJAX)
    $pageTitle = 'Napisz wiadomość';
    require 'header.php';
    ?>
    <div id="game-container">
        <?php // Add header ?>
         <header id="main-header">
             <div class="header-title">
                 <span class="game-logo">📧</span>
                 <span>Nowa wiadomość</span>
             </div>
             <?php // User section will be included by header.php if logic is there ?>
             <?php if (isset($_SESSION['user_id']) && ($currentUserVillage = $villageManager->getFirstVillage($_SESSION['user_id']))): ?>
              <div class="header-user">
                  Gracz: <?= htmlspecialchars($_SESSION['username']) ?><br>
                  <span class="village-name-display" data-village-id="<?= $currentUserVillage['id'] ?>"><?= htmlspecialchars($currentUserVillage['name']) ?> (<?= $currentUserVillage['x_coord'] ?>|<?= $currentUserVillage['y_coord'] ?>)</span>
              </div>
             <?php endif; ?>
         </header>
        <div id="main-content">
            <main>
                <?= $formHtml // Output the buffered form HTML ?>
            </main>
        </div>
    </div>
    <?php
    require 'footer.php';
}

?>

<script>
// Skrypt do obsługi wysyłania wiadomości za pomocą AJAX
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('send-message-form');
    if (!form) return;

    form.addEventListener('submit', function(event) {
        event.preventDefault(); // Zapobiegaj domyślnemu wysłaniu formularza

        // Pokaż loader
        showLoading(); // Zakładając istnienie funkcji showLoading()

        const formData = new FormData(form);

        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading(); // Ukryj loader
            if (data.success) {
                showToast('success', data.message); // Pokaż powiadomienie o sukcesie
                
                // Tutaj można dodać logikę np. czyszczenia formularza lub przekierowania
                // Obecnie ustawione jest tymczasowe przekierowanie w PHP, ale docelowo JS to obsłuży
                 if (data.redirect) {
                     window.location.href = data.redirect; // Przekierowanie po sukcesie (tymczasowe)
                 } else {
                     // Jeśli nie ma przekierowania, np. w popupie, można wyczyścić formularz:
                     // form.reset();
                     // // Ewentualnie zamknąć popup:
                     // if (window.closeSendMessagePopup) { window.closeSendMessagePopup(); }
                 }

            } else {
                showToast('error', data.message); // Pokaż powiadomienie o błędzie
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Błąd AJAX:', error);
            showToast('error', 'Wystąpił błąd komunikacji z serwerem.');
        });
    });
});

// Zakładane globalne funkcje (muszą być zdefiniowane w main.js lub innym wspólnym pliku)
// function showLoading() { /* implementacja */ }
// function hideLoading() { /* implementacja */ }
// function showToast(type, message) { /* implementacja */ }
</script>

<style>
/* Style specyficzne dla formularza wysyłania wiadomości */

/* Usunięto style dotyczące całego kontenera gry i paska bocznego, ponieważ plik może być ładowany przez AJAX */

.message-compose-container {
    background-color: var(--beige-light);
    border-radius: var(--border-radius-medium);
    box-shadow: var(--box-shadow-default);
    padding: var(--spacing-lg);
    margin-top: var(--spacing-md); /* Można dostosować */
}

.message-compose-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.form-group label {
    font-weight: bold;
    color: var(--brown-secondary);
}

.form-group input[type="text"],
.form-group textarea {
    padding: var(--spacing-sm);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
    background-color: #fff;
    font-family: var(--font-main);
    font-size: var(--font-size-normal);
    width: calc(100% - var(--spacing-sm) * 2); /* Uwzględnij padding */
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 200px;
}

.form-actions {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: flex-end;
    margin-top: var(--spacing-md);
}

/* Style dla oryginalnej wiadomości przy odpowiedzi */
.original-message {
    margin-top: var(--spacing-lg);
    padding: var(--spacing-md);
    background-color: var(--beige-dark);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
}

.original-message h3 {
    margin-top: 0;
    margin-bottom: var(--spacing-sm);
    color: var(--brown-primary);
    border-bottom: 1px solid var(--beige-darker);
    padding-bottom: var(--spacing-xs);
}

.original-message-content {
    font-size: var(--font-size-small);
    color: var(--brown-secondary);
}

.original-message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
}

.original-message-body {
    white-space: pre-wrap;
    word-break: break-word;
}

</style> 