/**
 * Frontend logic for Unit Recruitment and Queue
 */

// Function to format time duration (can reuse formatDuration from buildings.js if needed, or define here)
function formatDuration(seconds) {
    if (seconds < 0) seconds = 0;
    const d = Math.floor(seconds / (3600 * 24));
    const h = Math.floor((seconds % (3600 * 24)) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    let parts = [];
    if (d > 0) parts.push(d + 'd');
    if (h > 0 || d > 0) parts.push(h.toString().padStart(2, '0') + 'h');
    parts.push(m.toString().padStart(2, '0') + 'm');
    parts.push(s.toString().padStart(2, '0') + 's');

    return parts.join(' ');
}

// Function to fetch and render the recruitment panel and queue
async function fetchAndRenderRecruitmentPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    if (!actionContent || !villageId || !buildingInternalName) {
        console.error('Missing elements or parameters for recruitment panel.');
        return;
    }

    actionContent.innerHTML = '<p>Ładowanie...</p>'; // Show loading indicator

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?building_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && (data.action_type === 'recruit_barracks' || data.action_type === 'recruit_stable' || data.action_type === 'recruit_siege')) {
            const units = data.data.available_units;
            const queue = data.data.recruitment_queue;
            const buildingName = data.data.building_name_pl;
            const buildingLevel = data.data.building_level;

            // Render the recruitment panel HTML
            let html = `
                <h3>${buildingName} (Poziom ${buildingLevel}) - Rekrutacja</h3>
                <p>Tutaj możesz rekrutować jednostki wojskowe.</p>
            `;

            // Render recruitment queue
            html += '<h4>Kolejka rekrutacji:</h4>';
            html += '<div id="recruitment-queue-popup-list" class="queue-content">'
            if (queue.length > 0) {
                html += '<table class="recruitment-queue">';
                html += '<tr><th>Jednostka</th><th>Liczba</th><th>Pozostały czas</th><th>Akcja</th></tr>';
                queue.forEach(item => {
                    html += `
                        <tr data-queue-id="${item.id}">
                            <td>${item.unit_name_pl}</td>
                            <td>${item.count_finished} / ${item.count}</td>
                            <td class="recruitment-timer" data-ends-at="${item.finish_at}" data-start-time="${item.started_at}">${formatDuration(item.time_remaining)}</td>
                            <td><button class="cancel-recruitment-button" data-queue-id="${item.id}">Anuluj</button></td>
                        </tr>
                    `;
                });
                 html += '</table>';
            } else {
                html += '<p class="queue-empty">Brak zadań w kolejce rekrutacji.</p>';
            }
            html += '</div>';

            // Render available units for recruitment
            html += '<h4>Dostępne jednostki:</h4>';
            if (units.length > 0) {
                html += '<form action="recruit_units.php" method="post" class="recruit-form">';
                html += '<input type="hidden" name="village_id" value="${villageId}">';
                 html += '<input type="hidden" name="building_internal_name" value="${buildingInternalName}">'; // Potrzebne do identyfikacji skąd rekrutujemy
                html += '<table class="recruitment-units">';
                html += '<tr><th>Jednostka</th><th colspan="4">Koszt</th><th>Populacja</th><th>Czas rekrutacji</th><th>Atak/Obrona</th><th>Posiadane</th><th>Rekrutuj</th></tr>';

                units.forEach(unit => {
                    const canRecruit = unit.can_recruit;
                    html += `
                        <tr data-unit-internal-name="${unit.internal_name}" class="${canRecruit ? '' : 'unavailable'}">
                            <td><strong>${unit.name_pl}</strong><br><small>${unit.description_pl}</small></td>
                            <td><img src="img/resources/wood.png" title="Drewno" alt="Drewno"> ${formatNumber(unit.cost_wood)}</td>
                            <td><img src="img/resources/clay.png" title="Glina" alt="Glina"> ${formatNumber(unit.cost_clay)}</td>
                            <td><img src="img/resources/iron.png" title="Żelazo" alt="Żelazo"> ${formatNumber(unit.cost_iron)}</td>
                             <td><img src="img/ds_graphic/unit/${unit.internal_name}/population.png" title="Populacja" alt="Populacja"> ${formatNumber(unit.population_cost)}</td>
                            <td>${formatDuration(unit.recruit_time_seconds)}</td>
                            <td>${unit.attack}/${unit.defense}</td>
                            <td><span class="owned-units">${formatNumber(unit.owned)}</span></td>
                            <td>
                    `;
                    if (canRecruit) {
                         html += `
                             <input type="number" name="count[${unit.internal_name}]" class="recruit-count" min="0" value="0">
                         `;
                    } else {
                         html += `<span title="${unit.disable_reason}">Niedostępne</span>`;
                    }
                     html += `</td></tr>`;
                });

                html += '</table>';
                 // Add a single recruit button for all unit types
                 html += '<button type="submit" class="btn-primary" style="margin-top: 15px;">Rekrutuj zaznaczone jednostki</button>';
                html += '</form>';
            } else {
                html += '<p>Brak dostępnych jednostek do rekrutacji w tym budynku.</p>';
            }

            actionContent.innerHTML = html;
            setupRecruitmentFormListeners(villageId); // Setup listeners for the form and cancel buttons
            updateRecruitmentTimersPopup(); // Start timers for the popup queue

        } else if (data.error) {
            actionContent.innerHTML = '<p>Błąd ładowania: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy rekrutacji.</p>';
        }

    } catch (error) {
        console.error('Błąd AJAX pobierania panelu rekrutacji:', error);
        actionContent.innerHTML = '<p>Błąd komunikacji z serwerem.</p>';
        window.toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
    }
}

