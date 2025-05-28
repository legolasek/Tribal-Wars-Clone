<?php
require 'init.php';
require_once __DIR__ . '/lib/managers/VillageManager.php';
require_once 'config/config.php';
require_once 'lib/Database.php';

// Zabezpieczenie dostępu - tylko dla zalogowanych
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Pobierz aktywną wioskę użytkownika
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);

// Ustal pozycję startową (środek mapy lub wioska gracza)
$x = isset($_GET['x']) ? (int)$_GET['x'] : 50;
$y = isset($_GET['y']) ? (int)$_GET['y'] : 50;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;

// Parametry widoku mapy (z GET lub domyślne)
$center_x = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$center_y = isset($_GET['y']) ? (int)$_GET['y'] : 0;

// Jeśli mamy wioskę, użyj jej współrzędnych jako domyślne centrum mapy
if ($village_id) {
    $village = $villageManager->getVillageInfo($village_id);
    if ($village) {
        // Jeśli nie podano współrzędnych w GET, użyj koordynatów wioski
        if (!isset($_GET['x'])) $center_x = $village['x_coord'];
        if (!isset($_GET['y'])) $center_y = $village['y_coord'];
    }
}

$radius = isset($_GET['radius']) ? (int)$_GET['radius'] : 5;

// Ograniczenie maksymalnego promienia
if ($radius > 20) $radius = 20;
if ($radius < 3) $radius = 3;

// Pobierz wioski w zakresie
$stmt = $conn->prepare("
    SELECT v.id, v.name, v.x_coord, v.y_coord, v.user_id, u.username
    FROM villages v
    LEFT JOIN users u ON v.user_id = u.id
    WHERE 
        v.world_id = ? AND
        v.x_coord BETWEEN ? AND ? AND 
        v.y_coord BETWEEN ? AND ?
    ORDER BY v.y_coord ASC, v.x_coord ASC
");

$world_id = CURRENT_WORLD_ID;
$min_x = $center_x - $radius;
$max_x = $center_x + $radius;
$min_y = $center_y - $radius;
$max_y = $center_y + $radius;

$stmt->bind_param("iiiii", $world_id, $min_x, $max_x, $min_y, $max_y);
$stmt->execute();
$result = $stmt->get_result();

// Przygotuj tablicę wiosek
$villages_map = [];
while ($row = $result->fetch_assoc()) {
    $villages_map[$row['y_coord']][$row['x_coord']] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'owner' => $row['username'] ?? 'Wolna wioska',
        'user_id' => $row['user_id'],
        'is_own' => ($row['user_id'] == $user_id)
    ];
}

$pageTitle = 'Mapa Świata';
require 'header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">🗺️</span> <!-- Ikona dla mapy -->
            <span>Mapa Świata</span>
        </div>
        <div class="header-user">
            Gracz: <?= htmlspecialchars($username) ?><br>
            <?php if ($village): ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>

    <main id="main-content">
        <h2>Mapa świata</h2>

        <div class="map-container">
            <div class="map-controls">
                <button onclick="moveMap(0,-1)">↑</button>
                <button onclick="moveMap(-1,0)">←</button>
                <button onclick="centerMap()">Centruj</button>
                <button onclick="moveMap(1,0)">→</button>
                <button onclick="moveMap(0,1)">↓</button>
                <label>Rozmiar: <input type="number" id="map-size" min="7" max="31" value="<?php echo $size; ?>" style="width:50px;"> </label>
                <button onclick="resizeMap()">Zmień</button>
            </div>
            <div id="map-grid" class="map-grid"></div>
        </div>

        <!-- Popup for map tile details -->
        <div id="map-popup" class="map-popup" style="display: none;">
            <button class="popup-close-btn">&times;</button>
            <h4 id="popup-village-name"></h4>
            <div><b>Właściciel:</b> <span id="popup-village-owner"></span></div>
            <div><b>Współrzędne:</b> <span id="popup-village-coords"></span></div>
            <button id="popup-send-units" class="btn">Wyślij jednostki</button>
            <!-- <button id="popup-send-resources" class="btn">Wyślij surowce</button> -->
        </div>
    </main>
</div>

