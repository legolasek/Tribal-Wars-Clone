<?php

/**
 * Plik zawierający funkcje pomocnicze używane w całym systemie
 * Inspirowany starą wersją VeryOldTemplate
 */

/**
 * Formatuje czas w sekundach do formatu HH:MM:SS
 */
function formatTime($seconds) {
    return gmdate("H:i:s", $seconds);
}

/**
 * Formatuje datę w formacie czytelnym dla użytkownika
 */
function formatDate($timestamp) {
    return date("Y-m-d H:i:s", $timestamp);
}


/**
 * Filtruje i sanityzuje dane wejściowe
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }
    
    // Usuń białe znaki z początku i końca
    $input = trim($input);
    
    // Usuń znaki HTML
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Sanityzuje dane wejściowe dla zapytań SQL
 */
function sanitizeSql($conn, $input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeSql($conn, $value);
        }
        return $input;
    }
    
    if ($conn instanceof mysqli) {
        return $conn->real_escape_string($input);
    }
    
    return addslashes($input);
}

/**
 * Generuje unikalny token dla sesji
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Oblicza odległość między dwoma punktami na mapie
 */
function calculateDistance($x1, $y1, $x2, $y2) {
    return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
}

/**
 * Oblicza czas podróży jednostek między wioskami (w sekundach)
 */
function calculateTravelTime($distance, $speed) {
    // Prędkość jest w polach na godzinę, a czas w sekundach
    return ($distance / $speed) * 3600;
}

/**
 * Oblicza czas zakończenia zadania (budowa, rekrutacja, etc.)
 */
function calculateEndTime($duration_seconds) {
    return time() + $duration_seconds;
}

/**
 * Oblicza poziom punktów gracza na podstawie jego budynków i jednostek
 */