// Function to update timers specifically within the recruitment popup
function updateRecruitmentTimersPopup() {
    const timers = document.querySelectorAll('#recruitment-queue-popup-list .recruitment-timer');
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
            // For simplicity now, we just mark it finished. A full refresh is better.
            const queueItemElement = timerElement.closest('tr[data-queue-id]');
            if(queueItemElement) {
                queueItemElement.classList.add('finished');
                 // Optionally, remove the cancel button for finished items
                 const cancelButton = queueItemElement.querySelector('.cancel-recruitment-button');
                 if(cancelButton) cancelButton.remove();
            }
        }
    });
}

// Setup interval for updating recruitment popup timers
// Note: This interval should ideally be managed more carefully, e.g., started when popup opens, cleared when it closes.
// For now, a simple global interval is used.
let recruitmentTimerInterval = null;
function startRecruitmentTimerInterval() {
    if (recruitmentTimerInterval === null) {
        recruitmentTimerInterval = setInterval(updateRecruitmentTimersPopup, 1000);
    }
}

// Function to handle recruitment form submission via AJAX
function setupRecruitmentFormListeners(villageId) {
    const recruitForm = document.querySelector('#popup-action-content .recruit-form');
    if (recruitForm) {
        recruitForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            // Filter out units with count 0
            const unitsToRecruit = {};
            let totalRecruitCount = 0;
            for (const pair of formData.entries()) {
                 if (pair[0].startsWith('count[') && parseInt(pair[1]) > 0) {
                      const unitInternalName = pair[0].substring(6, pair[0].length - 1);
                      unitsToRecruit[unitInternalName] = parseInt(pair[1]);
                      totalRecruitCount += parseInt(pair[1]);
                 }
            }

            if (totalRecruitCount === 0) {
                 window.toastManager.showToast('Wprowadź liczbę jednostek do rekrutacji.', 'info');
                 return;
            }

            // Add village_id and building_internal_name to the data sent
            const postData = new URLSearchParams();
             postData.append('village_id', villageId);
             postData.append('building_internal_name', formData.get('building_internal_name'));
             postData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
             // Append unit counts
             for (const unitInternalName in unitsToRecruit) {
                  postData.append(`units[${unitInternalName}]`, unitsToRecruit[unitInternalName]);
             }

            try {
                const response = await fetch('recruit_units.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
                    },
                    body: postData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    window.toastManager.showToast(data.message, 'success');
                    // Refresh the recruitment panel and queue
                    const buildingInternalName = form.elements['building_internal_name'].value;
                     fetchAndRenderRecruitmentPanel(villageId, buildingInternalName);

                    // Update resources (population might change)
                    if (window.resourceUpdater && data.data && data.data.village_info) {
                        window.resourceUpdater.resources.wood.amount = data.data.village_info.wood;
                        window.resourceUpdater.resources.clay.amount = data.data.village_info.clay;
                        window.resourceUpdater.resources.iron.amount = data.data.village_info.iron;
                        window.resourceUpdater.resources.population.amount = data.data.village_info.population;
                        // Capacity updates might be needed if Farm capacity affects total population
                        // window.resourceUpdater.resources.population.capacity = data.data.village_info.farm_capacity; // Assuming farm_capacity is sent
                         window.resourceUpdater.updateUI();
                    }
                     // Update owned unit counts in the popup without a full refresh
                     if (data.data && data.data.updated_units) {
                          for(const unitInternalName in data.data.updated_units) {
                               const ownedUnitsSpan = document.querySelector(`#popup-action-content tr[data-unit-internal-name="${unitInternalName}"] .owned-units`);
                               if(ownedUnitsSpan) {
                                    ownedUnitsSpan.textContent = formatNumber(data.data.updated_units[unitInternalName]);
                               }
                          }
                     }

                } else {
                    window.toastManager.showToast(data.message || 'Błąd rekrutacji.', 'error');
                }

            } catch (error) {
                console.error('Błąd AJAX rekrutacji:', error);
                window.toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
            }
        });
    }

    // Setup listener for cancel recruitment buttons (using event delegation on the popup content)
    const popupActionContent = document.getElementById('popup-action-content');
    if (popupActionContent) {
        popupActionContent.addEventListener('click', async function(event) {
            const cancelButton = event.target.closest('.cancel-recruitment-button');
            if (!cancelButton) return; // Not a cancel button click

            const queueItemId = cancelButton.dataset.queueId;
            if (!queueItemId) {
                console.error('Missing queue item ID for cancellation.');
                return;
            }

            if (!confirm('Czy na pewno chcesz anulować rekrutację? Odzyskasz 90% surowców i populacji.')) {
                return;
            }

            try {
                const response = await fetch('cancel_recruitment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX
                    },
                    body: `queue_id=${queueItemId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}&ajax=1`
                });
                const data = await response.json();

                if (data.success) {
                    window.toastManager.showToast(data.message, 'success');
                    // Refresh the recruitment panel and queue
                    const recruitFormInPopup = popupActionContent.querySelector('.recruit-form');
                    if(recruitFormInPopup) {
                         const buildingInternalName = recruitFormInPopup.elements['building_internal_name'].value;
                         fetchAndRenderRecruitmentPanel(villageId, buildingInternalName);
                    }

                    // Update resources (population and refunded resources)
                    if (window.resourceUpdater && data.village_info) {
                        window.resourceUpdater.resources.wood.amount = data.village_info.wood;
                        window.resourceUpdater.resources.clay.amount = data.village_info.clay;
                        window.resourceUpdater.resources.iron.amount = data.village_info.iron;
                         window.resourceUpdater.resources.population.amount = data.village_info.population;
                         window.resourceUpdater.resources.population.capacity = data.village_info.farm_capacity; // Assuming farm_capacity is sent
                         window.resourceUpdater.updateUI();
                    }

                } else {
                    window.toastManager.showToast(data.error || data.message || 'Błąd anulowania rekrutacji.', 'error');
                }

            } catch (error) {
                console.error('Błąd AJAX anulowania rekrutacji:', error);
                window.toastManager.showToast('Błąd komunikacji z serwera podczas anulowania rekrutacji.', 'error');
            }
        });
    }
}

// Add the function to the global scope or make it accessible if needed by buildings.js
// window.fetchAndRenderRecruitmentPanel = fetchAndRenderRecruitmentPanel;

// Ensure timers start when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Start the interval for recruitment popup timers
     startRecruitmentTimerInterval();
}); 