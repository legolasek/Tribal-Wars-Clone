/**
 * Frontend logic for Research Panel and Queue
 */

// Assume formatDuration is available globally from buildings.js or units.js
// function formatDuration(seconds) { ... }

// Function to fetch and render the research panel and queue
async function fetchAndRenderResearchPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || !buildingInternalName) {
        console.error('Missing elements or parameters for research panel.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Ładowanie panelu badań...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && (data.action_type === 'research' || data.action_type === 'research_advanced')) {
            const researchOptions = data.data.available_research;
            const researchQueue = data.data.research_queue;
            const buildingName = data.data.building_name_pl;
            const buildingLevel = data.data.building_level;
            const villageResources = data.data.current_village_resources; // Assuming this is sent

            // Render the research panel HTML
            let html = `
                <h3>${buildingName} (Poziom ${buildingLevel}) - Badania</h3>
                <p>Tutaj możesz badać nowe technologie.</p>
            `;

            // Render research queue
            html += '<h4>Aktualne badanie:</h4>';
            html += '<div id="research-queue-popup-list" class="queue-content">'
            if (researchQueue.length > 0) {
                 html += '<table class="research-queue">';
                 html += '<tr><th>Badanie</th><th>Docelowy poziom</th><th>Pozostały czas</th><th>Akcja</th></tr>';

                 researchQueue.forEach(item => {
                     // Assuming item includes: id, research_name, level_after, ends_at, time_remaining
                     html += `
                         <tr data-queue-id="${item.id}">
                             <td>${item.research_name}</td>
                             <td>${item.level_after}</td>
                             <td class="research-timer" data-ends-at="${item.finish_at}" data-start-time="${item.started_at}">${formatDuration(item.time_remaining)}</td>
                             <td><button class="cancel-research-button" data-queue-id="${item.id}">Anuluj</button></td>
                         </tr>
                     `;
                 });

                 html += '</table>';
            } else {
                html += '<p class="queue-empty">Brak badań w kolejce.</p>';
            }
            html += '</div>';

            // Render available research options
            html += '<h4>Dostępne badania:</h4>';
            if (researchOptions.length > 0) {
                html += '<table class="research-options">';
                html += '<tr><th>Technologia</th><th>Poziom</th><th colspan="3">Koszt</th><th>Czas</th><th>Akcja</th></tr>';

                researchOptions.forEach(research => {
                    const currentLevel = research.current_level;
                    const nextLevel = currentLevel + 1;
                    const isAvailable = research.is_available; // From backend check
                    const isInProgress = research.is_in_progress; // From backend check
                    const isAtMaxLevel = currentLevel >= research.max_level;

                    html += `<tr data-research-id="${research.id}" class="${isAvailable && !isInProgress && !isAtMaxLevel ? '' : 'unavailable'}">`;
                    html += `<td><strong>${research.name_pl}</strong><br><small>${research.description}</small></td>`;
                    html += `<td>${currentLevel}/${research.max_level}</td>`;

                    if (isAtMaxLevel) {
                        html += '<td colspan="4">Maksymalny poziom osiągnięty</td>';
                        html += '<td>-</td>';
                        html += '<td><button disabled>Max poziom</button></td>';
                    } else if (isInProgress) {
                         html += '<td colspan="4">W trakcie badania</td>';
                         html += '<td>-</td>';
                         html += '<td><button disabled>W trakcie</button></td>';
                    } else if (!isAvailable) {
                         let reason = research.disable_reason || 'Niedostępne'; // Use reason from backend
                         html += `<td colspan="5">${reason}</td>`;
                         html += `<td><button disabled title="${reason}">Niedostępne</button></td>`;
                    } else { // Available to research
                         const cost = research.cost;
                         const time = research.time_seconds;

                         // Check resources availability on frontend for immediate feedback
                         const canAfford = villageResources &&
                                           villageResources.wood >= cost.wood &&
                                           villageResources.clay >= cost.clay &&
                                           villageResources.iron >= cost.iron;

                         html += `<td><img src="img/wood.png" title="Drewno" alt="Drewno"> ${formatNumber(cost.wood)}</td>`;
                         html += `<td><img src="img/stone.png" title="Glina" alt="Glina"> ${formatNumber(cost.clay)}</td>`;
                         html += `<td><img src="img/iron.png" title="Żelazo" alt="Żelazo"> ${formatNumber(cost.iron)}</td>`;
                         html += `<td>${formatDuration(time)}</td>`;
                         html += `<td>
                            <form action="start_research.php" method="post" class="research-form">
                                <input type="hidden" name="village_id" value="${villageId}">
                                <input type="hidden" name="research_type_id" value="${research.id}">
                                <input type="hidden" name="target_level" value="${nextLevel}">
                                <button type="submit" class="start-research-button btn-primary" ${canAfford ? '' : 'disabled'} title="${canAfford ? '' : 'Brak surowców'}">Badaj</button>
                            </form>
                         </td>`;
                    }

                    html += '</tr>';
                });

                html += '</table>';
            } else {
                html += '<p>Brak dostępnych badań w tym budynku.</p>';
            }

            actionContent.innerHTML = html;
            setupResearchListeners(villageId, buildingInternalName); // Setup listeners
            updateResearchTimersPopup(); // Start timers for the popup queue

        } else if (data.error) {
             actionContent.innerHTML = '<p>Błąd ładowania panelu badań: ' + data.error + '</p>';
             window.toastManager.showToast(data.error, 'error');
         } else {
              actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy badań.</p>';
         }

    } catch (error) {
        console.error('Błąd AJAX pobierania panelu badań:', error);
        actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
        window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania panelu badań.', 'error');
    }
}

