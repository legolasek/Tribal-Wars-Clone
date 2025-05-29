<?php
require '../init.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../lib/managers/MessageManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    // Przekieruj lub zwróć błąd w zależności od żądania
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header("Location: ../auth/login.php");
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Użytkownik niezalogowany.', 'redirect' => 'auth/login.php']);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Inicjalizacja menedżera wiosek i pobranie zasobów (potrzebne dla header.php)
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

// Inicjalizacja menedżera wiadomości
$messageManager = new MessageManager($conn);

// Sprawdź, czy podano ID wiadomości
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

// Pobierz aktywną zakładkę, jeśli została przekazana (dla celów powrotu)
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$validTabs = ['inbox', 'sent', 'archive'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'inbox';
}

// --- Obsługa żądania AJAX o dane wiadomości ---
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Pobierz wiadomość za pomocą MessageManager (ta metoda już oznacza jako przeczytane jeśli trzeba)
    $msg = $messageManager->getMessageByIdForUser($msg_id, $user_id);

    if ($msg === null) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Wiadomość nie znaleziona lub brak dostępu.']);
        exit();
    } else {
        // Zwróć dane wiadomości w JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'messageData' => $msg, // Zwracamy całe dane wiadomości
            'message' => 'Wiadomość załadowana pomyślnie.'
        ]);
        exit(); // Zakończ wykonywanie skryptu po zwróceniu JSON dla AJAX GET
    }
}

// --- Obsługa akcji na wiadomości (POST - Delete, Archive, Unarchive) ---
// Ta sekcja może obsługiwać zarówno AJAX POST jak i tradycyjne POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_id_from_post = (int)($_POST['message_id'] ?? 0);

    // Ensure the message_id from POST matches the message being viewed ($msg_id)
    if ($message_id_from_post === $msg_id) {
        // Use MessageManager to perform the single action
        $success = $messageManager->performSingleAction($user_id, $msg_id, $action);

        if ($success) {
            // Define response based on action
            $response = ['success' => true, 'message' => 'Operacja wykonana pomyślnie.'];
            // Przygotuj URL do przekierowania po akcji
            $redirectUrl = 'messages.php?tab=' . urlencode($activeTab);
            if ($action === 'delete') {
                 $response['message'] = 'Wiadomość usunięta.';
                 // Po usunięciu zawsze przekierowujemy do listy
                 $response['redirect'] = $redirectUrl;
            } elseif ($action === 'archive') {
                 $response['message'] = 'Wiadomość przeniesiona do archiwum.';
                 // Po archiwizacji można przekierować do archiwum, lub zostać i odświeżyć listę
                 $response['redirect'] = 'messages.php?tab=archive';
            } elseif ($action === 'unarchive') {
                 $response['message'] = 'Wiadomość przywrócona z archiwum.';
                 // Po przywróceniu przekierowujemy do odebranych
                 $response['redirect'] = 'messages.php?tab=inbox';
            }

            if ($is_ajax) {
                 header('Content-Type: application/json');
                 echo json_encode($response);
                 exit(); // Zakończ skrypt po odpowiedzi AJAX POST
            } else {
                // Tradycyjne przekierowanie po POST bez AJAX
                 header("Location: " . $response['redirect']);
                 exit();
            }

        } else {
            $response = ['success' => false, 'message' => 'Wystąpił błąd podczas wykonywania akcji lub brak uprawnień.'];
            if ($is_ajax) {
                 header('Content-Type: application/json');
                 echo json_encode($response);
                 exit();
            } else {
                 // Tradycyjne przekierowanie z błędem (można dodać parametr błędu w URL)
                 header("Location: messages.php?tab=" . urlencode($activeTab) . "&action_error=1");
                 exit();
            }
        }
    } else {
         $response = ['success' => false, 'message' => 'Nieprawidłowy identyfikator wiadomości w żądaniu akcji.'];
         if ($is_ajax) {
              header('Content-Type: application/json');
              echo json_encode($response);
              exit();
         } else {
              header("Location: messages.php?tab=" . urlencode($activeTab) . "&action_error=1");
              exit();
         }
    }
}

// --- Normalne wyświetlanie pełnej strony (jeśli nie AJAX) ---
// Ta część będzie używana, gdy użytkownik wejdzie bezpośrednio na URL view_message.php?id=X
// Powinna pobrać dane wiadomości i wyrenderować całą stronę.

