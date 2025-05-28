<?php
require 'init.php';
require_once 'lib/VillageManager.php';
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

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Mapa świata</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .map-container { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            margin: var(--spacing-lg) auto; /* Use CSS variable */
            padding: 0 var(--spacing-md); /* Add horizontal padding */
            max-width: 90%; /* Limit max width */
        }
        .map-grid { 
            display: grid; 
            grid-template-columns: repeat(<?php echo $size; ?>, 32px); 
            grid-template-rows: repeat(<?php echo $size; ?>, 32px); 
            gap: 1px; 
            background: var(--beige-darker); /* Use CSS variable */
            border: 2px solid var(--brown-primary); /* Use CSS variable */
            box-shadow: var(--box-shadow-default); /* Use CSS variable */
        }
        .map-tile { 
            width: 32px; 
            height: 32px; 
            background: var(--beige-medium); /* Use CSS variable */
            position: relative; 
            cursor: pointer; 
            transition: box-shadow .15s, transform .15s; /* Add transform transition */
            overflow: hidden; /* Hide overflowing content */
        }
        .map-tile:hover { 
            box-shadow: 0 0 8px rgba(var(--brown-primary-rgb), 0.5); /* Use CSS variable */
            z-index: 2; 
            transform: scale(1.05); /* Slight zoom effect */
        }
        .map-tile.selected { 
            outline: 2px solid var(--gold-highlight); /* Use CSS variable */
            z-index: 3; 
        }
        .map-tile img { 
            width: 100%; 
            height: 100%; 
            display: block; 
            object-fit: cover; /* Ensure image covers the tile */
            filter: brightness(0.8); /* Slightly darken background images */
        }
         /* Style for barbarian villages */
        .map-tile.barbarian img {
             filter: grayscale(0.8) brightness(0.7);
        }
         /* Style for player villages */
        .map-tile.player img {
             filter: brightness(1);
        }
        /* Style for own village */
        .map-tile.own-village img {
             filter: brightness(1.2) hue-rotate(150deg); /* Example tint */
        }
        
        .map-popup { 
            position: absolute; 
            left: calc(100% + var(--spacing-xs)); /* Position to the right of the tile, using CSS var */
            top: 50%;
            transform: translateY(-50%); /* Center vertically */
            background: var(--beige-light); /* Use CSS variable */
            border: 1px solid var(--brown-dark); /* Use CSS variable */
            border-radius: var(--border-radius-small); /* Use CSS variable */
            box-shadow: var(--box-shadow-hover); /* Use CSS variable */
            padding: var(--spacing-sm); /* Use CSS variable */
            min-width: 200px; /* Increased min-width */
            max-width: 300px; /* Added max-width */
            z-index: 100; /* Increased z-index */
            font-size: var(--font-size-normal); /* Use CSS variable */
            color: var(--brown-dark); /* Use CSS variable */
        }
        .map-popup h4 { 
            margin: 0 0 var(--spacing-xs) 0; /* Use CSS variable */
            font-size: var(--font-size-large); /* Use CSS variable */
            color: var(--brown-primary); /* Use CSS variable */
            border-bottom: 1px solid var(--beige-darker); /* Add separator */
            padding-bottom: var(--spacing-xs); /* Padding below separator */
        }
        .map-popup div { 
            margin-bottom: var(--spacing-xs); /* Space between details */
            line-height: 1.4; /* Improve readability */
        }
        .map-popup div:last-child { 
            margin-bottom: 0; /* No margin for the last div */
        }
        .map-popup b { 
            color: var(--brown-secondary); /* Style labels */
        }
        .map-popup button { 
            display: block; /* Make button block level */
            width: 100%; /* Full width button */
            margin-top: var(--spacing-sm); /* Use CSS variable */
             /* Inherit styles from global .btn */
            /* padding: 6px 14px; 
            background: #8d5c2c; 
            color: #fff; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold; */
        }
        .map-popup .popup-close-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: var(--font-size-large); /* Use CSS variable */
            color: var(--brown-dark); /* Use CSS variable */
            cursor: pointer;
        }
         .map-popup .popup-close-btn:hover {
             color: var(--red-error); /* Use CSS variable */
         }

        .map-controls { 
            margin-bottom: var(--spacing-md); /* Use CSS variable */
            display: flex; 
            gap: var(--spacing-md); /* Use CSS variable */
            align-items: center; /* Align items vertically */
            flex-wrap: wrap; /* Allow wrapping */
            justify-content: center; /* Center controls */
        }
        .map-controls button { 
             /* Inherit styles from global .btn */
            /* padding: 6px 14px; background: #8d5c2c; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; */
        }
        .map-controls button:disabled { 
            /* background: #ccc; color: #888; */
            opacity: 0.6; /* Indicate disabled state */
            cursor: not-allowed; /* Change cursor */
        }
        .map-controls label {
            font-weight: bold;
            color: var(--brown-dark);
        }
        .map-controls input[type="number"] {
            padding: var(--spacing-xs); /* Use CSS variable */
            border: 1px solid var(--beige-dark); /* Use CSS variable */
            border-radius: var(--border-radius-small); /* Use CSS variable */
            background-color: var(--beige-light); /* Use CSS variable */
            color: var(--brown-dark); /* Use CSS variable */
            font-size: var(--font-size-normal); /* Use CSS variable */
            width: 60px; /* Adjusted width */
            text-align: center; /* Center text */
        }

    </style>