// Function to update timers specifically within the research popup
function updateResearchTimersPopup() {
    const timers = document.querySelectorAll('#research-queue-popup-list .research-timer');
    const currentTime = Math.floor(Date.now() / 1000);

    timers.forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt, 10);
        const remainingTime = finishTime - currentTime;

        if (remainingTime > 0) {
            timerElement.textContent = formatDuration(remainingTime);
        } else {
            timerElement.textContent = 'Zakończono!';
            timerElement.classList.add('timer-finished');
            timerElement.removeAttribute('data-ends-at'); // Stop refreshing this timer

            // This timer has finished. We should ideally refresh the queue display.
            const queueItemElement = timerElement.closest('tr[data-queue-id]');
            if(queueItemElement) {
                queueItemElement.classList.add('finished');
                 // Optionally, remove the cancel button for finished items
                 const cancelButton = queueItemElement.querySelector('.cancel-research-button');
                 if(cancelButton) cancelButton.remove();
            }
             // A research finished, might unlock new research or update building logic
             // A full refresh of the research panel might be needed
             const actionContent = document.getElementById('popup-action-content');
             if (actionContent) {
                  const researchTable = actionContent.querySelector('.research-options');
                  if (researchTable) {
                       // Find the building internal name from somewhere (e.g., a data attribute on actionContent)
                       // For now, let's assume it's stored when rendering the panel
                       const popupBuildingInternalName = actionContent.dataset.buildingInternalName; // Need to add this data attribute
                        if (popupBuildingInternalName && window.currentVillageId) {
                             // Refresh the panel after a short delay to allow backend processing
                             setTimeout(() => {
                                 fetchAndRenderResearchPanel(window.currentVillageId, popupBuildingInternalName);
                             }, 1000); // Delay by 1 second
                        }
                  }
             }

        }
    });
}

// Setup interval for updating research popup timers
let researchTimerInterval = null;
function startResearchTimerInterval() {
    if (researchTimerInterval === null) {
        researchTimerInterval = setInterval(updateResearchTimersPopup, 1000);
    }
}

