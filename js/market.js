/**
 * Frontend logic for Market Panel and Trade
 */

// Assume formatDuration is available globally from buildings.js or units.js
// function formatDuration(seconds) { ... }

// Function to update timers specifically within the Market popup for active trades
function updateTradeTimersPopup() {
    const timers = document.querySelectorAll('#popup-action-content .trade-timer');
    const currentTime = Math.floor(Date.now() / 1000);

    timers.forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt, 10);
        const remainingTime = finishTime - currentTime;

        if (remainingTime > 0) {
            timerElement.textContent = formatDuration(remainingTime);
        } else {
            timerElement.textContent = 'Przybył!';
            timerElement.classList.add('timer-finished');
            timerElement.removeAttribute('data-ends-at'); // Stop refreshing this timer

            // This trade has finished. We should ideally refresh the active trades list.
            // For simplicity now, just mark it finished.
             const tradeRow = timerElement.closest('tr');
             if (tradeRow) {
                  tradeRow.classList.add('finished');
                  // Might need to visually update resources if it was an incoming trade
                  // Or update available traders if it was an outgoing trade
             }
             // A trade finished, refresh the market panel to update lists and trader count
             // Need a way to get current villageId and buildingInternalName (market)
             // Let's assume popup-action-content has data attributes
             const actionContent = document.getElementById('popup-action-content');
              if (actionContent && actionContent.dataset.villageId && actionContent.dataset.buildingInternalName === 'market') {
                   const villageId = actionContent.dataset.villageId;
                   const buildingInternalName = actionContent.dataset.buildingInternalName;
                    // Refresh the panel after a short delay to allow backend processing
                    setTimeout(() => {
                         fetchAndRenderMarketPanel(villageId, buildingInternalName); // Assuming this function exists and fetches market data
                    }, 1000); // Delay by 1 second
              }
        }
    });
}

// Setup interval for updating trade popup timers
let tradeTimerInterval = null;
function startTradeTimerInterval() {
    if (tradeTimerInterval === null) {
        tradeTimerInterval = setInterval(updateTradeTimersPopup, 1000);
    }
}

// Function to handle Send Resources form submission via AJAX
function setupMarketListeners(villageId, buildingInternalName) {
    const sendResourcesForm = document.getElementById('send-resources-form');
    if (sendResourcesForm) {
        sendResourcesForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            const sendButton = form.querySelector('.send-button');
            if (sendButton) {
                sendButton.disabled = true;
                sendButton.textContent = 'Wysyłanie...'; // Show loading state
            }

            try {
                 // Add csrf_token to form data
                 const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                 formData.append('csrf_token', csrfToken);

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
                    },
                    body: new URLSearchParams(formData).toString()
                });
                const data = await response.json();

                if (data.status === 'success') {
                    window.toastManager.showToast(data.message, 'success');
                    // Refresh the market panel and trader count
                     fetchAndRenderMarketPanel(villageId, buildingInternalName); // Assuming this function exists and fetches market data

                    // Update resources display
                    if (window.resourceUpdater && data.data && data.data.village_info) {
                         window.resourceUpdater.resources.wood.amount = data.data.village_info.wood;
                         window.resourceUpdater.resources.clay.amount = data.data.village_info.clay;
                         window.resourceUpdater.resources.iron.amount = data.data.village_info.iron;
                          window.resourceUpdater.updateUI();
                     }

                } else {
                    window.toastManager.showToast(data.message || 'Błąd wysyłania surowców.', 'error');
                }

            } catch (error) {
                console.error('Błąd AJAX wysyłania surowców:', error);
                window.toastManager.showToast('Błąd komunikacji z serwera podczas wysyłania surowców.', 'error');
            } finally {
                // Re-enable button regardless of success or failure
                if (sendButton) {
                    sendButton.disabled = false;
                    sendButton.textContent = 'Wyślij zasoby'; // Restore original text
                }
            }
        });
    }
}

// Add a function to fetch and render the Market panel (to be called from buildings.js)
async function fetchAndRenderMarketPanel(villageId, buildingInternalName) {
     const actionContent = document.getElementById('popup-action-content');
     const detailsContent = document.getElementById('building-details-content');
     if (!actionContent || !detailsContent || !villageId || !buildingInternalName) {
         console.error('Missing elements or parameters for market panel.');
         return;
     }

     // Show loading indicator
     actionContent.innerHTML = '<p>Ładowanie panelu rynku...</p>';
     actionContent.style.display = 'block';
     detailsContent.style.display = 'none'; // Hide details when showing action content

     // Add data attributes to actionContent for easy access in timer updates
     actionContent.dataset.villageId = villageId;
     actionContent.dataset.buildingInternalName = buildingInternalName;

     try {
         // Use the existing get_building_action.php endpoint
         const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
         const data = await response.json();

         if (data.status === 'success' && data.action_type === 'trade') {
             // Server already returns HTML in additional_info_html
             actionContent.innerHTML = data.data.additional_info_html;
             setupMarketListeners(villageId, buildingInternalName); // Setup listeners after rendering
              updateTradeTimersPopup(); // Start timers for the popup queue

         } else if (data.error) {
             actionContent.innerHTML = '<p>Błąd ładowania panelu rynku: ' + data.error + '</p>';
             window.toastManager.showToast(data.error, 'error');
         } else {
              actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy rynku.</p>';
         }

     } catch (error) {
         console.error('Błąd AJAX pobierania panelu rynku:', error);
         actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
         window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania panelu rynku.', 'error');
     }
}

// Add the fetchAndRenderMarketPanel function to the global scope or make it accessible
// window.fetchAndRenderMarketPanel = fetchAndRenderMarketPanel;

// Ensure timers start when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Start the interval for trade popup timers
     startTradeTimerInterval();
}); 