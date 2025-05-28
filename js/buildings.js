/**
 * Obsługa interakcji z budynkami (popupy, rozbudowa AJAX)
 */

// Funkcja do formatowania czasu w sekundach na format Dni GG:MM:SS
function formatDuration(seconds) {
    if (seconds < 0) seconds = 0; // Nie pokazuj ujemnego czasu
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

// Funkcja do aktualizacji timerów na stronie
function updateTimers() {
    const timers = document.querySelectorAll('[data-ends-at]');
    const currentTime = Math.floor(Date.now() / 1000); // Aktualny czas w sekundach (Unix timestamp)

    timers.forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt, 10);
        const remainingTime = finishTime - currentTime;

        // Znajdź element paska postępu i jego rodzica (item-progress)
        const progressContainer = timerElement.closest('.item-progress');
        const progressBarFill = progressContainer ? progressContainer.querySelector('.progress-fill') : null;

        if (remainingTime > 0) {
            timerElement.textContent = formatDuration(remainingTime);
            
            // Oblicz i zaktualizuj pasek postępu
            if (progressBarFill && timerElement.dataset.startTime) { // Potrzebujemy start_time do obliczeń
                 const startTime = parseInt(timerElement.dataset.startTime, 10);
                 const duration = finishTime - startTime;
                 // Unikaj dzielenia przez zero, jeśli czas trwania jest 0 lub ujemny (natychmiastowe zadania)
                 const progress = duration > 0 ? ((duration - remainingTime) / duration) * 100 : 100; 
                 progressBarFill.style.width = `${Math.min(100, Math.max(0, progress))}%`; // Upewnij się, że szerokość jest między 0% a 100%
            }

        } else {
            timerElement.textContent = 'Zakończono!';
            timerElement.classList.add('timer-finished');
            if (progressBarFill) progressBarFill.style.width = '100%'; // Upewnij się, że pasek jest pełny
            timerElement.removeAttribute('data-ends-at'); // Zatrzymaj odświeżanie tego timera

            // Znajdź powiązany element budynku (building-item i building-placeholder)
            const queueItemElement = timerElement.closest('.queue-item');
            if (queueItemElement) {
                const buildingNameElement = queueItemElement.querySelector('.building-name');
                if (buildingNameElement) {
                    // Zakładamy, że nazwa budynku w kolejce odpowiada internal_name z building_types
                    const buildingInternalName = buildingNameElement.textContent.trim(); // Może wymagać innej logiki do pobrania internal_name
                    
                    // Usuń klasę building-upgrading z odpowiedniego placeholdera
                    const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${buildingInternalName}']`);
                    if (buildingPlaceholder) {
                        buildingPlaceholder.classList.remove('building-upgrading');
                    }
                     // Znajdź building-item i usuń status 'w trakcie rozbudowy'
                     const buildingItem = document.querySelector(`.building-item[data-internal-name='${buildingInternalName}']`);
                      if (buildingItem) {
                           // Znajdź status i timer i usuń je lub zaktualizuj
                           const statusElement = buildingItem.querySelector('.upgrade-status');
                           if (statusElement) statusElement.textContent = `Rozbudowa do poziomu ${parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1}:`;
                           const timerElementInItem = buildingItem.querySelector('.upgrade-timer');
                           if (timerElementInItem) timerElementInItem.remove();
                           
                           // Włącz przycisk rozbudowy, jeśli nie osiągnięto max poziomu
                           const upgradeButton = buildingItem.querySelector('.upgrade-button');
                           if (upgradeButton && !buildingItem.dataset.maxLevel || parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1 <= parseInt(buildingItem.dataset.maxLevel || Infinity, 10)) { // Sprawdź max poziom
                               upgradeButton.disabled = false;
                               upgradeButton.classList.remove('btn-secondary');
                               upgradeButton.classList.add('btn-primary');
                           }
                      }
                }
                // Usuń element kolejki z DOM
                queueItemElement.remove();
            }
            
            updateBuildingQueue(); // Odśwież kolejkę (np. jeśli były inne zadania w tle, co na razie jest wyłączone)
            if (window.resourceUpdater) {
                 window.resourceUpdater.fetchUpdate(); // Aktualizuj zasoby (populacja, magazyn)
             }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const popupOverlay = document.getElementById('popup-overlay');
    const buildingDetailsPopup = document.getElementById('building-details-popup');
    const popupCloseBtn = document.getElementById('popup-close-btn');

    const popupBuildingName = document.getElementById('popup-building-name');
    const popupBuildingDescription = document.getElementById('popup-building-description');
    const popupCurrentLevel = document.getElementById('popup-current-level');
    const popupProductionInfo = document.getElementById('popup-production-info');
    const popupCapacityInfo = document.getElementById('popup-capacity-info');
    const popupNextLevel = document.getElementById('popup-next-level');
    const popupUpgradeCosts = document.getElementById('popup-upgrade-costs');
    const popupUpgradeTime = document.getElementById('popup-upgrade-time');
    const popupRequirements = document.getElementById('popup-requirements');
    const popupUpgradeReason = document.getElementById('popup-upgrade-reason');
    const popupUpgradeButton = document.getElementById('popup-upgrade-button');
    const popupActionContent = document.getElementById('popup-action-content');

    let currentVillageId = window.currentVillageId; // Pobierz ID wioski z globalnej zmiennej

    // Funkcja do otwierania popupu z detalami budynku
    async function openBuildingDetailsPopup(villageId, internalName) {
        if (!villageId || !internalName) {
            console.error('Brak villageId lub internalName dla popupu budynku.');
            return;
        }

        // Pokaż loader
        popupBuildingName.textContent = 'Ładowanie...';
        popupBuildingDescription.textContent = '';
        popupCurrentLevel.textContent = '';
        popupProductionInfo.textContent = '';
        popupCapacityInfo.textContent = '';
        popupNextLevel.textContent = '';
        popupUpgradeCosts.textContent = '';
        popupUpgradeTime.textContent = '';
        popupRequirements.innerHTML = '';
        popupUpgradeReason.textContent = '';
        popupUpgradeButton.style.display = 'none';
        popupActionContent.innerHTML = '';

        buildingDetailsPopup.classList.remove('main-building-popup'); // Resetuj klasę dla ratusza
        buildingDetailsPopup.style.display = 'block';
        popupOverlay.style.display = 'block';

        try {
            const response = await fetch(`get_building_details.php?village_id=${villageId}&building_internal_name=${internalName}`);
            const data = await response.json();

            if (data.error) {
                console.error('Błąd pobierania detali budynku:', data.error);
                window.toastManager.showToast(data.error, 'error');
                closeBuildingDetailsPopup();
                return;
            }

            // Wypełnij popup danymi
            popupBuildingName.textContent = `${data.name_pl} (Poziom ${data.level})`;
            popupBuildingDescription.textContent = data.description_pl;
            popupCurrentLevel.textContent = data.level;

            // Informacje o produkcji/pojemności
            if (data.production_info) {
                if (data.production_info.type === 'production') {
                    popupProductionInfo.textContent = `Produkcja: ${formatNumber(data.production_info.amount_per_hour)}/godz. ${data.production_info.resource_type}`;
                    if (data.production_info.amount_per_hour_next_level) {
                        popupProductionInfo.textContent += ` (Nast. poz.: +${formatNumber(data.production_info.amount_per_hour_next_level)})`;
                    }
                    popupProductionInfo.style.display = 'block';
                    popupCapacityInfo.style.display = 'none';
                } else if (data.production_info.type === 'capacity') {
                    popupCapacityInfo.textContent = `Pojemność: ${formatNumber(data.production_info.amount)}`;
                    if (data.production_info.amount_next_level) {
                        popupCapacityInfo.textContent += ` (Nast. poz.: ${formatNumber(data.production_info.amount_next_level)})`;
                    }
                    popupCapacityInfo.style.display = 'block';
                    popupProductionInfo.style.display = 'none';
                }
            } else {
                popupProductionInfo.style.display = 'none';
                popupCapacityInfo.style.display = 'none';
            }

            // Informacje o rozbudowie
            if (data.is_upgrading) {
                popupNextLevel.textContent = data.queue_level_after;
                popupUpgradeCosts.innerHTML = `<p class="upgrade-status">W trakcie rozbudowy do poziomu ${data.queue_level_after}.</p>`;
                popupUpgradeTime.innerHTML = `<p class="upgrade-timer" data-ends-at="${data.queue_finish_time}">${getRemainingTimeText(data.queue_finish_time)}</p>`;
                popupUpgradeButton.style.display = 'none';
                popupUpgradeReason.textContent = data.upgrade_not_available_reason;
                popupUpgradeReason.style.display = 'block';
            } else if (data.level >= data.max_level) {
                popupNextLevel.textContent = data.max_level;
                popupUpgradeCosts.innerHTML = `<p class="upgrade-status">Osiągnięto maksymalny poziom (${data.max_level}).</p>`;
                popupUpgradeTime.textContent = '';
                popupUpgradeButton.style.display = 'none';
                popupUpgradeReason.textContent = data.upgrade_not_available_reason;
                popupUpgradeReason.style.display = 'block';
            } else {
                popupNextLevel.textContent = data.level + 1;
                if (data.upgrade_costs) {
                    popupUpgradeCosts.innerHTML = `Koszt: 
                        <span class="resource-cost wood"><img src="img/wood.png" alt="Drewno"> ${formatNumber(data.upgrade_costs.wood)}</span> 
                        <span class="resource-cost clay"><img src="img/clay.png" alt="Glina"> ${formatNumber(data.upgrade_costs.clay)}</span> 
                        <span class="resource-cost iron"><img src="img/iron.png" alt="Żelazo"> ${formatNumber(data.upgrade_costs.iron)}</span>`;
                    popupUpgradeTime.textContent = `Czas budowy: ${data.upgrade_time_formatted}`;
                    
                    // Wymagania
                    if (data.requirements && data.requirements.length > 0) {
                        let reqHtml = '<div class="building-requirements"><p>Wymagania:</p><ul>';
                        data.requirements.forEach(req => {
                            reqHtml += `<li>${req.name_pl} (Poziom ${req.required_level})</li>`;
                        });
                        reqHtml += '</ul></div>';
                        popupRequirements.innerHTML = reqHtml;
                        popupRequirements.style.display = 'block';
                    } else {
                        popupRequirements.style.display = 'none';
                    }

                    if (data.can_upgrade) {
                        popupUpgradeButton.style.display = 'block';
                        popupUpgradeButton.textContent = `Rozbuduj do poziomu ${data.level + 1}`;
                        popupUpgradeButton.dataset.villageId = villageId;
                        popupUpgradeButton.dataset.buildingInternalName = internalName;
                        popupUpgradeButton.dataset.currentLevel = data.level;
                        popupUpgradeReason.style.display = 'none';
                    } else {
                        popupUpgradeButton.style.display = 'none';
                        popupUpgradeReason.textContent = data.upgrade_not_available_reason;
                        popupUpgradeReason.style.display = 'block';
                    }
                } else {
                    popupUpgradeCosts.textContent = 'Nie można obliczyć kosztów rozbudowy.';
                    popupUpgradeTime.textContent = '';
                    popupUpgradeButton.style.display = 'none';
                    popupUpgradeReason.textContent = data.upgrade_not_available_reason || 'Brak danych do rozbudowy.';
                    popupUpgradeReason.style.display = 'block';
                }
            }

            // Specjalna obsługa dla Ratusza (Main Building)
            if (internalName === 'main_building') {
                buildingDetailsPopup.classList.add('main-building-popup');
                // Tutaj można załadować listę wszystkich budynków do rozbudowy
                // fetchAndRenderAllBuildingsForMainBuilding(villageId);
            } else {
                buildingDetailsPopup.classList.remove('main-building-popup');
            }

            // Uruchom timery w popupie
            updateTimers(); // Funkcja z game.php
            setInterval(updateTimers, 1000); // Upewnij się, że timery są aktualizowane
            
        } catch (error) {
            console.error('Błąd AJAX pobierania detali budynku:', error);
            window.toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
            closeBuildingDetailsPopup();
        }
    }

    // Funkcja do zamykania popupu
    function closeBuildingDetailsPopup() {
        buildingDetailsPopup.style.display = 'none';
        popupOverlay.style.display = 'none';
        popupActionContent.innerHTML = ''; // Wyczyść zawartość akcji
    }

    // Obsługa kliknięć na placeholdery budynków
    document.querySelectorAll('.building-placeholder').forEach(placeholder => {
        placeholder.addEventListener('click', function() {
            const internalName = this.dataset.buildingInternalName;
            openBuildingDetailsPopup(currentVillageId, internalName);
        });
    });

    // Obsługa kliknięć na przyciski akcji w liście budynków (jeśli są)
    document.querySelectorAll('.building-item .building-action-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const internalName = this.dataset.buildingInternalName;
            openBuildingDetailsPopup(currentVillageId, internalName);
        });
    });

    // Obsługa kliknięcia na przycisk zamknięcia popupu
    popupCloseBtn.addEventListener('click', closeBuildingDetailsPopup);
    popupOverlay.addEventListener('click', closeBuildingDetailsPopup); // Zamknij po kliknięciu na overlay

    // Obsługa kliknięcia na przycisk "Rozbuduj" w popupie
    popupUpgradeButton.addEventListener('click', async function() {
        const button = this;
        const villageId = button.dataset.villageId;
        const buildingInternalName = button.dataset.buildingInternalName;
        const currentLevel = button.dataset.currentLevel;

        // Wyślij żądanie AJAX do upgrade_building.php
        try {
            const response = await fetch('upgrade_building.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `village_id=${villageId}&building_type_internal_name=${buildingInternalName}&current_level=${currentLevel}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
            });
            const data = await response.json();

            if (data.status === 'success') {
                window.toastManager.showToast(data.message, 'success');
                closeBuildingDetailsPopup();
                // Zaktualizuj zasoby
                if (window.resourceUpdater) {
                    window.resourceUpdater.fetchUpdate();
                }
                // Zaktualizuj kolejkę budowy
                updateBuildingQueue();
                // Opcjonalnie: zaktualizuj poziom budynku w widoku wioski i liście budynków
                // To wymagałoby bardziej złożonej logiki, na razie wystarczy odświeżenie kolejki i zasobów
            } else {
                window.toastManager.showToast(data.message || 'Błąd rozbudowy.', 'error');
            }
        } catch (error) {
            console.error('Błąd AJAX rozbudowy:', error);
            window.toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
        }
    });

    // Funkcja do aktualizacji kolejki budowy
    async function updateBuildingQueue() {
        const buildingQueueList = document.getElementById('building-queue-list');
        if (!buildingQueueList || !currentVillageId) return;

        try {
            const response = await fetch(`ajax/buildings/get_queue.php?village_id=${currentVillageId}`);
            const data = await response.json();

            if (data.status === 'success') {
                const queueItem = data.data.queue_item;
                buildingQueueList.innerHTML = ''; // Wyczyść obecną kolejkę

                if (queueItem) {
                    const queueHtml = `
                        <div class="queue-item current">
                            <div class="item-header">
                                <div class="item-title">
                                    <span class="building-name">${queueItem.building_name_pl}</span>
                                    <span class="building-level">Poziom ${queueItem.level}</span>
                                </div>
                                <div class="item-actions">
                                    <button class="cancel-button" data-queue-id="${queueItem.id}" title="Anuluj budowę">✖</button>
                                </div>
                            </div>
                            <div class="item-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%;"></div>
                                </div>
                                <div class="progress-time" data-ends-at="${queueItem.finish_time}" data-start-time="${queueItem.start_time}"></div>
                            </div>
                        </div>
                    `;
                    buildingQueueList.innerHTML = queueHtml;
                    // Uruchom timery ponownie dla nowo dodanego elementu
                    updateTimers();
                } else {
                    buildingQueueList.innerHTML = '<p class="queue-empty">Brak zadań w kolejce budowy.</p>';
                }
            } else {
                console.error('Błąd pobierania kolejki budowy:', data.message);
                window.toastManager.showToast('Błąd pobierania kolejki budowy.', 'error');
            }
        } catch (error) {
            console.error('Błąd AJAX kolejki budowy:', error);
            window.toastManager.showToast('Błąd komunikacji z serwerem.', 'error');
        }
    }

    // === Obsługa anulowania zadania budowy ===
    // Dodaj event listener do przycisku anulowania w kolejce budowy
    document.addEventListener('click', async function(event) {
        const cancelButton = event.target.closest('.cancel-button');
        if (!cancelButton) return; // Kliknięcie nie było na przycisku anulowania

        const queueItemId = cancelButton.dataset.queueId;
        if (!queueItemId) {
            console.error('Brak ID zadania budowy do anulowania.');
            return;
        }

        // Potwierdzenie anulowania przez użytkownika
        if (!confirm('Czy na pewno chcesz anulować tę budowę? Odzyskasz 90% surowców.')) {
            return;
        }

        try {
            // Wyślij żądanie AJAX do cancel_upgrade.php
            const response = await fetch('cancel_upgrade.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `queue_item_id=${queueItemId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}&ajax=1` // Dodaj flagę ajax
            });
            const data = await response.json();

            if (data.success) {
                window.toastManager.showToast(data.message, 'success');
                // Zaktualizuj zasoby
                if (window.resourceUpdater && data.village_info) {
                    // Możemy bezpośrednio zaktualizować dane zasobów w resourceUpdater
                    window.resourceUpdater.resources.wood.amount = data.village_info.wood;
                    window.resourceUpdater.resources.clay.amount = data.village_info.clay;
                    window.resourceUpdater.resources.iron.amount = data.village_info.iron;
                    window.resourceUpdater.resources.population.amount = data.village_info.population; // Populacja może się zmienić po anulowaniu farmy
                    // Zaktualizuj pojemności (mogą się zmienić jeśli anulowano magazyn/farmę)
                    window.resourceUpdater.resources.wood.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.clay.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.iron.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.population.capacity = data.village_info.farm_capacity;

                    window.resourceUpdater.updateUI(); // Zaktualizuj wyświetlanie
                }
                // Zaktualizuj kolejkę budowy - usunięcie elementu z kolejki
                updateBuildingQueue(); // Najprostsze rozwiązanie to ponowne załadowanie

                // Ponownie włącz przycisk rozbudowy dla anulowanego budynku
                // Znajdź element building-item lub building-placeholder na podstawie internal_name
                const buildingItem = document.querySelector(`.building-item[data-internal-name='${data.building_internal_name}']`);
                const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${data.building_internal_name}']`);
                
                if (buildingItem) {
                    // Znajdź przycisk rozbudowy w tym building-item i włącz go
                    const upgradeButton = buildingItem.querySelector('.upgrade-button');
                    if (upgradeButton) {
                        upgradeButton.disabled = false;
                        upgradeButton.classList.remove('btn-secondary');
                         upgradeButton.classList.add('btn-primary');
                         // Usuń powód niedostępności, jeśli istniał
                         const reasonElement = buildingItem.querySelector('.upgrade-unavailable-reason');
                         if(reasonElement) reasonElement.style.display = 'none';
                    }
                     // Usuń status 'w trakcie rozbudowy' i timer
                     const statusElement = buildingItem.querySelector('.upgrade-status');
                     if (statusElement && statusElement.textContent.includes('W trakcie rozbudowy')) {
                          statusElement.textContent = `Rozbudowa do poziomu ${parseInt(buildingItem.dataset.currentLevel, 10) + 1}:`;
                     }
                     const timerElement = buildingItem.querySelector('.upgrade-timer');
                     if (timerElement) timerElement.remove();
                }

                 if (buildingPlaceholder) {
                     buildingPlaceholder.classList.remove('building-upgrading');
                 }

            } else {
                window.toastManager.showToast(data.error || data.message || 'Błąd anulowania budowy.', 'error');
            }
        } catch (error) {
            console.error('Błąd AJAX anulowania budowy:', error);
            window.toastManager.showToast('Błąd komunikacji z serwerem podczas anulowania.', 'error');
        }
    });
    // =========================================

    // Uruchom timery po załadowaniu DOM i odświeżaj co sekundę
    updateTimers(); // Początkowe wywołanie
    setInterval(updateTimers, 1000); // Odświeżaj co sekundę
});
