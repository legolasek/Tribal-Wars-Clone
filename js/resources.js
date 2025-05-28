/**
 * Dynamiczna aktualizacja zasobów w grze bez odświeżania strony
 */

// Klasa ResourceUpdater do zarządzania aktualizacją zasobów w czasie rzeczywistym
class ResourceUpdater {
    /**
     * Konstruktor klasy
     * @param {Object} options - Opcje konfiguracyjne
     */
    constructor(options = {}) {
        // Domyślne opcje
        this.options = {
            apiUrl: 'http://localhost/ajax_proxy.php', // Zmieniono na pełną ścieżkę URL do proxy
            updateInterval: 30000, // 30 sekund
            tickInterval: 1000, // 1 sekunda
            resourcesSelector: '#resources-bar',
            villageId: null,
            ...options
        };
        
        // Stan zasobów
        this.resources = {
            wood: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            clay: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            iron: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            population: { amount: 0 }
        };
        
        // Flagi
        this.isInitialized = false;
        this.updateTimer = null;
        this.tickTimer = null;
        this.lastServerUpdate = null;
        this.lastClientUpdate = null;
        
        // Inicjalizacja
        this.init();
    }
    
    /**
     * Inicjalizuje aktualizator zasobów
     */
    async init() {
        try {
            // Pobierz początkowe dane
            const data = await this.fetchUpdate();
            
            // Jeśli dane zostały pobrane, rozpocznij timery
            if (data) {
                this.isInitialized = true;
                this.startUpdateTimer();
                this.startTickTimer();
            }
        } catch (error) {
            console.error('Błąd inicjalizacji aktualizatora zasobów:', error);
        }
    }
    
    /**
     * Pobiera aktualizację zasobów z serwera
     */
    async fetchUpdate() {
        try {
            // Przygotuj URL zapytania
            let url = this.options.apiUrl;
            if (this.options.villageId) {
                url += `?village_id=${this.options.villageId}`;
            }
            
            console.log(`Pobieranie zasobów z: ${url}`);
            
            // Wykonaj zapytanie
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                credentials: 'same-origin'
            });
            
            // Sprawdź, czy odpowiedź jest poprawna
            if (!response.ok) {
                // Specjalne traktowanie błędów 401 i 500
                if (response.status === 401) {
                    console.log('Sesja wygasła lub użytkownik nie jest zalogowany. Przekierowanie do strony logowania...');
                    // Opcjonalne przekierowanie do strony logowania - można odkomentować
                    // window.location.href = 'login.php';
                    return null;
                }
                
                if (response.status === 500) {
                    const errorText = await response.text();
                    console.error('Błąd serwera 500:', errorText);
                    throw new Error(`Błąd HTTP 500: ${errorText.substring(0, 100)}...`);
                }
                
                throw new Error(`Błąd HTTP: ${response.status}`);
            }
            