// Function to handle research form submission and cancellation via AJAX
function setupResearchListeners(villageId, buildingInternalName) {
    const researchForm = document.querySelector('#popup-action-content .research-form');
    if (researchForm) {
        researchForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

             const startButton = form.querySelector('.start-research-button');
             if (startButton) {
                  startButton.disabled = true;
                  startButton.textContent = 'Badanie...'; // Show loading state
             }

            try {
                const response = await fetch('start_research.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
                    },
                    body: new URLSearchParams(formData).toString()
                });
                const data = await response.json();

                if (data.status === 'success') {
                    window.toastManager.showToast(data.message, 'success');
                    // Refresh the research panel and queue
                    fetchAndRenderResearchPanel(villageId, buildingInternalName);

                    // Update resources
                    if (window.resourceUpdater && data.data && data.data.village_info) {
                         window.resourceUpdater.resources.wood.amount = data.data.village_info.wood;
                         window.resourceUpdater.resources.clay.amount = data.data.village_info.clay;
                         window.resourceUpdater.resources.iron.amount = data.data.village_info.iron;
                         window.resourceUpdater.resources.population.amount = data.data.village_info.population; // Population might change with some research?
                          window.resourceUpdater.updateUI();
                     }

                } else {
                    window.toastManager.showToast(data.message || 'Błąd rozpoczęcia badania.', 'error');
                     if (startButton) {
                         startButton.disabled = false;
                         startButton.textContent = 'Badaj'; // Restore button text
                    }
                }

            } catch (error) {
                console.error('Błąd AJAX rozpoczęcia badania:', error);
                window.toastManager.showToast('Błąd komunikacji z serwera podczas rozpoczynania badania.', 'error');
                 if (startButton) {
                     startButton.disabled = false;
                     startButton.textContent = 'Badaj'; // Restore button text
                }
            }
        });
    }

    // Setup listener for cancel research buttons
    const popupActionContent = document.getElementById('popup-action-content');
    if (popupActionContent) {
        popupActionContent.addEventListener('click', async function(event) {
            const cancelButton = event.target.closest('.cancel-research-button');
            if (!cancelButton) return;

            const queueItemId = cancelButton.dataset.queueId;
            if (!queueItemId) {
                console.error('Missing queue item ID for cancellation.');
                return;
            }

            if (!confirm('Czy na pewno chcesz anulować to badanie? Odzyskasz 90% surowców.')) {
                return;
            }

            // Disable button and show loading state
            cancelButton.disabled = true;
            cancelButton.textContent = '...';

            try {
                const response = await fetch('cancel_research.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `research_queue_id=${queueItemId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}&ajax=1`
                });
                const data = await response.json();

                if (data.success) {
                    window.toastManager.showToast(data.message, 'success');
                    // Refresh the research panel and queue
                     fetchAndRenderResearchPanel(villageId, buildingInternalName);

                    // Update resources
                    if (window.resourceUpdater && data.village_info) {
                         window.resourceUpdater.resources.wood.amount = data.village_info.wood;
                         window.resourceUpdater.resources.clay.amount = data.village_info.clay;
                         window.resourceUpdater.resources.iron.amount = data.village_info.iron;
                          window.resourceUpdater.updateUI();
                     }

                } else {
                    window.toastManager.showToast(data.error || data.message || 'Błąd anulowania badania.', 'error');
                }

            } catch (error) {
                console.error('Błąd AJAX anulowania badania:', error);
                window.toastManager.showToast('Błąd komunikacji z serwera podczas anulowania badania.', 'error');
            } finally {
                 // Re-enable button regardless of success or failure
                 if (cancelButton && cancelButton.parentNode) {
                      cancelButton.disabled = false;
                      cancelButton.textContent = 'Anuluj'; // Restore original text
                 }
            }
        });
    }
}

// Ensure timers start when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Start the interval for research popup timers
     startResearchTimerInterval();
}); 