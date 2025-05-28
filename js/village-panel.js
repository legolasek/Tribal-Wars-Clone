// Dynamiczne odświeżanie panelu surowców i budynków w wiosce
function showLoader(targetId) {
    const el = document.getElementById(targetId);
    if (el) el.innerHTML = '<div class="loader">Ładowanie...</div>';
}
function showNotification(message, type = 'info') {
    let notif = document.getElementById('village-notification');
    if (!notif) {
        notif = document.createElement('div');
        notif.id = 'village-notification';
        notif.className = 'village-notification';
        document.body.appendChild(notif);
    }
    notif.className = 'village-notification ' + type;
    notif.innerHTML = message;
    notif.style.display = 'block';
    setTimeout(() => { notif.style.display = 'none'; }, 4000);
}
function fetchVillageResources() {
    showLoader('village-resources-panel');
    fetch('get_resources.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('wood-count').textContent = data.wood;
            document.getElementById('clay-count').textContent = data.clay;
            document.getElementById('iron-count').textContent = data.iron;
            document.getElementById('warehouse-capacity').textContent = data.warehouse_capacity;
            document.getElementById('population-count').textContent = data.population;
        })
        .catch(() => showNotification('Błąd ładowania surowców', 'error'));
}
function fetchVillageBuildings() {
    //showLoader('village-buildings-panel'); // Zakomentowano loader
    // fetch('get_building_details.php') // Zakomentowano fetch
    //     .then(r => r.text())
    //     .then(html => {
    //         document.getElementById('village-buildings-panel').innerHTML = html; // Zakomentowano wstawianie HTML
    //     })
    //     .catch(() => showNotification('Błąd ładowania budynków', 'error')); // Zakomentowano catch
    
    // Opcjonalnie: wyczyść panel budynków, jeśli ma pozostać pusty
    const buildingPanel = document.getElementById('village-buildings-panel');
    if(buildingPanel) {
        buildingPanel.innerHTML = ''; // Ustawia panel na pusty
    }
}
function fetchVillageQueue() {
    showLoader('village-queue-panel');
    fetch('get_building_action.php?action=queue')
        .then(r => r.text())
        .then(html => {
            document.getElementById('village-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania kolejki budowy', 'error'));
}
function fetchCurrentUnits() {
    showLoader('current-units-panel');
    fetch('get_units.php')
        .then(r => r.text())
        .then(html => {
            document.getElementById('current-units-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania jednostek', 'error'));
}
function fetchRecruitmentPanel() {
    showLoader('recruitment-panel');
    fetch('get_recruitment_panel.php')
        .then(r => r.text())
        .then(html => {
            document.getElementById('recruitment-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania panelu rekrutacji', 'error'));
}
function fetchRecruitmentQueue() {
    showLoader('recruitment-queue-panel');
    fetch('get_recruitment_queue.php')
        .then(r => r.text())
        .then(html => {
            document.getElementById('recruitment-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania kolejki rekrutacji', 'error'));
}
function fetchCurrentResearch() {
    showLoader('current-research-panel');
    fetch('get_current_research.php')
        .then(r => r.text())
        .then(html => {
            document.getElementById('current-research-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania badań', 'error'));
}
function fetchResearchQueue() {
    showLoader('research-queue-panel');
    fetch('get_research_queue.php')
        .then(r => r.text())
        .then(html => {
            document.getElementById('research-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Błąd ładowania kolejki badań', 'error'));
}
function refreshVillagePanel() {
    fetchVillageResources();
    // fetchVillageBuildings(); // Zakomentowano to wywołanie, aby usunąć pionową listę budynków
    fetchVillageQueue();
    fetchCurrentUnits();
    fetchRecruitmentPanel();
    fetchRecruitmentQueue();
    fetchCurrentResearch();
    fetchResearchQueue();
}
setInterval(refreshVillagePanel, 5000);
document.addEventListener('DOMContentLoaded', refreshVillagePanel);

// Udostępnij refreshVillagePanel globalnie
window.refreshVillagePanel = refreshVillagePanel; 