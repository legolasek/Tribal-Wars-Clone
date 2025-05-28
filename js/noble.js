/**
 * Frontend logic for the Statue (Noble) panel
 */

// Assume formatDuration and formatNumber are available globally

// Function to fetch and render the Noble panel
async function fetchAndRenderNoblePanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'statue') {
        console.error('Missing elements or parameters for Noble panel or wrong building type.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Ładowanie panelu Statuy...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && data.action_type === 'noble') {
            // Assuming backend provides necessary data for the noble system
            const buildingName = data.data.building_name_pl;
            const buildingLevel = data.data.building_level;
            // TODO: Extract noble-specific data from data.data

            // Render the Noble panel HTML
            let html = `
                <h3>${buildingName} (Poziom ${buildingLevel}) - Szlachcic</h3>
                <p>Tutaj możesz rekrutować/zarządzać szlachcicami.</p>

                <h4>Status szlachcica:</h4>
                <div class="noble-status">
                    <p>TODO: Wyświetl status obecnego szlachcica (jeśli istnieje).</p>
                </div>

                <h4>Rekrutacja szlachcica:</h4>
                <div class="noble-recruitment">
                    <p>TODO: Formularz rekrutacji szlachcica (koszt, czas, wymagania).</p>
                     <button class="btn-primary" disabled>Rekrutuj Szlachcica (TODO)</button>
                </div>

                <h4>Wybijanie monet:</h4>
                <div class="coin-minting">
                    <p>TODO: Interfejs wybijania monet (jeśli to w Statui, w Dzikich Plemionach było w Pałacu/Rezydencji).</p>
                     <button class="btn-primary" disabled>Wybij Monety (TODO)</button>
                </div>

                <!-- Add other noble-related options -->

            `;

            actionContent.innerHTML = html;

            // Setup event listeners for any buttons/forms within the panel
            setupNobleListeners(villageId, buildingInternalName);

        } else if (data.error) {
            actionContent.innerHTML = '<p>Błąd ładowania panelu Statuy: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy Statuy.</p>';
         }

    } catch (error) {
        console.error('Błąd AJAX pobierania panelu Statuy:', error);
        actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
        window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania panelu Statuy.', 'error');
    }
}

// Function to setup event listeners for the Noble panel
function setupNobleListeners(villageId, buildingInternalName) {
    // TODO: Add event listeners for noble recruitment form, coin minting form, etc.
}

// Add the function to the global scope or make it accessible
// window.fetchAndRenderNoblePanel = fetchAndRenderNoblePanel; 