<script>
// Pass village data to JavaScript
const villagesData = <?php echo json_encode($villages_map); ?>;
const currentVillageId = <?php echo $village_id ?? 'null'; ?>;
const centerCoords = { x: <?php echo $center_x; ?>, y: <?php echo $center_y; ?> };
const mapRadius = <?php echo $radius; ?>;
const mapSize = <?php echo $size; ?>;

document.addEventListener('DOMContentLoaded', () => {
    renderMap(villagesData, centerCoords, mapRadius, mapSize, currentVillageId);

    // Add event listeners for map controls
    document.querySelector('.map-controls button:nth-child(1)').addEventListener('click', () => moveMap(0, -1));
    document.querySelector('.map-controls button:nth-child(2)').addEventListener('click', () => moveMap(-1, 0));
    document.querySelector('.map-controls button:nth-child(3)').addEventListener('click', () => centerMap());
    document.querySelector('.map-controls button:nth-child(4)').addEventListener('click', () => moveMap(1, 0));
    document.querySelector('.map-controls button:nth-child(5)').addEventListener('click', () => moveMap(0, 1));
    document.getElementById('map-size').addEventListener('change', () => resizeMap()); // Listen for change on input
    document.querySelector('.map-controls button:nth-child(7)').addEventListener('click', () => resizeMap()); // Listen for button click

    // Event delegation for map tiles
    document.getElementById('map-grid').addEventListener('click', function(event) {
        const tile = event.target.closest('.map-tile');
        if (tile && !tile.dataset.x === undefined && !tile.dataset.y === undefined) { // Ensure it's a valid tile
            const x = parseInt(tile.dataset.x);
            const y = parseInt(tile.dataset.y);
            showVillagePopup(x, y);
        }
    });

    // Close popup button
    document.querySelector('#map-popup .popup-close-btn').addEventListener('click', hideVillagePopup);

    // Send units button
    document.getElementById('popup-send-units').addEventListener('click', function() {
        const villageId = this.dataset.villageId;
        if (villageId) {
            window.location.href = `attack.php?target_village_id=${villageId}`;
        }
    });

    // Handle clicks outside the popup to close it
    document.addEventListener('click', function(event) {
        const popup = document.getElementById('map-popup');
        const isClickInsidePopup = popup.contains(event.target);
        const isClickOnTile = event.target.closest('.map-tile');
        
        // Close popup if clicked outside the popup AND not on a map tile
        if (!isClickInsidePopup && !isClickOnTile && popup.style.display !== 'none') {
            hideVillagePopup();
        }
    });

    // Prevent clicks inside the popup from closing it via the document listener
    document.getElementById('map-popup').addEventListener('click', function(event) {
        event.stopPropagation();
    });
});

function renderMap(villages, center, radius, size, currentVillageId) {
    const mapGrid = document.getElementById('map-grid');
    mapGrid.innerHTML = ''; // Clear existing map
    const startX = center.x - radius;
    const startY = center.y - radius;

    for (let y = startY; y <= center.y + radius; y++) {
        for (let x = startX; x <= center.x + radius; x++) {
            const village = villages[y] ? villages[y][x] : null;
            const tile = document.createElement('div');
            tile.classList.add('map-tile');
            tile.dataset.x = x;
            tile.dataset.y = y;

            let tileContent = '';
            let villageClass = '';

            if (village) {
                if (village.is_own) {
                    villageClass = 'own-village';
                    tileContent = '<img src="img/ds_graphic/buildings/main_building.png" alt="Wioska gracza">'; // Placeholder image
                } else if (village.user_id === null) {
                    villageClass = 'barbarian';
                    tileContent = '<img src="img/ds_graphic/buildings/main_building.png" alt="Wioska barbarzyńska">'; // Placeholder image
                } else {
                    villageClass = 'player';
                    tileContent = '<img src="img/ds_graphic/buildings/main_building.png" alt="Wioska gracza">'; // Placeholder image
                }
            } else {
                // Example background image for empty tiles
                // tileContent = '<img src="img/map/map_bg/grass.jpg" alt="Teran">'; // You might have different terrains
                 tileContent = ''; // Leave empty for now if no terrain images
            }
            
            tile.classList.add(villageClass);
            tile.innerHTML = tileContent + '<span class="coords">' + x + '|' + y + '</span>'; // Display coords on tile
            mapGrid.appendChild(tile);
        }
    }
     // Adjust grid columns based on size
     mapGrid.style.gridTemplateColumns = `repeat(${size}, 32px)`;
     mapGrid.style.gridTemplateRows = `repeat(${size}, 32px)`;
}

