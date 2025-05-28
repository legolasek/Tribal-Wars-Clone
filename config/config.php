<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tribal_wars_new');

// Tryb debugowania (true dla środowiska deweloperskiego, false dla produkcji)
define('DEBUG_MODE', true);

// Domyślne wartości dla nowej wioski
define('INITIAL_WOOD', 500);
define('INITIAL_CLAY', 500);
define('INITIAL_IRON', 500);
define('INITIAL_WAREHOUSE_CAPACITY', 1000);
define('INITIAL_POPULATION', 1);

// Definicje dla dynamicznej pojemności magazynu (używane w BuildingManager)
define('WAREHOUSE_BASE_CAPACITY', 1000); // Pojemność magazynu na poziomie 1
define('WAREHOUSE_CAPACITY_FACTOR', 1.227); // Mnożnik pojemności dla kolejnych poziomów magazynu (przykład)

// Współczynnik redukcji czasu budowy przez Ratusz (każdy poziom Ratusza skraca czas o ten współczynnik ^ (poziom_ratusza-1))
// np. 0.95 oznacza 5% skrócenia czasu na poziom względem poprzedniego, efektywnie (1 - 0.95) = 5% szybciej
define('MAIN_BUILDING_TIME_REDUCTION_FACTOR', 0.95);

// Ścieżki
define('BASE_URL', 'http://localhost/'); // Zmień na odpowiedni URL, jeśli projekt nie jest w głównym katalogu htdocs
define('TRADER_SPEED', 100); // Prędkość kupców w polach na godzinę

// Domyślny ID świata
define('INITIAL_WORLD_ID', 1);
?>
