<?php
require '../init.php';
// Walidacja CSRF dla żądań POST jest wykonywana automatycznie w validateCSRF() z functions.php

$message = '';

// Funkcja do generowania unikalnych współrzędnych wioski - Używa Prepared Statement
function findUniqueCoordinates($conn, $max_coord = 100) {
    $attempts = 0;
    $max_attempts = 1000; // Zapobieganie nieskończonej pętli

    do {
        $x = rand(1, $max_coord);
        $y = rand(1, $max_coord);

        $stmt_check = $conn->prepare("SELECT id FROM villages WHERE x_coord = ? AND y_coord = ?");
        // Sprawdzenie, czy prepare się powiodło
        if ($stmt_check === false) {
            error_log("Database prepare failed: " . $conn->error);
            return false; // Błąd bazy danych
        }
        $stmt_check->bind_param("ii", $x, $y);
        $stmt_check->execute();
        $stmt_check->store_result();
        $is_taken = $stmt_check->num_rows > 0;
        $stmt_check->close();
        $attempts++;
    } while ($is_taken && $attempts < $max_attempts);

    if ($is_taken) {
        // Nie udało się znaleźć unikalnych współrzędnych po wielu próbach - logowanie błędu
        error_log("Failed to find unique coordinates after {$max_attempts} attempts.");
        return false;
    }
    return ['x' => $x, 'y' => $y];
}

