document.addEventListener('DOMContentLoaded', function() {
    const marketPanel = document.getElementById('market-panel');

    // This script should only run when the market panel is loaded into the DOM.
    // We use a MutationObserver to detect when the panel is added.
    const observer = new MutationObserver((mutationsList, observer) => {
        for(const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                const marketPanelNode = document.getElementById('market-panel');
                if (marketPanelNode) {
                    initializeMarketPanel(marketPanelNode);
                    // Once initialized, we can disconnect the observer if the panel is not expected to be removed and re-added frequently.
                    // observer.disconnect();
                }
            }
        }
    });

    // Start observing the body for child list changes.
    observer.observe(document.body, { childList: true, subtree: true });

    function initializeMarketPanel(panel) {
        const tabs = panel.querySelectorAll('.tab-button');
        const contents = panel.querySelectorAll('.market-tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Deactivate all tabs and content
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));

                // Activate the clicked tab and its content
                tab.classList.add('active');
                const tabId = tab.dataset.tab;
                document.getElementById(tabId).classList.add('active');

                // Load content for the activated tab
                if (tabId === 'trade') {
                    loadAllOffers();
                } else if (tabId === 'my-offers') {
                    loadMyOffers();
                }
            });
        });

        // Initial load for the default tab
        loadAllOffers();
        setupCreateOfferForm();
    }

    async function loadAllOffers() {
        const offersList = document.getElementById('market-offers-list');
        if (!offersList) return;
        offersList.innerHTML = '<p>Ładowanie ofert...</p>';

        try {
            const response = await fetch('/ajax/market/get_offers.php');
            const data = await response.json();

            if (data.success) {
                renderOffers(offersList, data.offers, false);
            } else {
                offersList.innerHTML = `<p class="error-message">${data.message}</p>`;
            }
        } catch (error) {
            offersList.innerHTML = `<p class="error-message">Błąd ładowania ofert.</p>`;
        }
    }

    async function loadMyOffers() {
        const myOffersList = document.getElementById('my-offers-list');
        if (!myOffersList) return;
        myOffersList.innerHTML = '<p>Ładowanie Twoich ofert...</p>';

        try {
            // We reuse get_offers and filter client-side for simplicity.
            // In a larger application, a dedicated endpoint would be better.
            const response = await fetch('/ajax/market/get_offers.php');
            const data = await response.json();

            if (data.success) {
                const myVillageId = parseInt(document.querySelector('.village-name-display').dataset.villageId, 10);
                const myOffers = data.offers.filter(offer => offer.village_id == myVillageId);
                renderOffers(myOffersList, myOffers, true);
            } else {
                myOffersList.innerHTML = `<p class="error-message">${data.message}</p>`;
            }
        } catch (error) {
            myOffersList.innerHTML = `<p class="error-message">Błąd ładowania Twoich ofert.</p>`;
        }
    }

    function renderOffers(container, offers, isMyOffers) {
        if (offers.length === 0) {
            container.innerHTML = '<p>Brak ofert.</p>';
            return;
        }

        let html = '<table><thead><tr><th>Oferuje</th><th>W zamian za</th><th>Gracz</th><th>Akcja</th></tr></thead><tbody>';
        const myVillageId = parseInt(document.querySelector('.village-name-display').dataset.villageId, 10);

        offers.forEach(offer => {
            html += `
                <tr>
                    <td>${offer.offered_amount} <img src="/img/ds_graphic/${offer.offered_resource}.png" alt="${offer.offered_resource}" class="resource-icon"></td>
                    <td>${offer.requested_amount} <img src="/img/ds_graphic/${offer.requested_resource}.png" alt="${offer.requested_resource}" class="resource-icon"></td>
                    <td>${offer.village_name} (${offer.x_coord}|${offer.y_coord})</td>
                    <td>
                        ${isMyOffers ?
                            `<button class="btn btn-danger cancel-offer-btn" data-offer-id="${offer.id}">Anuluj</button>` :
                            (offer.village_id !== myVillageId ? `<button class="btn btn-success accept-offer-btn" data-offer-id="${offer.id}">Akceptuj</button>` : 'Twoja oferta')
                        }
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function setupCreateOfferForm() {
        const form = document.getElementById('create-offer-form');
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Wystawianie...';

            try {
                const response = await fetch('/ajax/market/create_offer.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'success');
                    form.reset();
                    // Switch to "My Offers" tab to see the new offer
                    document.querySelector('.tab-button[data-tab="my-offers"]').click();
                } else {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'error');
                }
            } catch (error) {
                if (window.toastManager) window.toastManager.showToast('Błąd podczas tworzenia oferty.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = 'Wystaw ofertę';
            }
        });
    }

    // Event delegation for accept/cancel buttons
    document.body.addEventListener('click', async function(e) {
        if (e.target.classList.contains('accept-offer-btn')) {
            const button = e.target;
            const offerId = button.dataset.offerId;
            button.disabled = true;

            try {
                const response = await fetch('/ajax/market/accept_offer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `offer_id=${offerId}`
                });
                const result = await response.json();

                if (result.success) {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'success');
                    loadAllOffers(); // Refresh the list
                } else {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'error');
                    button.disabled = false;
                }
            } catch (error) {
                if (window.toastManager) window.toastManager.showToast('Błąd podczas akceptowania oferty.', 'error');
                button.disabled = false;
            }
        }

        if (e.target.classList.contains('cancel-offer-btn')) {
            const button = e.target;
            const offerId = button.dataset.offerId;
            button.disabled = true;

            try {
                const response = await fetch('/ajax/market/cancel_offer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `offer_id=${offerId}`
                });
                const result = await response.json();

                if (result.success) {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'success');
                    loadMyOffers(); // Refresh the list
                } else {
                    if (window.toastManager) window.toastManager.showToast(result.message, 'error');
                    button.disabled = false;
                }
            } catch (error) {
                if (window.toastManager) window.toastManager.showToast('Błąd podczas anulowania oferty.', 'error');
                button.disabled = false;
            }
        }
    });
});