            // Przetwórz odpowiedź JSON - dodaj obsługę błędów
            let data;
            try {
                const text = await response.text();
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Nieprawidłowa odpowiedź JSON:', text.substring(0, 100) + '...');
                    throw new Error('Serwer zwrócił nieprawidłowy format danych');
                }
            } catch (e) {
                console.error('Błąd przetwarzania odpowiedzi:', e);
                throw e;
            }
            
            // Sprawdź status odpowiedzi
            if (data.status !== 'success') {
                throw new Error(data.message || 'Nieznany błąd serwera');
            }
            
            // Zaktualizuj dane zasobów, w tym produkcję i produkcję na sekundę
            this.resources.wood = {
                amount: parseFloat(data.data.wood.amount),
                capacity: parseFloat(data.data.wood.capacity),
                production: parseFloat(data.data.wood.production) || 0,
                production_per_second: parseFloat(data.data.wood.production_per_second) || 0
            };
            this.resources.clay = {
                amount: parseFloat(data.data.clay.amount),
                capacity: parseFloat(data.data.clay.capacity),
                production: parseFloat(data.data.clay.production) || 0,
                production_per_second: parseFloat(data.data.clay.production_per_second) || 0
            };
            this.resources.iron = {
                amount: parseFloat(data.data.iron.amount),
                capacity: parseFloat(data.data.iron.capacity),
                production: parseFloat(data.data.iron.production) || 0,
                production_per_second: parseFloat(data.data.iron.production_per_second) || 0
            };
            // Populacja nie ma produkcji/capacity per second, tylko amount i capacity
            this.resources.population = {
                amount: parseFloat(data.data.population.amount),
                capacity: parseFloat(data.data.population.capacity) || 0 // Może być 0 na początkowych poziomach
            };
            
            // Zapisz czas ostatniej aktualizacji
            this.lastServerUpdate = new Date(data.data.current_server_time);
            this.lastClientUpdate = new Date();
            
            // Zaktualizuj UI
            this.updateUI();
            
            return data;
        } catch (error) {
            console.error('Błąd pobierania aktualizacji zasobów:', error);
            // Nie przerywaj działania programu, kontynuuj używając poprzednich wartości
            return null;
        }
    }
    
    /**
     * Aktualizuje UI z aktualnymi wartościami zasobów
     */
    updateUI() {
        // Znajdź kontener zasobów
        const container = document.querySelector(this.options.resourcesSelector);
        if (!container) return;
        
        // Aktualizuj wartości zasobów
        this.updateResourceDisplay(container, 'wood', this.resources.wood);
        this.updateResourceDisplay(container, 'clay', this.resources.clay);
        this.updateResourceDisplay(container, 'iron', this.resources.iron);
        this.updateResourceDisplay(container, 'population', this.resources.population);
    }
    
    /**
     * Aktualizuje wyświetlanie pojedynczego zasobu w UI, w tym tooltipy i paski.
     * @param {HTMLElement} container - Główny kontener zasobów.
     * @param {string} resourceType - Typ zasobu (np. 'wood', 'clay').
     * @param {Object} resourceData - Obiekt z danymi zasobu (amount, capacity, production_per_hour, production_per_second).
     */
    updateResourceDisplay(container, resourceType, resourceData) {
        const currentAmount = Math.floor(resourceData.amount);
        const capacity = resourceData.capacity;
        const productionPerHour = resourceData.production_per_hour;

        // Aktualizuj główny pasek zasobów
        const valueElement = container.querySelector(`#current-${resourceType}`);
        if (valueElement) {
            valueElement.textContent = this.formatNumber(currentAmount);
            if (capacity) {
                if (currentAmount >= capacity * 0.9 && currentAmount < capacity) {
                    valueElement.classList.add('resource-almost-full');
                    valueElement.classList.remove('resource-full');
                } else if (currentAmount >= capacity) {
                    valueElement.classList.add('resource-full');
                    valueElement.classList.remove('resource-almost-full');
                } else {
                    valueElement.classList.remove('resource-almost-full', 'resource-full');
                }
            }
        }

        const capacityElement = container.querySelector(`#capacity-${resourceType}`);
        if (capacityElement && capacity) {
            capacityElement.textContent = this.formatNumber(capacity);
        }

        const productionElement = container.querySelector(`#prod-${resourceType}`);
        if (productionElement && productionPerHour !== undefined) {
            productionElement.textContent = `+${this.formatNumber(productionPerHour)}/h`;
        }

        // Aktualizuj tooltip
        const tooltipCurrent = container.querySelector(`#tooltip-current-${resourceType}`);
        if (tooltipCurrent) tooltipCurrent.textContent = this.formatNumber(currentAmount);

        const tooltipCapacity = container.querySelector(`#tooltip-capacity-${resourceType}`);
        if (tooltipCapacity && capacity) tooltipCapacity.textContent = this.formatNumber(capacity);

        const tooltipProduction = container.querySelector(`#tooltip-prod-${resourceType}`);
        if (tooltipProduction && productionPerHour !== undefined) {
            tooltipProduction.textContent = `+${this.formatNumber(productionPerHour)}/h`;
        }

        // Aktualizuj pasek postępu w tooltipie
        const progressBarInner = container.querySelector(`#bar-${resourceType}`);
        if (progressBarInner && capacity) {
            const percentage = Math.min(100, (currentAmount / capacity) * 100);
            progressBarInner.style.width = `${percentage}%`;
        }
    }
    
    /**
     * Aktualizuje wartości zasobów na podstawie upływu czasu
     */
    tick() {
        if (!this.isInitialized || !this.lastClientUpdate) return;
        
        // Oblicz czas, który upłynął od ostatniej aktualizacji klienta
        const now = new Date();
        const elapsedSeconds = (now - this.lastClientUpdate) / 1000;
        this.lastClientUpdate = now;
        
        // Zaktualizuj wartości zasobów na podstawie produkcji na sekundę
        for (const resourceType of ['wood', 'clay', 'iron']) {
            const resource = this.resources[resourceType];
            
            // Dodaj wyprodukowane zasoby
            if (resource.production_per_second > 0) {
                const newAmount = resource.amount + (resource.production_per_second * elapsedSeconds);
                
                // Nie przekraczaj pojemności magazynu
                resource.amount = resource.capacity ? Math.min(newAmount, resource.capacity) : newAmount;
            }
        }
        // Populacja nie ma produkcji, ale może mieć limit
        if (this.resources.population.capacity) {
            this.resources.population.amount = Math.min(this.resources.population.amount, this.resources.population.capacity);
        }
        
        // Zaktualizuj UI
        this.updateUI();
    }
    
    /**
     * Rozpoczyna timer aktualizacji z serwera
     */
    startUpdateTimer() {
        this.stopUpdateTimer();
        this.updateTimer = setInterval(() => this.fetchUpdate(), this.options.updateInterval);
    }
    
    /**
     * Zatrzymuje timer aktualizacji z serwera
     */
    stopUpdateTimer() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }
    
    /**
     * Rozpoczyna timer tikowania
     */
    startTickTimer() {
        this.stopTickTimer();
        this.tickTimer = setInterval(() => this.tick(), this.options.tickInterval);
    }
    
    /**
     * Zatrzymuje timer tikowania
     */
    stopTickTimer() {
        if (this.tickTimer) {
            clearInterval(this.tickTimer);
            this.tickTimer = null;
        }
    }
    
    /**
     * Formatuje liczbę do wyświetlenia
     */
    formatNumber(number) {
        return window.formatNumber(number); // Użyj globalnej funkcji z utils.js
    }
}

// Inicjalizacja aktualizatora zasobów po załadowaniu dokumentu
document.addEventListener('DOMContentLoaded', () => {
    // Pobierz ID wioski z globalnej zmiennej JavaScript
    const villageId = window.currentVillageId || null;
    
    // Inicjalizuj aktualizator tylko jeśli ID wioski jest dostępne
    if (villageId) {
        window.resourceUpdater = new ResourceUpdater({
            villageId: villageId
        });
    } else {
        console.warn('Nie znaleziono ID wioski. Aktualizator zasobów nie zostanie uruchomiony.');
    }
});
