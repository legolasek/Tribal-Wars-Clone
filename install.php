<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalator Tribal Wars Nowa Edycja</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Styles for the main installer container */
        .install-container {
            max-width: 800px;
            margin: 20px auto;
            padding: var(--spacing-lg);
            background-color: var(--beige-light);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-medium);
            box-shadow: var(--box-shadow-default);
            font-family: var(--font-main);
        }

        /* Styles for individual installation stages */
        .install-stage {
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-md);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-medium);
            background-color: var(--beige-medium);
            box-shadow: var(--box-shadow-inset);
        }

        .install-stage h3 {
            color: var(--brown-secondary);
            margin-top: 0;
            margin-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--beige-dark);
            padding-bottom: var(--spacing-xs);
            font-size: var(--font-size-medium);
        }

        /* Styles for lists within stages (e.g., list of tables, files) */
        .install-stage ul {
            list-style: none;
            padding: 0;
            margin: var(--spacing-sm) 0;
        }

        .install-stage li {
            padding: var(--spacing-xs) 0;
            border-bottom: 1px dashed var(--beige-darker);
            margin-bottom: var(--spacing-xs);
        }

        .install-stage li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        /* Styles for success messages */
        .success {
            color: var(--green-success);
            font-weight: bold;
        }

        /* Styles for error messages */
        .error {
            color: var(--red-error);
            font-weight: bold;
        }

        /* Style for clickable error message to show details */
        .error.clickable {
            cursor: pointer;
            text-decoration: underline;
        }

        .error.clickable:hover {
            color: var(--red-error-bg); /* Lighter red on hover */
        }

        /* Style for the text indicating show/hide details */
        .show-details {
            font-weight: normal;
            font-size: var(--font-size-small);
            margin-left: var(--spacing-xs);
        }

        /* Container for detailed SQL errors - hidden by default */
        .sql-errors {
            margin-top: var(--spacing-sm);
            padding: var(--spacing-sm);
            background-color: var(--red-error-bg);
            border: 1px dashed var(--red-error);
            border-radius: var(--border-radius-small);
            display: none; /* Hidden by default */
        }

        /* Styles for individual error details within the container */
        .error-detail {
            margin-bottom: var(--spacing-sm);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px dashed var(--red-error);
        }

        .error-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .error-detail strong {
            color: var(--red-error);
        }

        .error-detail pre {
            background-color: #fff;
            padding: var(--spacing-xs);
            border: 1px solid var(--red-error-bg);
            border-radius: var(--border-radius-small);
            overflow-x: auto;
            font-size: var(--font-size-small);
            margin-top: var(--spacing-xs);
        }

        /* Style for horizontal rule separators */
        hr {
            border: none;
            height: 1px;
            background-color: var(--beige-dark);
            margin: var(--spacing-lg) 0;
        }

        /* Styles for the admin form container */
        .form-container {
            max-width: 400px;
            margin: var(--spacing-xl) auto;
            background-color: var(--beige-medium);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-medium);
            box-shadow: var(--box-shadow-default);
            border: 1px solid var(--beige-dark);
        }

        .form-container label {
            display: block;
            margin-bottom: var(--spacing-xs);
            font-weight: bold;
            color: var(--brown-secondary);
        }

        .form-container input[type="text"],
        .form-container input[type="password"] {
            width: 100%;
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-small);
            background-color: var(--beige-light);
            color: #333;
        }

        .form-container button {
            width: 100%;
            /* Using general button styles from main.css */
        }

        /* Specific success message for admin creation */
        .admin-success-message {
             color: var(--green-success);
            background-color: var(--green-success-bg);
            border-left: 4px solid var(--green-success);
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-small);
            margin: var(--spacing-md) 0;
             box-shadow: var(--box-shadow-default);
             text-align: center;
        }

        .admin-success-message a {
            color: var(--green-success);
            text-decoration: underline;
             font-weight: bold;
        }

        /* Specific error message for admin creation */
         .admin-error-message {
            color: var(--red-error);
            background-color: var(--red-error-bg);
            border-left: 4px solid var(--red-error);
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-small);
            margin: var(--spacing-md) 0;
             box-shadow: var(--box-shadow-default);
             text-align: center;
        }


    </style>
