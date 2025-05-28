/**
 * Frontend logic for the Mint (Odlewnia Monety) panel
 */

// Assume formatDuration and formatNumber are available globally

// Function to fetch and render the Mint panel
async function fetchAndRenderMintPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'mint') {
        console.error('Missing elements or parameters for Mint panel or wrong building type.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Ładowanie panelu Odlewni Monety...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && data.action_type === 'mint') {
            // Assuming backend provides necessary data for coin minting
            const buildingName = data.data.building_name_pl;
            const buildingLevel = data.data.building_level;
            // TODO: Extract minting-specific data from data.data

            // Render the Mint panel HTML
            let html = `
                <h3>${buildingName} (Poziom ${buildingLevel}) - Odlewnia Monety</h3>
                <p>Tutaj możesz wybijać monety potrzebne do przejmowania wiosek (wymaga Pałacu).</p>

                <h4>Wybijanie monet:</h4>
                <div class="coin-minting-form">
                    <p>TODO: Formularz wybijania monet (koszt, czas).</p>
                    <button class="btn-primary" disabled>Wybij Monety (TODO)</button>
                </div>

                <h4>Status wybijania:</h4>
                 <div class="coin-minting-queue">
                     <p>TODO: Wyświetl kolejkę wybijania monet.</p>
                 </div>

                <!-- Add other minting-related options -->

            `;

            actionContent.innerHTML = html;

            // Setup event listeners for any buttons/forms within the panel
            setupMintListeners(villageId, buildingInternalName);

        } else if (data.error) {
            actionContent.innerHTML = '<p>Błąd ładowania panelu Odlewni Monety: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Nieprawidłowa odpowiedź serwera lub akcja nie dotyczy Odlewni Monety.</p>';
         }

    } catch (error) {
        console.error('Błąd AJAX pobierania panelu Odlewni Monety:', error);
        actionContent.innerHTML = '<p>Błąd komunikacji z serwera.</p>';
        window.toastManager.showToast('Błąd komunikacji z serwera podczas pobierania panelu Odlewni Monety.', 'error');
    }
}

// Function to setup event listeners for the Mint panel
function setupMintListeners(villageId, buildingInternalName) {
    // TODO: Add event listeners for coin minting form, etc.
}

// Add the function to the global scope or make it accessible
// window.fetchAndRenderMintPanel = fetchAndRenderMintPanel; 