// Pobierz wiadomość za pomocą MessageManager (ta metoda już oznacza jako przeczytane jeśli trzeba)
// Ponownie pobieramy dane, bo poprzednie pobranie mogło być tylko na potrzeby AJAX GET
$msg = $messageManager->getMessageByIdForUser($msg_id, $user_id);

if ($msg === null) {
    // Wiadomość nie znaleziona lub brak dostępu przy wejściu bezpośrednim
    header("Location: messages.php?tab=" . urlencode($activeTab) . "&error=message_not_found");
    exit();
}

// Sprawdź, czy użytkownik jest nadawcą czy odbiorcą (na podstawie danych z Managera)
$is_sender = ($msg['sender_id'] == $user_id);
$is_receiver = ($msg['receiver_id'] == $user_id);

$pageTitle = 'Wiadomość: ' . htmlspecialchars($msg['subject']);
require '../header.php';
?>

<div id="game-container">
    <!-- Nagłówek główny strony - włączony przez header.php -->

    <div id="main-content">
        <main>
            <div class="message-view-container" data-message-id="<?= $msg['id'] ?>">
                <div class="message-header">
                    <div class="message-nav">
                        <a href="messages.php?tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Powrót do <?= $activeTab === 'inbox' ? 'Odebranych' : ($activeTab === 'sent' ? 'Wysłanych' : 'Archiwum') ?>
                        </a>
                        
                        <div class="message-actions">
                            <?php if ($is_receiver): // Tylko odbiorca może odpowiedzieć ?>
                                <a href="send_message.php?reply_to=<?= $msg_id ?>" class="btn btn-primary">
                                    <i class="fas fa-reply"></i> Odpowiedz
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-danger action-button" data-action="delete" data-message-id="<?= $msg_id ?>" data-confirm="Czy na pewno chcesz usunąć tę wiadomość?">
                                <i class="fas fa-trash"></i> Usuń
                            </button>
                            
                            <?php if ($is_receiver): // Tylko odbiorca może archiwizować/przywracać ?>
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
                            <a href="../player/player.php?id=<?= $msg['sender_id'] ?>" class="player-link">
                                <?= htmlspecialchars($msg['sender_username']) ?>
                            </a>
                        </div>
                        <div class="receiver">
                            <strong>Do:</strong> 
                            <a href="../player/player.php?id=<?= $msg['receiver_id'] ?>" class="player-link">
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
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>

<script src="js/messages.js"></script>
<script>
    // Skrypt do obsługi przycisków akcji na pojedynczej wiadomości (można przenieść do js/messages.js)
    document.addEventListener('DOMContentLoaded', function() {
        const messageViewContainer = document.querySelector('.message-view-container');
        if (messageViewContainer) {
            messageViewContainer.addEventListener('click', function(event) {
                const target = event.target.closest('.action-button');
                if (target) {
                    const action = target.dataset.action;
                    const messageId = target.dataset.messageId;
                    const confirmMessage = target.dataset.confirm;

                    if (confirmMessage && !confirm(confirmMessage)) {
                        return; // Anuluj akcję jeśli użytkownik nie potwierdził
                    }

                    // Wyślij żądanie AJAX do tego samego pliku (view_message.php)
                    fetch('view_message.php?id=' + messageId + '&tab=<?= urlencode($activeTab) ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest' // Indicate it's an AJAX request
                        },
                        body: new URLSearchParams({
                            action: action,
                            message_id: messageId,
                            // Add CSRF token if implemented
                            // csrf_token: 'your_token_here'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Handle success
                            alert(data.message);
                            if (data.redirect) {
                                // Przekieruj na inną stronę, jeśli response zawiera redirect URL
                                window.location.href = data.redirect;
                            } else {
                                // Jeśli nie ma przekierowania (np. po oznaczeniu jako nieprzeczytane),
                                // można odświeżyć widok wiadomości (np. załadować go ponownie przez AJAX)
                                // lub zaktualizować UI lokalnie.
                                // Na potrzeby prostoty na razie odświeżymy stronę, jeśli nie ma redirectu
                                // Lepszym rozwiązaniem byłaby aktualizacja UI lub powrót do listy wiadomości
                                window.location.reload(); // TODO: Zaimplementować lepszą obsługę UI
                            }
                        } else {
                            // Handle error
                            alert('Błąd: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Błąd:', error);
                        alert('Wystąpił błąd komunikacji z serwerem.');
                    });
                }
            });
        }
    });
</script> 