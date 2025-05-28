/**
 * Obsługa powiadomień typu "toast"
 */

class ToastManager {
    constructor() {
        this.toastContainer = this.getOrCreateToastContainer();
    }

    getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Wyświetla powiadomienie typu "toast".
     * @param {string} message Treść komunikatu.
     * @param {string} type Typ komunikatu (success, error, info, warning).
     * @param {number} duration Czas wyświetlania w milisekundach.
     */
    showToast(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.classList.add('game-toast', type);
        toast.textContent = message;

        this.toastContainer.appendChild(toast);

        // Pokaż toast
        setTimeout(() => {
            toast.classList.add('visible');
        }, 100); // Krótkie opóźnienie dla animacji

        // Ukryj i usuń toast po czasie
        setTimeout(() => {
            toast.classList.remove('visible');
            toast.addEventListener('transitionend', () => {
                toast.remove();
            }, { once: true });
        }, duration);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.toastManager = new ToastManager(); // Ustaw globalnie

    // Wyświetl komunikaty przekazane z PHP
    if (window.gameMessages && Array.isArray(window.gameMessages)) {
        window.gameMessages.forEach(msg => {
            window.toastManager.showToast(msg.message, msg.type);
        });
    }

    // Obsługa kliknięć na ikonę powiadomień w nagłówku
    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationCountBadge = document.getElementById('notification-count');
    const notificationsList = document.getElementById('notifications-list');
    const markAllReadBtn = document.getElementById('mark-all-read');

    if (notificationsToggle && notificationsDropdown) {
        notificationsToggle.addEventListener('click', (e) => {
            e.preventDefault();
            notificationsDropdown.classList.toggle('show');
            // Jeśli otwieramy, pobierz najnowsze powiadomienia
            if (notificationsDropdown.classList.contains('show')) {
                fetchNotifications();
            }
        });

        // Zamknij dropdown po kliknięciu poza nim
        document.addEventListener('click', (e) => {
            if (!notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.remove('show');
            }
        });
    }

    // Funkcja do pobierania i renderowania powiadomień
    async function fetchNotifications() {
        try {
            const response = await fetch('ajax/get_notifications.php?unreadOnly=true&limit=5');
            const data = await response.json();

            if (data.status === 'success') {
                renderNotifications(data.data.notifications, data.data.unread_count);
            } else {
                console.error('Błąd pobierania powiadomień:', data.message);
                toastManager.showToast('Błąd pobierania powiadomień.', 'error');
            }
        } catch (error) {
            console.error('Błąd AJAX powiadomień:', error);
            toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
        }
    }

    // Funkcja do renderowania powiadomień w dropdownie
    function renderNotifications(notifications, unreadCount) {
        if (!notificationsList) return;

        notificationsList.innerHTML = ''; // Wyczyść listę

        if (notifications.length === 0) {
            notificationsList.innerHTML = '<div class="no-notifications">Brak nowych powiadomień</div>';
        } else {
            const ul = document.createElement('ul');
            ul.classList.add('notifications-list-items');
            notifications.forEach(notification => {
                const li = document.createElement('li');
                li.classList.add('notification-item', `notification-${notification.type}`);
                li.dataset.id = notification.id;
                
                const iconClass = notification.type === 'success' ? 'fa-check-circle' : 
                                  (notification.type === 'error' ? 'fa-exclamation-circle' : 
                                  (notification.type === 'info' ? 'fa-info-circle' : 'fa-bell'));

                li.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${relativeTime(new Date(notification.created_at).getTime() / 1000)}</div>
                    </div>
                    <button class="mark-read-btn" data-id="${notification.id}" title="Oznacz jako przeczytane">
                        <i class="fas fa-check"></i>
                    </button>
                `;
                ul.appendChild(li);
            });
            notificationsList.appendChild(ul);
        }

        // Zaktualizuj badge z liczbą nieprzeczytanych
        if (notificationCountBadge) {
            if (unreadCount > 0) {
                notificationCountBadge.textContent = unreadCount;
                notificationCountBadge.style.display = 'block';
            } else {
                notificationCountBadge.style.display = 'none';
            }
        }
    }

    // Obsługa oznaczania pojedynczego powiadomienia jako przeczytane
    notificationsList.addEventListener('click', async (e) => {
        if (e.target.closest('.mark-read-btn')) {
            const button = e.target.closest('.mark-read-btn');
            const notificationId = button.dataset.id;
            try {
                const response = await fetch('ajax/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `notification_id=${notificationId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    toastManager.showToast('Powiadomienie oznaczone jako przeczytane.', 'success');
                    fetchNotifications(); // Odśwież listę powiadomień
                } else {
                    toastManager.showToast(data.message || 'Błąd oznaczania powiadomienia.', 'error');
                }
            } catch (error) {
                console.error('Błąd AJAX oznaczania powiadomienia:', error);
                toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
            }
        }
    });

    // Obsługa oznaczania wszystkich powiadomień jako przeczytane
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const response = await fetch('ajax/mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    toastManager.showToast('Wszystkie powiadomienia oznaczone jako przeczytane.', 'success');
                    fetchNotifications(); // Odśwież listę powiadomień
                } else {
                    toastManager.showToast(data.message || 'Błąd oznaczania wszystkich powiadomień.', 'error');
                }
            } catch (error) {
                console.error('Błąd AJAX oznaczania wszystkich powiadomień:', error);
                toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
            }
        });
    }

});