function calculatePlayerPoints($conn, $user_id) {
    // Pobierz punkty z budynków
    $stmt = $conn->prepare("
        SELECT SUM(bt.base_points * vb.level) AS building_points
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        JOIN villages v ON vb.village_id = v.id
        WHERE v.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $building_points = $row['building_points'] ?? 0;
    $stmt->close();
    
    // Pobierz punkty z jednostek
    $stmt = $conn->prepare("
        SELECT SUM(ut.points * vu.amount) AS unit_points
        FROM village_units vu
        JOIN unit_types ut ON vu.unit_type_id = ut.id
        JOIN villages v ON vu.village_id = v.id
        WHERE v.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unit_points = $row['unit_points'] ?? 0;
    $stmt->close();
    
    return $building_points + $unit_points;
}

/**
 * Zwraca koordynaty na mapie w formacie "X|Y"
 */
function formatCoordinates($x, $y) {
    return $x . "|" . $y;
}

/**
 * Oblicza pozycję na podstawie koordynatów na mapie
 */
function calculateMapPosition($x, $y, $map_size) {
    $position = ($y * $map_size) + $x;
    return $position;
}

/**
 * Zaokrągla liczbę do określonej liczby miejsc po przecinku
 */
function roundNumber($number, $decimals = 0) {
    return round($number, $decimals);
}

/**
 * Formatuje liczbę z separatorami tysięcy
 */
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

/**
 * Konwertuje sekundy na format godziny:minuty:sekundy
 */
function secondsToTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

/**
 * Sprawdza czy nazwa użytkownika jest dozwolona (nie zawiera zakazanych znaków)
 */
function isValidUsername($username) {
    // Nazwa użytkownika musi mieć od 3 do 20 znaków i może zawierać tylko litery, cyfry i podkreślenia
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * Sprawdza czy nazwa wioski jest dozwolona
 */
function isValidVillageName($name) {
    // Nazwa wioski musi mieć od 2 do 30 znaków i może zawierać litery, cyfry, spacje i podstawowe znaki interpunkcyjne
    return preg_match('/^[a-zA-Z0-9 \.\,\-\_]{2,30}$/', $name);
}

/**
 * Hashuje hasło z wykorzystaniem salt (wykorzystując nowoczesne metody PHP)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Sprawdza czy hasło jest poprawne
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generuje losowy kolor dla gracza
 */
function generatePlayerColor() {
    $colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ff8000', '#8000ff'];
    return $colors[array_rand($colors)];
}

/**
 * Pobiera adres IP użytkownika
 */
function getClientIP() {
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if(isset($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } else if(isset($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } else if(isset($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/**
 * Generuje losowe koordynaty dla nowej wioski
 */
function generateRandomCoordinates($conn, $map_size = 100) {
    $max_attempts = 50; // Maksymalna liczba prób znalezienia wolnych koordynatów
    $attempt = 0;
    
    do {
        $x = rand(0, $map_size - 1);
        $y = rand(0, $map_size - 1);
        
        // Sprawdź czy koordynaty są już zajęte
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM villages WHERE x_coord = ? AND y_coord = ?");
        $stmt->bind_param("ii", $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $is_occupied = $row['count'] > 0;
        $attempt++;
        
    } while ($is_occupied && $attempt < $max_attempts);
    
    if ($attempt >= $max_attempts) {
        // Jeśli nie udało się znaleźć wolnych koordynatów, użyj domyślnych
        return ['x' => 0, 'y' => 0];
    }
    
    return ['x' => $x, 'y' => $y];
}

/**
 * Zwraca typ terenu na podstawie koordynatów (funkcja pseudo-losowa ale deterministyczna)
 */
function getTerrainType($x, $y) {
    $hash = $x * 1000 + $y;
    $types = ['plain', 'forest', 'hill', 'mountain', 'water'];
    
    // Zapewnienie, że ten sam punkt zawsze będzie miał ten sam typ terenu
    srand($hash);
    $type = $types[rand(0, count($types) - 1)];
    srand(time()); // Przywrócenie losowości
    
    return $type;
}

/**
 * Generates or returns existing CSRF token stored in session.
 */
function getCSRFToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Dodaje komunikat do wyświetlenia na stronie (toast).
 * @param string $message Treść komunikatu.
 * @param string $type Typ komunikatu (success, error, info, warning).
 */
function setGameMessage($message, $type = 'info') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['game_messages'])) {
        $_SESSION['game_messages'] = [];
    }
    $_SESSION['game_messages'][] = ['message' => $message, 'type' => $type];
}

/**
 * Validates CSRF token in POST requests.
 * Terminates script with 403 if invalid.
 */
function validateCSRF() {
    // Sprawdź, czy to żądanie AJAX
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Logowanie próby ataku CSRF (opcjonalne)
        error_log("CSRF validation failed. Session token: " . ($_SESSION['csrf_token'] ?? 'none') . ", Post token: " . ($_POST['csrf_token'] ?? 'none'));

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Błąd walidacji CSRF. Odśwież stronę i spróbuj ponownie.']);
        } else {
            // Przekierowanie lub wyświetlenie błędu dla zwykłych żądań
            setGameMessage('Błąd walidacji CSRF. Odśwież stronę i spróbuj ponownie.', 'error');
            // Możesz wybrać, gdzie przekierować, np. na stronę główną lub logowania
            header("Location: index.php");
        }
        exit(); // Zamiast die()
    }
}

/**
 * Returns the name of the current world.
 */
function getCurrentWorldName($conn) {
    // Use variable for constant to satisfy bind_param reference requirement
    $worldId = CURRENT_WORLD_ID;
    $stmt = $conn->prepare("SELECT name FROM worlds WHERE id = ?");
    $stmt->bind_param("i", $worldId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: [];
    $stmt->close();
    return $row['name'] ?? '';
}

/**
 * Formatuje czas w sposób przyjazny dla użytkownika (np. "za 2 godziny", "za 5 minut").
 * 
 * @param int $timestamp - docelowy timestamp
 * @return string - sformatowany czas
 */
function formatTimeToHuman($timestamp) {
    $now = time();
    $diff = $timestamp - $now;
    
    if ($diff <= 0) {
        return "teraz";
    }
    
    if ($diff < 60) {
        return "za " . $diff . " " . ($diff == 1 ? "sekundę" : ($diff < 5 ? "sekundy" : "sekund"));
    }
    
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "za " . $minutes . " " . ($minutes == 1 ? "minutę" : ($minutes < 5 ? "minuty" : "minut"));
    }
    
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "za " . $hours . " " . ($hours == 1 ? "godzinę" : ($hours < 5 ? "godziny" : "godzin"));
    }
    
    $days = floor($diff / 86400);
    return "za " . $days . " " . ($days == 1 ? "dzień" : "dni");
}

/**
 * Wyświetla zasób w formacie HTML
 */
function displayResource($resource_type, $amount, $show_max = false, $max_amount = 0) {
    $icons = [
        'wood' => '../img/ds_graphic/wood.png',
        'clay' => '../img/ds_graphic/stone.png',
        'iron' => '../img/ds_graphic/iron.png',
        'population' => '../img/ds_graphic/resources/population.png'
    ];
    
    $names = [
        'wood' => 'Drewno',
        'clay' => 'Glina',
        'iron' => 'Żelazo',
        'population' => 'Populacja'
    ];
    
    $icon = isset($icons[$resource_type]) ? $icons[$resource_type] : '';
    $name = isset($names[$resource_type]) ? $names[$resource_type] : $resource_type;
    
    $output = '<img src="' . $icon . '" alt="' . $name . '" title="' . $name . '"> ';
    $output .= '<span class="resource-value" id="current-' . $resource_type . '">' . formatNumber($amount) . '</span>';
    
    if ($show_max && $max_amount > 0) {
        $output .= '<span class="resource-capacity">/<span id="capacity-' . $resource_type . '">' . formatNumber($max_amount) . '</span></span>';
        
        // Dodaj tooltip z informacjami o zasobie
        $percentage = min(100, round(($amount / $max_amount) * 100));
        
        $output .= '<div class="resource-tooltip">
            <div class="resource-info">
                <span class="resource-info-label">' . $name . ':</span>
                <span><span id="tooltip-current-' . $resource_type . '">' . formatNumber($amount) . '</span>/<span id="tooltip-capacity-' . $resource_type . '">' . formatNumber($max_amount) . '</span></span>
            </div>
            <div class="resource-info">
                <span class="resource-info-label">Produkcja:</span>
                <span id="tooltip-prod-' . $resource_type . '"></span>
            </div>
            <div class="resource-info">
                <span class="resource-info-label">Zapełnienie:</span>
                <span id="tooltip-percentage-' . $resource_type . '">' . $percentage . '%</span>
            </div>
            <div class="resource-bar-outer">
                <div class="resource-bar-inner" id="bar-' . $resource_type . '" style="width: ' . $percentage . '%"></div>
            </div>
        </div>';
    }
    
    return $output;
}

/**
 * Oblicza godzinową produkcję zasobu na podstawie poziomu budynku.
 * Ta funkcja jest uproszczona i powinna być zastąpiona przez ResourceManager.
 * @deprecated Użyj ResourceManager->getHourlyProductionRate()
 */
function calculateHourlyProduction($building_type, $level) {
    // Przykładowa logika produkcji, do zastąpienia przez dane z bazy/konfiguracji
    // To jest uproszczona wersja, która powinna być obsługiwana przez ResourceManager
    $base_production = 100; // Bazowa produkcja na godzinę dla poziomu 1
    $growth_factor = 1.2; // Współczynnik wzrostu produkcji na poziom

    if ($level <= 0) {
        return 0;
    }

    return round($base_production * pow($growth_factor, $level - 1));
}

/**
 * Dodaje powiadomienie dla użytkownika.
 * 
 * @param int $user_id - ID użytkownika
 * @param string $type - typ powiadomienia (info, success, warning, error)
 * @param string $message - treść powiadomienia
 * @param string $link - opcjonalny link do strony
 * @param int $expires_at - kiedy powiadomienie wygasa (timestamp)
 * @return bool - czy powiadomienie zostało dodane
 */
function addNotification($conn, $user_id, $type, $message, $link = '', $expires_at = 0) {
    // Domyślnie powiadomienie wygasa po 7 dniach
    if ($expires_at <= 0) {
        $expires_at = time() + (7 * 24 * 60 * 60);
    }
    
    // Sprawdź, czy tabela notifications istnieje
    $table_exists = false;
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result && $result->num_rows > 0) {
        $table_exists = true;
    }
    
    // Jeśli tabela nie istnieje, utwórz ją
    if (!$table_exists) {
        $create_table = "CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read TINYINT(1) DEFAULT 0,
            expires_at INT(11) NOT NULL,
            PRIMARY KEY (id),
            KEY (user_id),
            KEY (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        if (!$conn->query($create_table)) {
            error_log("Nie można utworzyć tabeli powiadomień: " . $conn->error);
            return false;
        }
    }
    
    // Dodaj powiadomienie do bazy
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $user_id, $type, $message, $link, $expires_at);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Pobiera powiadomienia dla użytkownika.
 * 
 * @param int $user_id - ID użytkownika
 * @param bool $only_unread - czy pobierać tylko nieprzeczytane powiadomienia
 * @param int $limit - maksymalna liczba powiadomień
 * @return array - lista powiadomień
 */
function getNotifications($conn, $user_id, $only_unread = false, $limit = 10) {
    // Sprawdź, czy tabela notifications istnieje
    $table_exists = false;
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result && $result->num_rows > 0) {
        $table_exists = true;
    }
    
    if (!$table_exists) {
        return [];
    }
    
    // Usuń wygasłe powiadomienia
    $current_time = time();
    $stmt_delete = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND expires_at < ?");
    $stmt_delete->bind_param("ii", $user_id, $current_time);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    // Pobierz powiadomienia
    $query = "SELECT id, type, message, link, created_at, is_read FROM notifications 
              WHERE user_id = ? AND expires_at > ?";
    
    if ($only_unread) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $current_time, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Oznacza powiadomienie jako przeczytane.
 * 
 * @param int $notification_id - ID powiadomienia
 * @param int $user_id - ID użytkownika (dla weryfikacji)
 * @return bool - czy operacja się powiodła
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Sprawdza czy użytkownik ma wystarczającą ilość surowców.
 * 
 * @param array $available - dostępne surowce [wood, clay, iron]
 * @param array $required - wymagane surowce [wood, clay, iron]
 * @return bool - czy użytkownik ma wystarczającą ilość surowców
 */
function hasEnoughResources($available, $required) {
    return $available['wood'] >= $required['wood'] && 
           $available['clay'] >= $required['clay'] && 
           $available['iron'] >= $required['iron'];
}

/**
 * Oblicza czas pozostały do zakończenia budowy/rekrutacji w formacie tekstowym.
 * 
 * @param int $ends_at - timestamp zakończenia
 * @return string - sformatowany czas pozostały
 */
function getRemainingTimeText($ends_at) {
    $now = time();
    $remaining = $ends_at - $now;
    
    if ($remaining <= 0) {
        return 'Ukończono';
    }
    
    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    $seconds = $remaining % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

/**
 * Generuje odnośnik do profilu gracza.
 * 
 * @param int $user_id - ID użytkownika
 * @param string $username - nazwa użytkownika (opcjonalnie)
 * @return string - HTML z odnośnikiem do profilu
 */
function generatePlayerLink($user_id, $username = '') {
    if (empty($username)) {
        $username = 'Gracz #' . $user_id;
    }
    
    return '<a href="player.php?id=' . $user_id . '" class="player-link">' . htmlspecialchars($username) . '</a>';
}

/**
 * Generuje odnośnik do wioski.
 * 
 * @param int $village_id - ID wioski
 * @param string $village_name - nazwa wioski (opcjonalnie)
 * @param int $x - współrzędna X (opcjonalnie)
 * @param int $y - współrzędna Y (opcjonalnie)
 * @return string - HTML z odnośnikiem do wioski
 */
function generateVillageLink($village_id, $village_name = '', $x = null, $y = null) {
    $html = '<a href="game.php?village_id=' . $village_id . '" class="village-link">';
    
    if (!empty($village_name)) {
        $html .= htmlspecialchars($village_name);
    } else {
        $html .= 'Wioska #' . $village_id;
    }
    
    if ($x !== null && $y !== null) {
        $html .= ' <span class="coordinates">(' . $x . '|' . $y . ')</span>';
    }
    
    $html .= '</a>';
    return $html;
}

/**
 * Konwertuje czas trwania w sekundach na format czytelny dla człowieka.
 * 
 * @param int $seconds - czas w sekundach
 * @param bool $long_format - czy używać długiego formatu (np. "2 godziny 15 minut")
 * @return string - czytelny format czasu
 */
function formatDuration($seconds, $long_format = false) {
    if ($seconds < 60) {
        return $seconds . ($long_format ? " sekund" : "s");
    }
    
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    if ($minutes < 60) {
        if ($long_format) {
            $min_text = $minutes . " " . ($minutes == 1 ? "minuta" : ($minutes < 5 ? "minuty" : "minut"));
            if ($seconds > 0) {
                $min_text .= " " . $seconds . " " . ($seconds == 1 ? "sekunda" : ($seconds < 5 ? "sekundy" : "sekund"));
            }
            return $min_text;
        } else {
            return $minutes . "m " . $seconds . "s";
        }
    }
    
    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;
    
    if ($long_format) {
        $hour_text = $hours . " " . ($hours == 1 ? "godzina" : ($hours < 5 ? "godziny" : "godzin"));
        if ($minutes > 0) {
            $hour_text .= " " . $minutes . " " . ($minutes == 1 ? "minuta" : ($minutes < 5 ? "minuty" : "minut"));
        }
        return $hour_text;
    } else {
        return $hours . "h " . $minutes . "m";
    }
}

/**
 * Zwraca tekst akcji dla przycisku budynku w widoku wioski
 */
function getBuildingActionText($building_internal_name) {
    switch ($building_internal_name) {
        case 'main_building': return 'Przegląd wioski';
        case 'barracks': return 'Rekrutacja wojska';
        case 'stable': return 'Rekrutacja konnicy';
        case 'workshop': return 'Produkcja machin';
        case 'smithy': return 'Badania technologii';
        case 'academy': return 'Badania zaawansowane';
        case 'market': return 'Handel';
        case 'statue': return 'Posąg'; // Placeholder
        case 'church':
        case 'first_church': return 'Kościół'; // Placeholder
        case 'mint': return 'Odlewnia monety'; // Placeholder
        default: return 'Akcja'; // Domyślny tekst
    }
}