</head>
<body>
<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">⚙️</span>
            <span class="game-name">Instalator</span>
        </div>
    </header>
    <main class="install-container">
<?php
// Obsługa instalacji: GET = wykonaj SQL i pokaż formularz admina, POST = stwórz admina
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once 'config/config.php';
    require_once 'lib/Database.php';

    // Włącz pełne raportowanie błędów
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Funkcja do wykonania zapytań SQL z pliku
    function executeSqlFile($conn, $filePath, &$errorMessages) {
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            $errorMessages[] = "Błąd: Nie można odczytać pliku SQL: " . htmlspecialchars($filePath);
            return false;
        }

        // Podziel zapytania na pojedyncze instrukcje
        $queries = explode(';', $sql);
        $success = true;
        $queryCount = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $queryCount++;
                try {
                    if ($conn->query($query) !== TRUE) {
                        $errorMsg = "&nbsp;&nbsp;<strong>Błąd w zapytaniu #$queryCount:</strong> " . $conn->error . "<pre>" . htmlspecialchars($query) . "</pre>";
                        $errorMessages[] = $errorMsg;
                        $success = false;
                    }
                } catch (mysqli_sql_exception $e) {
                    $errorMsg = "&nbsp;&nbsp;<strong>Wyjątek SQL w zapytaniu #$queryCount:</strong> " . $e->getMessage() . "<pre>" . htmlspecialchars($query) . "</pre>";
                    $errorMessages[] = $errorMsg;
                    $success = false;
                }
            }
        }
        
        return $success;
    }

    echo "<h2>Instalacja tabel bazy danych:</h2>";

    // --- Etap 1/4: Łączenie z bazą danych i tworzenie bazy ---
    echo "<div class='install-stage'>"; // Kontener dla etapu
    echo "<h3>Etap 1/4: Łączenie z bazą danych i tworzenie bazy...</h3>";
    $conn_no_db = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn_no_db->connect_error) {
        echo "<div class='error'>❌ Błąd połączenia z serwerem MySQL: " . $conn_no_db->connect_error . "</div>";
        echo "</div>"; // Zamknięcie kontenera etapu
        die(); // Przerwij instalację w przypadku błędu połączenia
    }

    // Utwórz bazę danych
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    if ($conn_no_db->query($sql_create_db) === TRUE) {
        echo "<div class='success'>✅ Baza danych '" . DB_NAME . "' utworzona pomyślnie lub już istnieje.</div>";
    } else {
        echo "<div class='error'>❌ Błąd podczas tworzenia bazy danych: " . $conn_no_db->error . "</div>";
    }

    $conn_no_db->close();
    echo "</div>"; // Zamknięcie kontenera etapu
    echo "<hr>"; // Separator etapów

    // Teraz połącz się z nowo utworzoną bazą danych
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();

    // --- Etap 2/4: Usuwanie istniejących tabel ---
    echo "<div class='install-stage'>"; // Kontener dla etapu
    echo "<h3>Etap 2/4: Usuwanie istniejących tabel...</h3>";
    echo "<ul>";
    try {
        // Wyłącz sprawdzanie kluczy obcych tymczasowo
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Lista tabel do usunięcia (w odwrotnej kolejności zależności)
        $tables_to_drop = [
            'ai_logs',
            'battle_report_units',
            'battle_reports',
            'attack_units',
            'attacks',
            'research_queue',
            'village_research',
            'research_types',
            'unit_queue',
            'village_units',
            'unit_types',
            'building_queue',
            'village_buildings',
            'building_types',
            'messages',
            'reports',
            'villages',
            'users',
            'worlds'
        ];
        
        foreach ($tables_to_drop as $table) {
            try {
                $conn->query("DROP TABLE IF EXISTS $table");
                echo "<li>Tabela <strong>$table</strong> została usunięta (jeśli istniała).</li>";
            } catch (Exception $e) {
                echo "<li><div class='error'>Błąd podczas usuwania tabeli $table: " . $e->getMessage() . "</div></li>";
            }
        }
        
        // Włącz sprawdzanie kluczy obcych z powrotem
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        echo "</ul>";
        echo "<div class='success'>✅ Zakończono usuwanie tabel.</div>";
    } catch (Exception $e) {
        echo "</ul>";
        echo "<div class='error'>❌ Błąd podczas usuwania tabel: " . $e->getMessage() . "</div>";
    }
    echo "</div>"; // Zamknięcie kontenera etapu 2
    echo "<hr>"; // Separator etapów

    // Wykonaj skrypty SQL aby utworzyć tabele
    echo "<div class='install-stage'>"; // Kontener dla etapu
    echo "<h3>Etap 3/4: Tworzenie tabel i dodawanie danych...</h3>";
    echo "<ul>";
    $sql_files = [
        'docs/sql/sql_create_users_table.sql',
        'docs/sql/sql_create_worlds_table.sql',
        'docs/sql/sql_create_villages_table.sql',
        'docs/sql/sql_create_buildings_tables.sql',
        'docs/sql/sql_create_building_queue_table.sql',
        'docs/sql/sql_create_unit_types.sql',
        'docs/sql/sql_create_units_table.sql',
        'docs/sql/sql_create_reports_table.sql',
        'docs/sql/sql_create_battle_tables.sql',
        'docs/sql/sql_create_research_tables.sql',
        'docs/sql/sql_create_messages_table.sql',
        'docs/sql/sql_create_trade_routes_table.sql',
        'docs/sql/sql_create_notifications_table.sql'
    ];
    
    foreach ($sql_files as $sql_file) {
        echo "<li>Wykonywanie pliku <strong>$sql_file</strong>: ";
        if (file_exists($sql_file)) {
            $errorMessages = [];
            $result = executeSqlFile($conn, $sql_file, $errorMessages);
            if ($result) {
                echo "<span class='success'>Pomyślnie.</span></li>";
            } else {
                echo "<span class='error clickable'>❌ Wystąpiły błędy. <span class='show-details'>(Pokaż szczegóły)</span></span></li>";
                echo "<div class='sql-errors'>"; // Kontener na błędy
                foreach ($errorMessages as $msg) {
                    echo "<div class='error-detail'>$msg</div>";
                }
                echo "</div>"; // Zamknięcie kontenera
            }
            
            // Po wykonaniu skryptu sql_create_buildings_tables.sql, dodaj brakującą kolumnę population_cost
            if ($sql_file === 'docs/sql/sql_create_buildings_tables.sql') {
                echo "<li>Dodawanie kolumny `population_cost` do tabeli `building_types`: ";
                $alter_sql = "ALTER IGNORE TABLE `building_types` ADD COLUMN `population_cost` INT(11) DEFAULT 0 COMMENT 'Zużycie populacji na poziom';";
                if ($conn->query($alter_sql) === TRUE) {
                    echo "<span class='success'>Pomyślnie lub kolumna już istniała.</span></li>";
                } else {
                    echo "<span class='error'>❌ Błąd: " . $conn->error . "</span></li>";
                }
            }

            // Po wykonaniu skryptu unit_types, sprawdź strukturę tabeli
            if ($sql_file === 'docs/sql/sql_create_unit_types.sql') {
                echo "<li>Sprawdzanie struktury tabeli `unit_types`: ";
                $describe_result = $conn->query("DESCRIBE `unit_types`");
                if ($describe_result) {
                    $fields = [];
                    while ($row = $describe_result->fetch_assoc()) {
                        $fields[] = $row['Field'];
                    }
                    $describe_result->free();
                    // Sprawdź czy kluczowe kolumny istnieją
                    if (in_array('internal_name', $fields) && in_array('wood_cost', $fields) && in_array('clay_cost', $fields) && in_array('iron_cost', $fields)) {
                         echo "<span class='success'>Struktura poprawna.</span></li>";
                    } else {
                         echo "<span class='error'>Brakuje kluczowych kolumn!</span></li>";
                    }
                } else {
                    echo "<span class='error'>Błąd podczas pobierania struktury tabeli: " . $conn->error . "</span></li>";
                }
            }
        } else {
            echo "<span class='error'>❌ Plik nie istnieje!</span></li>";
        }
    }
    echo "</ul>";
    echo "<div class='success'>✅ Zakończono tworzenie tabel i dodawanie danych.</div>";
    echo "</div>"; // Zamknięcie kontenera etapu 3
    echo "<hr>"; // Separator etapów

    // --- Etap 4/4: Tworzenie domyślnego świata i administratora ---
    echo "<div class='install-stage'>"; // Kontener dla etapu
    echo "<h3>Etap 4/4: Tworzenie domyślnego świata i administratora...</h3>";
    echo "<ul>"; // Lista dla etapu 4
    echo "<li>Tworzenie domyślnego świata: ";
    if ($conn->query("INSERT INTO worlds (name) VALUES ('Świat 1')") === TRUE) {
        echo "<span class='success'>Pomyślnie.</span></li>";
    } else {
        echo "<span class='error'>❌ Błąd: " . $conn->error . "</span></li>";
    }

    // Po instalacji, nie ma potrzeby czyszczenia tabel, ponieważ są one już świeżo utworzone
    $database->closeConnection();

    // Po instalacji tabel pokaż formularz tworzenia administratora
    echo "</ul>"; // Zamknięcie listy dla etapu 4
    echo "<div class='success'>✅ Instalacja bazy danych zakończona. Proszę utworzyć konto administratora poniżej:</div>";
    echo "</div>"; // Zamknięcie kontenera etapu 4
    echo '<form method="POST" class="form-container">';
    echo '<label for="admin_username">Nazwa administratora</label>';
    echo '<input type="text" id="admin_username" name="admin_username" required>';
    echo '<label for="admin_password">Hasło administratora</label>';
    echo '<input type="password" id="admin_password" name="admin_password" required>';
    echo '<label for="admin_password_confirm">Potwierdź hasło</label>';
    echo '<input type="password" id="admin_password_confirm" name="admin_password_confirm" required>';
    echo '<button type="submit">Utwórz administratora</button>';
    echo '</form>';
    exit();
}
// POST: tworzenie konta admina
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
    require_once 'config/config.php';
    require_once 'lib/Database.php';
    require_once 'lib/functions.php';
    require_once 'lib/managers/VillageManager.php';
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $db->getConnection();
    $username = trim($_POST['admin_username']);
    $password = $_POST['admin_password'];
    $confirm  = $_POST['admin_password_confirm'];
    $error = '';
    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'Wszystkie pola są wymagane.';
    } elseif ($password !== $confirm) {
        $error = 'Hasła nie pasują do siebie.';
    } elseif (!isValidUsername($username)) {
        $error = 'Nieprawidłowa nazwa użytkownika.';
    }
    if (!$error) {
        $email = $username . '@localhost';
        $hash = hashPassword($password);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sss', $username, $email, $hash);
        if ($stmt->execute()) {
            $admin_id = $stmt->insert_id;
            $stmt->close();
            $vm = new VillageManager($conn);
            $coords = generateRandomCoordinates($conn, 100);
            $vm->createVillage($admin_id, 'Wioska ' . $username, $coords['x'], $coords['y']);
            echo '<h2>Administrator utworzony pomyślnie!</h2>';
            echo '<p><a href="admin/admin_login.php">Zaloguj się do panelu administratora</a> | <a href="auth/login.php">Rozpocznij grę</a>.</p>';
        } else {
            $error = 'Błąd: ' . $stmt->error;
        }
    }
    if ($error) {
        echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
    }
    exit();
}
?>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const errorSpans = document.querySelectorAll('.error.clickable');

        errorSpans.forEach(span => {
            span.addEventListener('click', function() {
                // Znajdź element <li>, który jest rodzicem klikniętego spana
                const listItem = this.closest('li');
                if (!listItem) return;

                // Znajdź kontener z błędami wewnątrz tego elementu listy
                const sqlErrorsDiv = listItem.querySelector('.sql-errors');
                
                if (sqlErrorsDiv) {
                    // Przełącz widoczność kontenera z błędami
                    if (sqlErrorsDiv.style.display === 'none' || sqlErrorsDiv.style.display === '') {
                        sqlErrorsDiv.style.display = 'block';
                        this.querySelector('.show-details').textContent = '(Ukryj szczegóły)';
                    } else {
                        sqlErrorsDiv.style.display = 'none';
                        this.querySelector('.show-details').textContent = '(Pokaż szczegóły)';
                    }
                }
            });
        });

        // Ukryj wszystkie kontenery z błędami SQL na początku
        document.querySelectorAll('.sql-errors').forEach(div => {
            div.style.display = 'none';
        });

    });
</script>
</body>
</html>
