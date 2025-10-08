<div id="market-panel">
    <h2>Targ</h2>
    <div class="market-tabs">
        <button class="tab-button active" data-tab="trade">Handel</button>
        <button class="tab-button" data-tab="create-offer">Stwórz ofertę</button>
        <button class="tab-button" data-tab="my-offers">Własne oferty</button>
    </div>

    <!-- Trade/View Offers Tab -->
    <div id="trade" class="market-tab-content active">
        <h3>Dostępne oferty</h3>
        <div id="market-offers-list" class="market-offers-container">
            <!-- Offers will be loaded here by JS -->
            <p>Ładowanie ofert...</p>
        </div>
    </div>

    <!-- Create Offer Tab -->
    <div id="create-offer" class="market-tab-content">
        <h3>Stwórz nową ofertę</h3>
        <form id="create-offer-form">
            <div class="offer-section">
                <h4>Oferuję:</h4>
                <select name="offered_resource" required>
                    <option value="wood">Drewno</option>
                    <option value="clay">Glina</option>
                    <option value="iron">Żelazo</option>
                </select>
                <input type="number" name="offered_amount" placeholder="Ilość" required min="1">
            </div>
            <div class="offer-section">
                <h4>W zamian za:</h4>
                <select name="requested_resource" required>
                    <option value="wood">Drewno</option>
                    <option value="clay">Glina</option>
                    <option value="iron">Żelazo</option>
                </select>
                <input type="number" name="requested_amount" placeholder="Ilość" required min="1">
            </div>
            <button type="submit" class="btn btn-primary">Wystaw ofertę</button>
        </form>
    </div>

    <!-- My Offers Tab -->
    <div id="my-offers" class="market-tab-content">
        <h3>Twoje oferty</h3>
        <div id="my-offers-list" class="market-offers-container">
            <!-- Player's own offers will be loaded here by JS -->
            <p>Ładowanie twoich ofert...</p>
        </div>
    </div>
</div>

<style>
    #market-panel {
        padding: 10px;
    }
    .market-tabs {
        display: flex;
        border-bottom: 1px solid #ccc;
        margin-bottom: 15px;
    }
    .tab-button {
        padding: 10px 15px;
        cursor: pointer;
        border: none;
        background-color: transparent;
        border-bottom: 2px solid transparent;
    }
    .tab-button.active {
        border-bottom: 2px solid var(--brown-primary);
        font-weight: bold;
    }
    .market-tab-content {
        display: none;
    }
    .market-tab-content.active {
        display: block;
    }
    .market-offers-container {
        max-height: 300px;
        overflow-y: auto;
    }
    #create-offer-form .offer-section {
        margin-bottom: 10px;
    }
    #create-offer-form select, #create-offer-form input {
        margin-right: 10px;
    }
</style>