// --- PRZETWARZANIE DANYCH (REJESTRACJA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../lib/managers/VillageManager.php';
    // validateCSRF(); // Usunięto stąd, bo jest w validateCSRF() wywoływanym globalnie dla POST w init.php (jeśli logika init.php została odpowiednio zmieniona)

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Walidacja danych wejściowych (bardziej szczegółowa)
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = '<p class="error-message">Wszystkie pola są wymagane!</p>';
    } elseif ($password !== $confirm_password) {
        $message = '<p class="error-message">Hasła nie pasują do siebie!</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p class="error-message">Nieprawidłowy format adresu e-mail!</p>';
    } elseif (!isValidUsername($username)) { // Dodana walidacja nazwy użytkownika
         $message = '<p class="error-message">Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślenia (3-20 znaków).</p>';
    } else {
        // Sprawdź, czy użytkownik lub e-mail już istnieje (Prepared Statement)
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
         if ($stmt === false) {
            error_log("Database prepare failed: " . $conn->error);
            $message = '<p class="error-message">Wystąpił błąd bazy danych.</p>';
         } else {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $message = '<p class="error-message">Nazwa użytkownika lub e-mail jest już zajęty!</p>';
            } else {
                $stmt->close(); // Zamknij stmt_check_user_exists przed nowymi

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $conn->begin_transaction(); // Rozpocznij transakcję

                try {
                    // 1. Dodaj użytkownika (Prepared Statement)
                    $stmt_user = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    if ($stmt_user === false) throw new Exception("Database prepare failed for user insert: " . $conn->error);
                    $stmt_user->bind_param("sss", $username, $email, $hashed_password);

                    if (!$stmt_user->execute()) {
                         throw new Exception("Błąd wykonania zapytania dodania użytkownika: " . $stmt_user->error);
                    }
                    $user_id = $stmt_user->insert_id; // Pobierz ID nowo utworzonego użytkownika
                    $stmt_user->close(); 

                    // 2. Znajdź unikalne koordynaty i utwórz wioskę (Prepared Statement wewnątrz funkcji findUniqueCoordinates i tutaj)
                    $coordinates = findUniqueCoordinates($conn);
                    if ($coordinates === false) {
                         throw new Exception("Nie udało się znaleźć unikalnych koordynatów dla wioski.");
                    }
                    
                    $village_name = "Wioska " . htmlspecialchars($username); // Sanityzacja nazwy wioski
                    // Użycie stałych z config.php dla początkowych wartości
                    $initial_wood = INITIAL_WOOD;
                    $initial_clay = INITIAL_CLAY;
                    $initial_iron = INITIAL_IRON;
                    $initial_warehouse_capacity = INITIAL_WAREHOUSE_CAPACITY;
                    $initial_population = INITIAL_POPULATION;
                    $stmt_village = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                     if ($stmt_village === false) throw new Exception("Database prepare failed for village insert: " . $conn->error);
                    $stmt_village->bind_param(
                        "isiiiiiii",
                        $user_id,
                        $village_name,
                        $coordinates['x'],
                        $coordinates['y'],
                        $initial_wood,
                        $initial_clay,
                        $initial_iron,
                        $initial_warehouse_capacity,
                        $initial_population
                    );

                    if (!$stmt_village->execute()) {
                         throw new Exception("Błąd wykonania zapytania dodania wioski: " . $stmt_village->error);
                    }
                    $village_id = $stmt_village->insert_id; // Pobierz ID nowo utworzonej wioski
                    $stmt_village->close(); 

                    // 3. Dodaj podstawowe budynki (Prepared Statements)
                    $initial_buildings = [
                         'main', 'sawmill', 'clay_pit', 'iron_mine', 'warehouse', 'farm' // Dodano farmę
                    ];
                    $base_level = 1;

                    foreach ($initial_buildings as $internal_name) {
                        $stmt_get_building_type = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
                        if ($stmt_get_building_type === false) throw new Exception("Database prepare failed for building type select: " . $conn->error);
                        $stmt_get_building_type->bind_param("s", $internal_name);
                        $stmt_get_building_type->execute();
                        $result_building_type = $stmt_get_building_type->get_result();
                        $building_type = $result_building_type->fetch_assoc();
                        $stmt_get_building_type->close();

                        if (!$building_type) {
                            throw new Exception("Building type {$internal_name} not found for initial setup.");
                        }
                        
                        $building_type_id = $building_type['id'];
                        $stmt_add_building = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
                         if ($stmt_add_building === false) throw new Exception("Database prepare failed for village building insert: " . $conn->error);
                        $stmt_add_building->bind_param("iii", $village_id, $building_type_id, $base_level);
                        if (!$stmt_add_building->execute()) {
                            throw new Exception("Failed to add building {$internal_name} for village {$village_id}: " . $stmt_add_building->error);
                        }
                        $stmt_add_building->close();
                    }
                    
                    // 4. Zaktualizuj populację wioski po dodaniu budynków
                    $villageManager = new VillageManager($conn);
                    $villageManager->updateVillagePopulation($village_id); // Wywołaj metodę do przeliczenia populacji

                    $conn->commit(); // Zatwierdź transakcję jeśli wszystko poszło dobrze
                    $message = '<p class="success-message">Rejestracja zakończona sukcesem! Twoja pierwsza wioska została założona z podstawowymi budynkami. Możesz się teraz <a href="login.php">zalogować</a>.</p>';

                } catch (Exception $e) {
                    $conn->rollback(); // Wycofaj transakcję w przypadku błędu
                    error_log("Registration failed for user {$username}: " . $e->getMessage());
                    $message = '<p class="error-message">Wystąpił błąd podczas rejestracji lub zakładania wioski. Spróbuj ponownie lub skontaktuj się z administratorem.</p>';
                    // Dodatkowo, jeśli użytkownik został dodany, ale wioska nie, można go usunąć tutaj
                    if (isset($user_id)) {
                         $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                         if ($stmt_delete_user) {
                            $stmt_delete_user->bind_param("i", $user_id);
                            $stmt_delete_user->execute();
                            $stmt_delete_user->close();
                         }
                    }
                     // Zamknij wszystkie otwarte statementy w przypadku błędu w trakcie transakcji
                     if (isset($stmt_user) && $stmt_user) $stmt_user->close();
                     if (isset($stmt_village) && $stmt_village) $stmt_village->close();
                     if (isset($stmt_get_building_type) && $stmt_get_building_type) $stmt_get_building_type->close();
                     if (isset($stmt_add_building) && $stmt_add_building) $stmt_add_building->close();
                }
            }
             // Nie zamykaj stmt check user/email exists tutaj, tylko po użyciu lub w catch
        }
         if (isset($stmt) && $stmt) $stmt->close();
    }
}

// --- PREZENTACJA (HTML) ---
$pageTitle = 'Rejestracja';
require '../header.php';
?>
<main>
    <div class="form-container">
        <h1>Rejestracja</h1>
        <?= $message ?>
        <form action="register.php" method="POST">
            <?php if (isset($_SESSION['csrf_token'])): ?>
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php endif; ?>
            
            <label for="username">Nazwa użytkownika</label>
            <input type="text" id="username" name="username" required>

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Potwierdź hasło</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <input type="submit" value="Zarejestruj" class="btn btn-primary">
        </form>
        <p class="mt-2">Masz już konto? <a href="login.php">Zaloguj się</a>.</p>
        <p><a href="../index.php">Wróć do strony głównej</a>.</p>
    </div>
</main>
<?php
require '../footer.php';
// Zamknij połączenie z bazą po renderowaniu strony
if (isset($database)) {
    $database->closeConnection();
}
?>
