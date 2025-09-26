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

        // Znajdź powiązany element kolejki (np. dla budowy)
        const queueItemElement = timerElement.closest('.queue-item'); // Zakładamy, że timer jest wewnątrz .queue-item

        // Znajdź tag img powiązany z tym zadaniem na mapie lub liście budynków
        let buildingImage = null;
        let internalName = null;

        if (queueItemElement && queueItemElement.dataset.buildingInternalName) {
             internalName = queueItemElement.dataset.buildingInternalName;
             // Spróbuj znaleźć grafikę na mapie (placeholder)
             const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${internalName}']`);
             if (buildingPlaceholder) {
                 buildingImage = buildingPlaceholder.querySelector('.building-graphic');
             }
             // TODO: Jeśli nie znaleziono na mapie (np. jesteśmy w widoku innym niż game.php), spróbuj znaleźć na liście budynków
             // To wymagałoby upewnienia się, że elementy listy budynków w game.php (lub innym widoku)
             // zawierają tag <img> z klasą building-graphic i atrybutem data-building-internal-name
             // Obecnie game.php generuje listę BEZ grafik budynków w itemach.
        }


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

            // Zmień grafikę na GIF, jeśli istnieje i nie jest już GIFem
            if (buildingImage) {
                const currentSrc = buildingImage.src;
                // Sprawdź, czy istnieje odpowiadający plik .gif (proste sprawdzenie rozszerzenia)
                // Bardziej zaawansowane sprawdzanie wymagałoby dodatkowych żądań lub mapy dostępnych grafik
                if (currentSrc.endsWith('.png')) {
                    const gifSrc = currentSrc.replace('.png', '.gif');
                     // TODO: Opcjonalnie, sprawdź, czy gifSrc zwraca 200 OK. Na razie zakładamy, że jeśli jest PNG, jest też GIF.
                    buildingImage.src = gifSrc;
                }
            }

        } else {
            timerElement.textContent = 'Zakończono!';
            timerElement.classList.add('timer-finished');
            if (progressBarFill) progressBarFill.style.width = '100%'; // Upewnij się, że pasek jest pełny
            // NIE usuwaj data-ends-at od razu, aby ostatnia aktualizacja mogła ustawić "Zakończono!" i 100% paska
            // Usunięcie elementu kolejki z DOM następuje poniżej, co też skutecznie zatrzyma odświeżanie

             // Zmień grafikę z powrotem na PNG, jeśli jest GIFem
            if (buildingImage) {
                const currentSrc = buildingImage.src;
                if (currentSrc.endsWith('.gif')) {
                    const pngSrc = currentSrc.replace('.gif', '.png');
                    buildingImage.src = pngSrc;
                }
            }

            // Znajdź powiązany element budynku (building-item i building-placeholder)
            // Logic to update building item status and re-enable upgrade button
            // This part is already mostly implemented and should handle updating the list view

             const queueItemElementToRemove = timerElement.closest('.queue-item');
            if (queueItemElementToRemove) {
                // Pobierz internal_name zanim usuniesz element
                const buildingInternalName = queueItemElementToRemove.dataset.buildingInternalName;

                // Usuń element kolejki z DOM
                queueItemElementToRemove.remove();

                // Po usunięciu elementu kolejki, zaktualizuj status budynku na liście i mapie
                if (buildingInternalName) {
                     // Usuń klasę building-upgrading z odpowiedniego placeholdera (jeśli była dodana w PHP/JS)
                     const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${buildingInternalName}']`);
                     if (buildingPlaceholder) {
                         buildingPlaceholder.classList.remove('building-upgrading');
                         // Zmiana grafiki na PNG odbywa się powyżej, w bloku else dla remainingTime <= 0
                     }
                     // Znajdź building-item i zaktualizuj jego status (np. usuń status 'w trakcie rozbudowy')
                     const buildingItem = document.querySelector(`.building-item[data-internal-name='${buildingInternalName}']`);
                      if (buildingItem) {
                           // Tutaj można dodać logikę aktualizacji wyświetlanego poziomu budynku
                           // i stanu przycisku rozbudowy na liście budynków.
                           // Ta część kodu poniżej jest już obecna i może wymagać dostosowania
                           // w zależności od tego, jak BuildingManager::processCompletedTasksForVillage
                           // wpływa na wyświetlane dane na liście.
                           const statusElement = buildingItem.querySelector('.upgrade-status');
                           if (statusElement && statusElement.textContent.includes('W trakcie rozbudowy')) {
                                // Idealnie, powinniśmy pobrać nowy poziom budynku po zakończeniu budowy
                                // Ale na razie możemy po prostu zaktualizować tekst statusu.
                                statusElement.textContent = `Rozbudowa do poziomu ${parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1}:`; // Może być niepoprawne jeśli VillageManager nie zaktualizował DOM
                           }
                           const timerElementInItem = buildingItem.querySelector('.upgrade-timer');
                           if (timerElementInItem) timerElementInItem.remove();

                            // Ponownie włącz przycisk rozbudowy
                            // Upewnij się, że selektor przycisku jest poprawny i dotyczy przycisku rozbudowy w building-item
                            const upgradeButton = buildingItem.querySelector('.upgrade-button'); // Zakładając, że przycisk rozbudowy ma klasę 'upgrade-button'
                            // Jeśli przycisk rozbudowy ma inną klasę lub strukturę, zaktualizuj selektor.
                            // W game.php przycisk rozbudowy ma klasę 'upgrade-building-button'
                            const upgradeButtonInItem = buildingItem.querySelector('.upgrade-building-button'); // Poprawiony selektor
                             if (upgradeButtonInItem) {
                                 upgradeButtonInItem.disabled = false;
                                 upgradeButtonInItem.classList.remove('btn-secondary');
                                 upgradeButtonInItem.classList.add('btn-primary');
                                 // Usuń powód niedostępności, jeśli istniał
                                 const reasonElement = buildingItem.querySelector('.upgrade-unavailable-reason'); // Zakładając taką klasę
                                 if(reasonElement) reasonElement.style.display = 'none';
                                 // Potencjalnie zaktualizuj tekst przycisku do nowego poziomu
                                 // updateBuildingItemLevelAndButtonText(buildingItem, data.new_level); // Wymaga funkcji i danych o nowym poziomie
                            }
                             // Potencjalnie zaktualizuj wyświetlany poziom na liście budynków
                             // Wymaga dodatkowego zapytania lub aktualizacji danych na stronie
                            // const levelElement = buildingItem.querySelector('.building-level'); // Zakładając taki element w building-item h3 lub span
                            // if (levelElement) {
                            //      // update level text
                            // }
                      }
                }
            }


            updateBuildingQueue(); // Odśwież kolejkę (może być pusta teraz)
            if (window.resourceUpdater) {
                 // Aktualizuj zasoby - VillageManager powinien to zrobić, ale wymuśmy odświeżenie
                 window.resourceUpdater.fetchUpdate();
             }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const popupOverlay = document.getElementById('popup-overlay');
    const buildingDetailsPopup = document.getElementById('building-action-popup');
    // Upewnij się, że element popupCloseBtn jest wyszukiwany po upewnieniu się, że buildingDetailsPopup istnieje
    const popupCloseBtn = buildingDetailsPopup ? buildingDetailsPopup.querySelector('.close-button') : null;

    // Elementy wewnątrz popupu (przydałoby się umieścić w osobnym obiekcie/klasie dla porządku)
    // Dodano sprawdzenia null przy pobieraniu elementów
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
     const buildingDetailsContent = document.getElementById('building-details-content'); // Dodany element do przełączania widoków w popupie


    let currentVillageId = window.currentVillageId; // Pobierz ID wioski z globalnej zmiennej

    // Funkcja do otwierania popupu z detalami budynku
    async function openBuildingDetailsPopup(villageId, internalName) {
        if (!villageId || !internalName) {
            console.error('Brak villageId lub internalName dla popupu budynku.');
            return;
        }

        // Pokaż loader i wyczyść poprzednią zawartość
        if (popupBuildingName) popupBuildingName.textContent = 'Ładowanie...';
        if (popupBuildingDescription) popupBuildingDescription.textContent = '';
        if (popupCurrentLevel) popupCurrentLevel.textContent = '';
        if (popupProductionInfo) popupProductionInfo.textContent = '';
        if (popupCapacityInfo) popupCapacityInfo.textContent = '';
        if (popupNextLevel) popupNextLevel.textContent = '';
        if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = ''; // Użyj innerHTML bo zawiera znaczniki
        if (popupUpgradeTime) popupUpgradeTime.textContent = '';
        if (popupRequirements) popupRequirements.innerHTML = ''; // Użyj innerHTML
        if (popupUpgradeReason) popupUpgradeReason.textContent = '';
        if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
        if (popupActionContent) {
            popupActionContent.innerHTML = '';
            popupActionContent.style.display = 'none'; // Hide action content initially
        }
        if (buildingDetailsContent) buildingDetailsContent.style.display = 'block'; // Show details content


        if (buildingDetailsPopup) {
             buildingDetailsPopup.classList.remove('main-building-popup'); // Resetuj klasę dla ratusza
             buildingDetailsPopup.style.display = 'block';
        }
        if (popupOverlay) popupOverlay.style.display = 'block';

        try {
            const response = await fetch(`/ajax/buildings/get_building_details.php?village_id=${villageId}&building_internal_name=${internalName}`);
            const data = await response.json();

            if (data.error) {
                console.error('Błąd pobierania detali budynku:', data.error);
                if (window.toastManager) window.toastManager.showToast(data.error, 'error');
                closeBuildingDetailsPopup();
                return;
            }

            // Wypełnij popup danymi (dodano sprawdzenia null dla elementów)
            if (popupBuildingName) popupBuildingName.textContent = `${data.name_pl} (Poziom ${data.level})`;
            if (popupBuildingDescription) popupBuildingDescription.textContent = data.description_pl;
            if (popupCurrentLevel) popupCurrentLevel.textContent = data.level;

            // Informacje o produkcji/pojemności
            if (data.production_info) {
                if (data.production_info.type === 'production') {
                    if (popupProductionInfo) {
                        popupProductionInfo.textContent = `Produkcja: ${formatNumber(data.production_info.amount_per_hour)}/godz. ${data.production_info.resource_type}`;
                        if (data.production_info.amount_per_hour_next_level) {
                            popupProductionInfo.textContent += ` (Nast. poz.: +${formatNumber(data.production_info.amount_per_hour_next_level)})`;
                        }
                        popupProductionInfo.style.display = 'block';
                    }
                    if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
                } else if (data.production_info.type === 'capacity') {
                     if (popupCapacityInfo) {
                        popupCapacityInfo.textContent = `Pojemność: ${formatNumber(data.production_info.amount)}`;
                        if (data.production_info.amount_next_level) {
                            popupCapacityInfo.textContent += ` (Nast. poz.: ${formatNumber(data.production_info.amount_next_level)})`;
                        }
                        popupCapacityInfo.style.display = 'block';
                     }
                    if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                } else {
                    if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                    if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
                }
            } else { // No production_info type
                if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
            }

            // Informacje o rozbudowie
             // Only show upgrade section if the building can be upgraded
             // Zakładamy istnienie elementu building-upgrade-section w HTML popupu
             const buildingUpgradeSection = document.getElementById('building-upgrade-section');
             if (buildingUpgradeSection) {
                 if (data.level < data.max_level) {
                     buildingUpgradeSection.style.display = 'block';
                      if (data.is_upgrading) {
                          if (popupNextLevel) popupNextLevel.textContent = data.queue_level_after;
                          if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = `<p class="upgrade-status">W trakcie rozbudowy do poziomu ${data.queue_level_after}.</p>`;
                           // Sprawdzamy istnienie elementu timera przed ustawieniem innerHTML
                          if (popupUpgradeTime) popupUpgradeTime.innerHTML = `<p class="upgrade-timer" data-ends-at="${data.queue_finish_time}">${getRemainingTimeText(data.queue_finish_time)}</p>`;
                          if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                          if (popupUpgradeReason) {
                            popupUpgradeReason.textContent = data.upgrade_not_available_reason;
                            popupUpgradeReason.style.display = 'block';
                          }
                      } else { // Not upgrading
                          if (popupNextLevel) popupNextLevel.textContent = data.level + 1;
                          if (data.upgrade_costs) {
                              if (popupUpgradeCosts) {
                                // Używamy względnych ścieżek do grafik zasobów w popupie
                                popupUpgradeCosts.innerHTML = `Koszt:
                                    <span class="resource-cost wood"><img src="/img/ds_graphic/wood.png" alt="Drewno"> ${formatNumber(data.upgrade_costs.wood)}</span>
                                    <span class="resource-cost clay"><img src="/img/ds_graphic/stone.png" alt="Glina"> ${formatNumber(data.upgrade_costs.clay)}</span>
                                    <span class="resource-cost iron"><img src="/img/ds_graphic/iron.png" alt="Żelazo"> ${formatNumber(data.upgrade_costs.iron)}</span>`;
                              }
                              if (popupUpgradeTime) popupUpgradeTime.textContent = `Czas budowy: ${data.upgrade_time_formatted}`;

                              // Wymagania
                              if (data.requirements && data.requirements.length > 0) {
                                  if (popupRequirements) {
                                      let reqHtml = '<div class="building-requirements"><p>Wymagania:</p><ul>';
                                      data.requirements.forEach(req => {
                                          const isMet = req.met;
                                          const requirementClass = isMet ? 'requirement-met' : 'requirement-not-met';
                                          const statusText = isMet ? '(Spełnione)' : ' (Wymagany)';
                                          reqHtml += `<li class="${requirementClass}">${req.name_pl} (Poziom ${req.required_level}, Twój poziom: ${req.current_level}) ${statusText}</li>`;
                                      });
                                      reqHtml += '</ul></div>';
                                      popupRequirements.innerHTML = reqHtml;
                                      popupRequirements.style.display = 'block';
                                  }
                              } else {
                                  if (popupRequirements) popupRequirements.style.display = 'none';
                              }

                              if (data.can_upgrade) {
                                  if (popupUpgradeButton) {
                                      popupUpgradeButton.style.display = 'block';
                                      popupUpgradeButton.textContent = `Rozbuduj do poziomu ${data.level + 1}`;
                                      popupUpgradeButton.dataset.villageId = villageId;
                                      popupUpgradeButton.dataset.buildingInternalName = internalName;
                                      popupUpgradeButton.dataset.currentLevel = data.level;
                                  }
                                  if (popupUpgradeReason) popupUpgradeReason.style.display = 'none';
                              } else { // Cannot upgrade (e.g., insufficient resources, missing requirements)
                                  if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                                  if (popupUpgradeReason) {
                                    popupUpgradeReason.textContent = data.upgrade_not_available_reason || 'Brak danych do rozbudowy.';
                                    popupUpgradeReason.style.display = 'block';
                                  }
                              }
                          } else { // No upgrade costs data
                              if (popupUpgradeCosts) popupUpgradeCosts.textContent = 'Nie można obliczyć kosztów rozbudowy.';
                              if (popupUpgradeTime) popupUpgradeTime.textContent = '';
                              if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                              if (popupUpgradeReason) {
                                popupUpgradeReason.textContent = data.upgrade_not_available_reason || 'Brak danych do rozbudowy.';
                                popupUpgradeReason.style.display = 'block';
                              }
                          }
                      }
                 } else { // Max level reached
                     if (buildingUpgradeSection) buildingUpgradeSection.style.display = 'none'; // Hide if max level
                     // Still show max level info in the main details area
                     if (popupNextLevel) popupNextLevel.textContent = data.max_level;
                     if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = ''; // Clear upgrade costs section
                     if (popupUpgradeTime) popupUpgradeTime.textContent = '';
                     if (popupRequirements) popupRequirements.innerHTML = '';
                     if (popupUpgradeReason) {
                        popupUpgradeReason.textContent = 'Osiągnięto maksymalny poziom.';
                        popupUpgradeReason.style.display = 'block';
                     }
                 }
             }

            // Specjalna obsługa dla Ratusza (Main Building)
            if (buildingDetailsPopup && internalName === 'main_building') {
                buildingDetailsPopup.classList.add('main-building-popup');
                // Tutaj można załadować listę wszystkich budynków do rozbudowy
                // fetchAndRenderAllBuildingsForMainBuilding(villageId); // Będzie wywołane przez building-action-button
            } else {
                if (buildingDetailsPopup) buildingDetailsPopup.classList.remove('main-building-popup');
            }

            // Uruchom timery w popupie (dla kolejki budowy, jeśli jest) - nie jest potrzebne, globalny interwał już działa na wszystkich timerach
            // updateTimers();
            // The interval is already running globally

         } catch (error) {
            console.error('Błąd AJAX pobierania detali budynku:', error);
            if (window.toastManager) window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania detali.', 'error');
            closeBuildingDetailsPopup();
        }
    }

    // Funkcja do zamykania popupu
    function closeBuildingDetailsPopup() {
        if (buildingDetailsPopup) buildingDetailsPopup.style.display = 'none';
        if (popupOverlay) popupOverlay.style.display = 'none';
        if (popupActionContent) {
            popupActionContent.innerHTML = ''; // Wyczyść zawartość akcji
            popupActionContent.style.display = 'none'; // Hide action content
        }
        if (buildingDetailsContent) buildingDetailsContent.style.display = 'block'; // Show details content
         // Clear timer interval if it's only for popup - NIE jest potrzebne, timery są globalne
         // clearInterval(popupTimerInterval);
    }

    // Obsługa kliknięć na placeholdery budynków - Otwiera popup z detalami
    // Używamy delegacji zdarzeń na kontenerze nadrzędnym, bo placeholdery mogą być dynamicznie ładowane/aktualizowane
    const villageViewGraphic = document.getElementById('village-view-graphic');
    if (villageViewGraphic) {
        villageViewGraphic.addEventListener('click', function(event) {
            const placeholder = event.target.closest('.building-placeholder');
            if (placeholder) {
                const internalName = placeholder.dataset.buildingInternalName;
                 if (window.currentVillageId) { // Sprawdź, czy villageId jest dostępne
                      openBuildingDetailsPopup(window.currentVillageId, internalName);
                 } else {
                      console.error('Village ID not available to open building details.');
                 }
            }
        });
    }


    // Handle building action button clicks (using event delegation) - buttons might be inside the popup
    document.addEventListener('click', async function(event) {
        const button = event.target.closest('.building-action-button');

        if (button) {
            event.preventDefault();
             // Prefer village_id from global variable if available, otherwise from button data
            const villageId = window.currentVillageId || button.dataset.villageId;
            const buildingInternalName = button.dataset.buildingInternalName;
             const actionContent = document.getElementById('popup-action-content'); // Upewnij się, że to jest ID kontenera w popupie
             const detailsContent = document.getElementById('building-details-content'); // Upewnij się, że to jest ID kontenera w popupie


            if (!actionContent || !detailsContent || !villageId || !buildingInternalName) {
                 console.error('Missing elements or data for building action.');
                 if (window.toastManager) window.toastManager.showToast('Błąd: brak danych do wykonania akcji.', 'error');
                 return;
             }

             // Show loading state and disable button
             button.disabled = true;
             button.textContent = 'Ładowanie...'; // Or add a spinner
             actionContent.innerHTML = '<p>Ładowanie zawartości akcji...</p>';
             actionContent.style.display = 'block';
             detailsContent.style.display = 'none'; // Hide details when showing action content

            try {
                 // Fetch and render content based on building internal name
                 // This uses the get_building_action.php endpoint
                 // Pass village_id and internal_name
                 const response = await fetch(`/ajax/buildings/get_building_action.php?village_id=${villageId}&building_internal_name=${buildingInternalName}`); // Zmieniono parametr building_type na building_internal_name
                 const data = await response.json();

                 if (data.status === 'success' && actionContent) {
                     // Wstaw zawartość akcji do kontenera
                     actionContent.innerHTML = data.html; // Zakładamy, że odpowiedź zawiera HTML w polu 'html'
                     actionContent.style.display = 'block';
                     detailsContent.style.display = 'none'; // Upewnij się, że szczegóły są ukryte

                     // Po załadowaniu treści akcji, uruchom timery wewnątrz tej treści, jeśli istnieją
                     updateTimers(); // Uruchom ponownie timery dla nowej zawartości

                     // TODO: Tutaj można dodać specyficzną inicjalizację JS dla paneli (np. panel rekrutacji)
                     // W zależności od data.action_type można wywołać odpowiednie funkcje inicjalizacyjne
                     switch(data.action_type) {
                          case 'recruit_barracks':
                          case 'recruit_stable':
                          case 'recruit_workshop': // Poprawiono z siege na workshop zgodnie z nazwami
                              // Inicjalizacja panelu rekrutacji, jeśli potrzebne (np. dodanie event listenerów do przycisków rekrutacji)
                               // Zakładamy, że renderowanie panelu i dodawanie event listenerów dzieje się w fetchAndRenderRecruitmentPanel lub podobnej funkcji wywołanej wcześniej.
                               // Jeśli panel rekrutacji ma własne timery, updateTimers() je znajdzie.
                              break;
                          case 'research':
                              // Inicjalizacja panelu badań
                              break;
                           case 'trade':
                              // Inicjalizacja panelu handlu
                              break;
                           case 'main_building':
                              // Inicjalizacja panelu ratusza
                              break;
                            case 'noble':
                              // Inicjalizacja panelu szlachcica
                              break;
                            case 'mint':
                              // Inicjalizacja panelu mennicy
                              break;
                           // Add cases for other building panels as they are implemented
                     }


                 } else if (actionContent) {
                     // Handle server-side errors
                     actionContent.innerHTML = '<p>Błąd ładowania akcji: ' + (data.message || data.error || 'Nieznany błąd') + '</p>';
                     if (window.toastManager) window.toastManager.showToast(data.message || data.error || 'Błąd serwera.', 'error');
                     actionContent.style.display = 'block'; // Pokaż komunikat o błędzie w sekcji akcji
                     detailsContent.style.display = 'none';
                 }

            } catch (error) {
                console.error('Błąd AJAX pobierania akcji budynku:', error);
                 if (actionContent) {
                    actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
                    actionContent.style.display = 'block';
                 }
                if (window.toastManager) window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania akcji.', 'error');
                 if (detailsContent) detailsContent.style.display = 'none';
            }
        } else if (event.target.classList.contains('upgrade-building-button')) { // Obsługa kliknięcia na przycisk "Rozbuduj" na liście budynków
             // Ta logika jest już zaimplementowana w bloku event listenera dla popupUpgradeButton click
             // Jeśli chcesz, aby przyciski na liście też działały (obecnie nie mają addEventListener),
             // można przenieść logikę z popupUpgradeButton.addEventListener tutaj i dostosować pobieranie danych.
             // Na razie pozostawiamy obsługę rozbudowy tylko przez przycisk w popupie, co jest standardowe.
        }
    });

     // Pomocnicza funkcja do pobierania tekstu akcji budynku (dopasuj do PHP)
     // Ta funkcja powinna być zsynchronizowana z getBuildingActionText w PHP
     function getBuildingActionText(internalName) {
          switch(internalName) {
               case 'main_building': return 'Zarządzaj wioską';
               case 'barracks': return 'Rekrutuj jednostki';
               case 'stable': return 'Rekrutuj jednostki';
               case 'workshop': return 'Rekrutuj jednostki';
               case 'academy': return 'Badaj technologie';
               case 'market': return 'Handluj surowcami';
               case 'statue': return 'Widok szlachcica';
               case 'church': return 'Kościół';
               case 'first_church': return 'Pierwszy Kościół';
               case 'mint': return 'Mennica';
               // For production buildings (wood_production, clay_pit, iron_mine, farm) and others (warehouse, wall, watchtower)
               // The action might just be "Szczegóły" or similar, handled by the details popup itself.
               default: return 'Akcja'; // Domyślny tekst, jeśli brak specyficznej akcji
          }
     }


    // Obsługa kliknięcia na przycisk zamknięcia popupu
    if (popupCloseBtn) {
        popupCloseBtn.addEventListener('click', closeBuildingDetailsPopup);
    }
    if (popupOverlay) {
        popupOverlay.addEventListener('click', closeBuildingDetailsPopup); // Zamknij po kliknięciu na overlay
    }

    // Obsługa kliknięcia na przycisk "Rozbuduj" w popupie
    if (popupUpgradeButton) {
        popupUpgradeButton.addEventListener('click', async function() {
            const button = this;
            const villageId = button.dataset.villageId;
            const buildingInternalName = button.dataset.buildingInternalName;
            const currentLevel = button.dataset.currentLevel;

            // Disable button and show loading
             button.disabled = true;
             button.textContent = 'Rozbudowa...';

            // Wyślij żądanie AJAX do upgrade_building.php
            try {
                const response = await fetch('/ajax/buildings/upgrade_building.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `village_id=${villageId}&building_type_internal_name=${buildingInternalName}&current_level=${currentLevel}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
                });
                const data = await response.json();

                if (data.status === 'success') {
                    if (window.toastManager) window.toastManager.showToast(data.message, 'success');
                    // Close popup after successful upgrade initiation (optional, but common)
                    closeBuildingDetailsPopup(); // Close popup
                    // Zaktualizuj zasoby
                    if (window.resourceUpdater) {
                        window.resourceUpdater.fetchUpdate();
                    }
                    // Zaktualizuj kolejkę budowy (should happen automatically or by polling)
                    updateBuildingQueue(); // Wymuś odświeżenie kolejki

                } else {
                     if (window.toastManager) window.toastManager.showToast(data.message || 'Błąd rozbudowy.', 'error');
                }
            } catch (error) {
                console.error('Błąd AJAX rozbudowy:', error);
                if (window.toastManager) window.toastManager.showToast('Błąd komunikacji z serwera podczas rozbudowy.', 'error');
            } finally {
             // Re-enable button regardless of success or failure
             button.disabled = false;
             button.textContent = `Rozbuduj do poziomu ${parseInt(currentLevel, 10) + 1}`
             button.textContent = `Rozbuduj do poziomu ${parseInt(currentLevel, 10) + 1}`; // Aktualizuj tekst przycisku
            }
        });
    }


    // Funkcja do aktualizacji kolejki budowy
    async function updateBuildingQueue() {
        const buildingQueueList = document.getElementById('building-queue-list'); // Upewnij się, że taki element istnieje w game.php
        if (!buildingQueueList || !window.currentVillageId) return; // Użyj globalnej zmiennej


        // Optional: Show a loading indicator for the queue itself
        buildingQueueList.innerHTML = '<p class="queue-empty">Ładowanie kolejki budowy...</p>'; // Dodano komunikat ładowania


        try {
            // Użyj globalnej zmiennej villageId
            const response = await fetch(`/ajax/buildings/get_queue.php?village_id=${window.currentVillageId}`);
            const data = await response.json();

            if (data.status === 'success') {
                const queueItem = data.data.queue_item;
                buildingQueueList.innerHTML = ''; // Wyczyść obecną kolejkę

                if (queueItem) {
                    const queueHtml = `
                        <div class="queue-item current" data-building-internal-name="${queueItem.building_internal_name}"> <!-- Dodano atrybut building-internal-name -->
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
                    // updateTimers(); // Globalny interwał to zrobi
                } else {
                    buildingQueueList.innerHTML = '<p class="queue-empty">Brak zadań w kolejce budowy.</p>';
                }
            } else {
                console.error('Błąd pobierania kolejki budowy:', data.message);
                if (window.toastManager) window.toastManager.showToast('Błąd pobierania kolejki budowy.', 'error');
                 if (buildingQueueList) buildingQueueList.innerHTML = '<p class="queue-empty error">Błąd ładowania kolejki.</p>'; // Show error state
            }
        } catch (error) {
            console.error('Błąd AJAX kolejki budowy:', error);
             if (window.toastManager) window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania kolejki budowy.', 'error');
             if (buildingQueueList) buildingQueueList.innerHTML = '<p class="queue-empty error">Błąd komunikacji z serwera.</p>'; // Show error state
        }
    }

    // === Obsługa anulowania zadania budowy ===
    // Dodaj event listener do przycisku anulowania w kolejce budowy (używamy delegacji zdarzeń)
    document.addEventListener('click', async function(event) {
        const cancelButton = event.target.closest('.cancel-button');
        // Ensure it's a building cancel button, not recruitment (if they use the same class)
         if (!cancelButton || cancelButton.classList.contains('recruitment-cancel-button')) return; // Upewnij się, że to przycisk anulowania budowy

        const queueItemId = cancelButton.dataset.queueId;
        if (!queueItemId) {
            console.error('Brak ID zadania budowy do anulowania.');
            return;
        }

        // Potwierdzenie anulowania przez użytkownika
        if (!confirm('Czy na pewno chcesz anulować tę budowę? Odzyskasz 90% surowców.')) {
            return;
        }

        // Disable button and show loading state
         cancelButton.disabled = true;
         cancelButton.textContent = '...'; // Or a spinner

        try {
            // Wyślij żądanie AJAX do cancel_upgrade.php
            const response = await fetch('/ajax/buildings/cancel_upgrade.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `queue_item_id=${queueItemId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}&ajax=1` // Dodaj flagę ajax
            });
            const data = await response.json();

            if (data.success) {
                if (window.toastManager) window.toastManager.showToast(data.message, 'success');
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
                const buildingInternalNameAfterCancel = data.building_internal_name; // Pobierz internal_name z odpowiedzi serwera
                if (buildingInternalNameAfterCancel) {
                    const buildingItem = document.querySelector(`.building-item[data-internal-name='${buildingInternalNameAfterCancel}']`);
                    const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${buildingInternalNameAfterCancel}']`);

                    if (buildingItem) {
                         // Znajdź przycisk rozbudowy w tym building-item i włącz go
                        const upgradeButton = buildingItem.querySelector('.upgrade-button'); // Upewnij się, że selektor jest poprawny
                        if (upgradeButton) {
                            upgradeButton.disabled = false;
                            upgradeButton.classList.remove('btn-secondary');
                            upgradeButton.classList.add('btn-primary');
                            // Usuń powód niedostępności, jeśli istniał
                            const reasonElement = buildingItem.querySelector('.upgrade-unavailable-reason'); // Zakładając taką klasę
                            if(reasonElement) reasonElement.style.display = 'none';
                        }
                         // Usuń status 'w trakcie rozbudowy' i timer
                         const statusElement = buildingItem.querySelector('.upgrade-status');
                         if (statusElement && statusElement.textContent.includes('W trakcie rozbudowy')) {
                              // Aktualizujemy tekst statusu - możemy próbować odgadnąć nowy poziom lub po prostu usunąć status budowy
                               // Idealnie, odpowiedź serwera powinna zawierać nowy poziom budynku
                               // Jeśli data.new_level jest dostępne: statusElement.textContent = `Poziom ${data.new_level}:`;
                               // W przeciwnym razie, po prostu resetujemy do ogólnego statusu
                               statusElement.textContent = `Rozbudowa do poziomu ${parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1}:`; // Może być niepoprawne, jeśli anulowano ostatni poziom
                         }
                         const timerElement = buildingItem.querySelector('.upgrade-timer'); // Upewnij się, że selektor jest poprawny
                         if (timerElement) timerElement.remove();
                    }

                     if (buildingPlaceholder) {
                        buildingPlaceholder.classList.remove('building-upgrading');
                         // Po anulowaniu, zmień grafikę z powrotem na PNG, jeśli była GIFem
                         const buildingImage = buildingPlaceholder.querySelector('.building-graphic');
                          if (buildingImage) {
                              const currentSrc = buildingImage.src;
                              if (currentSrc.endsWith('.gif')) {
                                  const pngSrc = currentSrc.replace('.gif', '.png');
                                  buildingImage.src = pngSrc;
                              }
                          }
                     }
                }


            } else {
                 if (window.toastManager) window.toastManager.showToast(data.error || data.message || 'Błąd anulowania budowy.', 'error');
            }
        } catch (error) {
            console.error('Błąd AJAX anulowania budowy:', error);
            if (window.toastManager) window.toastManager.showToast('Błąd komunikacji z serwera podczas anulowania.', 'error');
        } finally {
             // Re-enable button regardless of success or failure (if it still exists in DOM)
             if (cancelButton && cancelButton.parentNode) {
                  cancelButton.disabled = false;
                  cancelButton.textContent = '✖'; // Restore original text
             }
        }
    });
    // =========================================

    // Uruchom timery po załadowaniu DOM i odświeżaj co sekundę
    updateTimers(); // Początkowe wywołanie
    setInterval(updateTimers, 1000); // Odświeżaj co sekundę

     // Initial load of the building queue when the page loads
     // Assuming currentVillageId is set globally in game.php
     const villageId = window.currentVillageId || null;
     if (villageId) {
         updateBuildingQueue(villageId);
     } else {
         console.warn('Village ID not available. Cannot initialize building queue.');
     }
});

// Funkcja do formatowania liczb z separatorami
function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}