function moveMap(dx, dy) {
    const currentX = centerCoords.x;
    const currentY = centerCoords.y;
    window.location.href = `map.php?x=${currentX + dx * mapSize}&y=${currentY + dy * mapSize}&radius=${mapRadius}&size=${mapSize}`;
}

function centerMap() {
     // Assuming the user's village coordinates are available globally or fetched
     // For now, let's assume currentVillageCoords is available from PHP if $village is set
     <?php if ($village): ?>
     const currentVillageCoords = { x: <?php echo $village['x_coord']; ?>, y: <?php echo $village['y_coord']; ?> };
     window.location.href = `map.php?x=${currentVillageCoords.x}&y=${currentVillageCoords.y}&radius=${mapRadius}&size=${mapSize}`;
     <?php else: ?>
     // Fallback or error if user has no village
     console.error("Cannot center map: User has no village.");
     <?php endif; ?>
}

function resizeMap() {
     const newSize = parseInt(document.getElementById('map-size').value);
     if (!isNaN(newSize) && newSize >= 7 && newSize <= 31) {
          const newRadius = Math.floor((newSize - 1) / 2); // Calculate new radius based on size
          window.location.href = `map.php?x=${centerCoords.x}&y=${centerCoords.y}&radius=${newRadius}&size=${newSize}`;
     } else {
          alert('Rozmiar mapy musi być liczbą między 7 a 31.');
     }
}

function showVillagePopup(x, y) {
    const village = villagesData[y] ? villagesData[y][x] : null;
    const popup = document.getElementById('map-popup');
    const popupVillageName = document.getElementById('popup-village-name');
    const popupVillageOwner = document.getElementById('popup-village-owner');
    const popupVillageCoords = document.getElementById('popup-village-coords');
    const popupSendUnitsButton = document.getElementById('popup-send-units');
    
    if (village) {
        popupVillageName.textContent = village.name;
        popupVillageOwner.textContent = village.owner;
        popupVillageCoords.textContent = `${x}|${y}`;
        
        // Set village ID for buttons
        popupSendUnitsButton.dataset.villageId = village.id;

        // Show/hide/enable/disable buttons based on village type/ownership
        if (village.is_own) {
            popupSendUnitsButton.style.display = 'none'; // Can't attack own village
        } else {
             popupSendUnitsButton.style.display = 'block';
             // Maybe disable attack button if not enough units/conditions not met
             // For now, always enabled for non-own villages
        }
        
        // Position the popup near the tile
        const tileElement = document.querySelector(`.map-tile[data-x="${x}"][data-y="${y}"]`);
        if (tileElement) {
             const tileRect = tileElement.getBoundingClientRect();
             const gameContainer = document.getElementById('game-container'); // Assuming game-container is the scrollable parent
             const containerRect = gameContainer.getBoundingClientRect();

             // Calculate position relative to the game-container (or viewport if container is not relative)
             let popupLeft = tileRect.right + 10; // 10px right of the tile
             let popupTop = tileRect.top + tileRect.height / 2; // Vertically centered with tile
             
             // Adjust position if near the right edge of the viewport
             if (popupLeft + popup.offsetWidth > window.innerWidth - 20) { // 20px margin from right edge
                 popupLeft = tileRect.left - popup.offsetWidth - 10; // Position to the left of the tile
             }

              // Adjust position relative to the top of the game container if game-container has position: relative
              // Otherwise, position relative to viewport
              // Assuming game-container has position: relative
              popup.style.left = `${popupLeft - containerRect.left}px`;
              popup.style.top = `${popupTop - containerRect.top - popup.offsetHeight / 2}px`;

             popup.style.display = 'block';
        }

    } else {
        // Handle empty tile click - maybe show different info or do nothing
        hideVillagePopup();
    }
}

function hideVillagePopup() {
    document.getElementById('map-popup').style.display = 'none';
}

// Helper function to format duration (from buildings.js or similar)
// Make sure this function is available globally or included here
// function formatDuration(seconds) { ... }
</script>

<?php require 'footer.php'; ?> 