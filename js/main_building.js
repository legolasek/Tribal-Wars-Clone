/**
 * Frontend logic for the Main Building panel
 */

// Assume formatDuration and formatNumber are available globally
// from other scripts like buildings.js or units.js
// function formatDuration(seconds) { ... }
// function formatNumber(number) { ... }

// Function to fetch and render the Main Building panel
async function fetchAndRenderMainBuildingPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'main_building') {
        console.error('Missing elements or parameters for Main Building panel or wrong building type.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Ładowanie panelu Ratusza...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && data.action_type === 'manage_village') {
            const villageInfo = data.data;
            const villageName = villageInfo.village_name;
            const mainBuildingLevel = villageInfo.main_building_level;
            const population = villageInfo.population;
            const villagesCount = villageInfo.villages_count;
            const buildingsList = villageInfo.buildings_list;
            const resourcesCapacity = villageInfo.resources_capacity;

            // Render the Main Building panel HTML
            let html = `
                <h3>${villageName} (Ratusz Poziom ${mainBuildingLevel})</h3>
                <p>Zarządzaj swoją wioską z poziomu Ratusza.</p>

                <div class="village-overview">
                    <h4>Przegląd wioski:</h4>
                    <p>Populacja: <strong class="village-population">${formatNumber(population)}</strong></p>
                    <p>Liczba wiosek: <strong>${villagesCount}</strong></p>
                     <div class="resource-capacity-overview">
                          <p>Pojemność magazynu: <strong class="warehouse-capacity">${formatNumber(resourcesCapacity.warehouse_capacity)}</strong></p>
                          <p>Pojemność zagrody: <strong class="farm-capacity">${formatNumber(resourcesCapacity.farm_capacity)}</strong></p>
                     </div>
                </div>

                <div class="building-upgrade-list">
                    <h4>Rozbudowa budynków:</h4>
            `;

            if (buildingsList && buildingsList.length > 0) {
                html += '<table class="main-building-upgrade-table upgrade-buildings-table">';
                html += '<thead><tr><th>Budynek</th><th>Poziom</th><th>Akcja</th></tr></thead>';
                html += '<tbody>';

                // TODO: Fetch actual upgrade costs and times dynamically here or in a separate endpoint
                // For now, this table will just list buildings and their current levels
                // The upgrade logic is handled via clicking the building placeholder on the map/village view
                // This section primarily serves as a quick overview/navigation.
                // A proper upgrade UI in the Main Building requires fetching costs/times for EACH building type here.

                buildingsList.forEach(building => {
                     // Check if building can be upgraded (not max level)
                     const canUpgrade = building.level < building.max_level;
                     const upgradeButtonHtml = canUpgrade 
                         ? `<button class="btn-secondary view-upgrade-details" data-village-building-id="${building.id}" data-building-internal-name="${building.internal_name}">Szczegóły rozbudowy</button>`
                         : `<button disabled class="btn-secondary">Max poziom</button>`;

                    html += `
                        <tr>
                            <td>${building.name_pl}</td>
                            <td>${building.level}</td>
                            <td>${upgradeButtonHtml}</td>
                        </tr>
                    `;
                });

                html += '</tbody>';
                html += '</table>';
                 html += '<p style="font-size:0.9em; color:#777; margin-top: 10px;">Kliknij budynek na widoku wioski, aby rozbudować.</p>'; // Hint for the user

            } else {
                html += '<p>Brak danych o budynkach.</p>';
            }

            html += '</div>'; // building-upgrade-list
            html += '<div class="village-management-options" style="margin-top: 20px;"><h4>Inne opcje zarządzania:</h4><ul><li><button class="btn-secondary" id="rename-village-button">Zmień nazwę wioski</button></li></ul></div>'; // Add rename option

            actionContent.innerHTML = html;

            // Setup event listeners for any buttons/forms within the panel
            setupMainBuildingListeners(villageId, buildingInternalName);

        } else if (data.error) {
            actionContent.innerHTML = '<p>Błąd ładowania panelu Ratusza: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy Ratusza.</p>';
         }

    } catch (error) {
        console.error('Błąd AJAX pobierania panelu Ratusza:', error);
        actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
        window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania panelu Ratusza.', 'error');
    }
}

// Function to setup event listeners for the Main Building panel
function setupMainBuildingListeners(villageId, buildingInternalName) {
    // Listener for "Szczegóły rozbudowy" buttons (if implemented in the future to open details)
    // For now, they just hint to click the building on the map.

     // Listener for Rename Village Button
    const renameButton = document.getElementById('rename-village-button');
    if (renameButton) {
        renameButton.addEventListener('click', function() {
            const newName = prompt('Podaj nową nazwę dla wioski:');
            if (newName !== null && newName.trim() !== '') {
                renameVillage(villageId, newName.trim());
            }
        });
    }

    // Add other listeners for future functionalities (e.g., manage units, manage research overview, etc.)
}

// Function to handle renaming village via AJAX
async function renameVillage(villageId, newName) {
     if (!villageId || !newName) return;

     // Optional: Show a loading indicator or disable button

     try {
         const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
         const response = await fetch('rename_village.php', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/x-www-form-urlencoded',
                 'X-Requested-With': 'XMLHttpRequest'
             },
             body: `village_id=${villageId}&new_name=${encodeURIComponent(newName)}&csrf_token=${csrfToken}&ajax=1`
         });
         const data = await response.json();

         if (data.status === 'success') {
             window.toastManager.showToast(data.message, 'success');
             // Update village name displayed in the header/sidebar
             const villageNameElements = document.querySelectorAll('.village-name-display'); // Need a class for village name elements
              villageNameElements.forEach(element => element.textContent = newName);
             
              // Update the name in the popup itself if it's open
              const popupBuildingNameElement = document.getElementById('popup-building-name');
              if(popupBuildingNameElement) {
                   // Assuming popupBuildingName includes the level like "Ratusz (Poziom X)"
                   // Need to find a more robust way to update just the name part
                   // For now, a simple replace might work if the format is consistent
                   popupBuildingNameElement.textContent = popupBuildingNameElement.textContent.replace(/^.* \(Ratusz/, `${newName} (Ratusz`);
              }

         } else {
             window.toastManager.showToast(data.message || 'Błąd zmiany nazwy wioski.', 'error');
         }

     } catch (error) {
         console.error('Błąd AJAX zmiany nazwy wioski:', error);
         window.toastManager.showToast('Błąd komunikacji z serwera podczas zmiany nazwy wioski.', 'error');
     } finally {
         // Optional: Re-enable button or hide loading indicator
     }
}

// Add the function to the global scope or make it accessible
// window.fetchAndRenderMainBuildingPanel = fetchAndRenderMainBuildingPanel; 