</head>
<body>
<div class="map-container">
    <h1>Mapa świata</h1>
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
<script>
let mapX = <?php echo $x; ?>;
let mapY = <?php echo $y; ?>;
let mapSize = <?php echo $size; ?>;
let selectedTile = null;

function fetchMap() {
    document.getElementById('map-grid').innerHTML = '<div style="grid-column: 1 / -1; text-align:center; padding:30px;">Ładowanie mapy...</div>';
    fetch(`map_data.php?x=${mapX}&y=${mapY}&size=${mapSize}`)
        .then(r=>r.json())
        .then(data=>renderMap(data.villages));
}

function renderMap(villages) {
    const grid = document.getElementById('map-grid');
    grid.innerHTML = '';
    const vmap = {};
    villages.forEach(v=>{ vmap[v.x+'_'+v.y] = v; });
    for(let row=0; row<mapSize; row++) {
        for(let col=0; col<mapSize; col++) {
            const x = mapX - Math.floor(mapSize/2) + col;
            const y = mapY - Math.floor(mapSize/2) + row;
            const key = x+'_'+y;
            const v = vmap[key];
            const tile = document.createElement('div');
            tile.className = 'map-tile';
            tile.dataset.x = x;
            tile.dataset.y = y;
            if (v) {
                tile.innerHTML = `<img src="${v.img}" alt="">`;
                tile.title = v.name + ' ('+x+'|'+y+')';
                tile.onclick = e => showPopup(tile, v);
                if (v.type === 'barbarian') tile.style.filter = 'grayscale(0.3)';
                    } else {
                tile.style.background = '#e8dcc0 url(img/ds_graphic/map/empty.png) center/cover no-repeat';
                tile.onclick = () => hidePopup();
            }
            grid.appendChild(tile);
        }
    }
}

function showPopup(tile, v) {
    hidePopup();
    tile.classList.add('selected');
    selectedTile = tile;
    const popup = document.createElement('div');
    popup.className = 'map-popup';
    popup.innerHTML = `<h4>${v.name}</h4>
        <div><b>Koordynaty:</b> ${v.x}|${v.y}</div>
        <div><b>Punkty:</b> ${v.points}</div>
        <div><b>Typ:</b> ${v.type === 'barbarian' ? 'Barbarzyńska' : 'Gracza'}</div>
        <div><b>ID:</b> ${v.id}</div>
        <button onclick="hidePopup()" style="margin-top:8px;float:right;">Zamknij</button>`;
    tile.appendChild(popup);
}
function hidePopup() {
    if (selectedTile) {
        selectedTile.classList.remove('selected');
        const pop = selectedTile.querySelector('.map-popup');
        if (pop) pop.remove();
        selectedTile = null;
    }
}
function moveMap(dx,dy) {
    mapX += dx;
    mapY += dy;
    fetchMap();
    hidePopup();
}
function centerMap() {
    mapX = <?php echo $x; ?>;
    mapY = <?php echo $y; ?>;
    fetchMap();
    hidePopup();
}
function resizeMap() {
    const val = parseInt(document.getElementById('map-size').value);
    if (val >= 7 && val <= 31) {
        mapSize = val;
        document.querySelector('.map-grid').style.gridTemplateColumns = `repeat(${mapSize}, 32px)`;
        document.querySelector('.map-grid').style.gridTemplateRows = `repeat(${mapSize}, 32px)`;
        fetchMap();
        hidePopup();
    }
}
window.addEventListener('click', e => { if (!e.target.closest('.map-popup')) hidePopup(); });
fetchMap();
</script>
</body>
</html> 

<?php require 'footer.php'; ?> 