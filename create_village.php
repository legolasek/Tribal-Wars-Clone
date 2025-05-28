<?php
require 'init.php';
validateCSRF();
require_once 'lib/BuildingManager.php'; // Potrzebny do informacji o budynkach

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$buildingManager = new BuildingManager($conn);

$message = '';

// Sprawdź, czy użytkownik istnieje w bazie
$stmt_check_user = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$stmt_check_user->bind_param("i", $user_id);
$stmt_check_user->execute();
$stmt_check_user->store_result();
if ($stmt_check_user->num_rows === 0) {
    $stmt_check_user->close();
    $message = '<p class="error-message">Błąd: użytkownik nie istnieje. Wyloguj się i zaloguj ponownie.</p>';
    session_destroy();
    echo $message;
    exit();
}
$stmt_check_user->close();

// Sprawdź, czy użytkownik już ma wioskę (na wszelki wypadek, gdyby tu trafił bezpośrednio)
$stmt_check_village_exists = $conn->prepare("SELECT id FROM villages WHERE user_id = ? AND world_id = ? LIMIT 1");
$world_id = CURRENT_WORLD_ID; // Przypisanie stałej do zmiennej, aby przekazać przez referencję
$stmt_check_village_exists->bind_param("ii", $user_id, $world_id);
$stmt_check_village_exists->execute();
$stmt_check_village_exists->store_result();
if ($stmt_check_village_exists->num_rows > 0) {
    $stmt_check_village_exists->close();
    header("Location: game.php"); // Już ma wioskę, idź do gry
    exit();
}
$stmt_check_village_exists->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_village'])) {
    // Na razie tworzymy wioskę w losowym, wolnym miejscu na mapie i aktualnym świecie.
    // TODO: W przyszłości można dodać wybór kierunku lub konkretnego miejsca.
    $x_coord = rand(1, 500); // Przykładowy zakres mapy
    $y_coord = rand(1, 500);
    // TODO: Dodać pętlę sprawdzającą, czy współrzędne są wolne i generującą nowe, jeśli zajęte.

    $village_name = "Wioska gracza " . htmlspecialchars($username);
    $initial_wood = 500;
    $initial_clay = 500;
    $initial_iron = 500;
    $initial_warehouse_capacity = 1000; // Początkowa pojemność magazynu (związana z poziomem 1)
    $initial_population = 0; // Populacja będzie rosła z budynkami

    $conn->begin_transaction();

    try {
        // 1. Stwórz wioskę
        $stmt_create_village = $conn->prepare("INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_create_village->bind_param("iisiiiiiii", $user_id, $world_id, $village_name, $x_coord, $y_coord, $initial_wood, $initial_clay, $initial_iron, $initial_warehouse_capacity, $initial_population);
        $stmt_create_village->execute();
        $new_village_id = $stmt_create_village->insert_id;
        $stmt_create_village->close();

        if (!$new_village_id) {
            throw new Exception("Nie udało się utworzyć wioski.");
        }

        // 2. Dodaj początkowe budynki (Ratusz poziom 1)
        $main_building_info = $buildingManager->getBuildingInfo('main_building');
        if ($main_building_info) {
            $stmt_add_main_building = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 1)");
            $stmt_add_main_building->bind_param("ii", $new_village_id, $main_building_info['id']);
            $stmt_add_main_building->execute();
            $stmt_add_main_building->close();
        } else {
            throw new Exception("Nie znaleziono informacji o Ratuszu w konfiguracji budynków.");
        }
        
        // Dodajmy też Magazyn na poziomie 1, skoro ustawiamy initial_warehouse_capacity
        $warehouse_info = $buildingManager->getBuildingInfo('warehouse');
        if ($warehouse_info) {
            // Upewnijmy się, że pojemność magazynu jest zgodna z poziomem 1
            $initial_warehouse_capacity = $buildingManager->getWarehouseCapacityByLevel(1);
            $stmt_update_capacity = $conn->prepare("UPDATE villages SET warehouse_capacity = ? WHERE id = ?");
            $stmt_update_capacity->bind_param("ii", $initial_warehouse_capacity, $new_village_id);
            $stmt_update_capacity->execute();
            $stmt_update_capacity->close();

            $stmt_add_warehouse = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 1)");
            $stmt_add_warehouse->bind_param("ii", $new_village_id, $warehouse_info['id']);
            $stmt_add_warehouse->execute();
            $stmt_add_warehouse->close();
        } else {
             // Ostrzeżenie, ale nie przerywamy transakcji, magazyn może nie być krytyczny na start
            error_log("Nie znaleziono informacji o Magazynie w konfiguracji budynków przy tworzeniu wioski dla user_id: " . $user_id);
        }

        // TODO: Dodać pozostałe budynki produkcyjne na poziomie 0 lub 1, jeśli taka jest logika gry.
        // Na razie Ratusz wystarczy do działania podstawowego interfejsu.

        $conn->commit();
        $message = '<p class="success-message">Wioska została pomyślnie utworzona! Za chwilę zostaniesz przekierowany do gry...</p>';
        header("Refresh: 3; url=game.php"); // Przekieruj do gry po 3 sekundach
        // Nie wywołuj exit() od razu, aby wiadomość była widoczna
    } catch (Exception $e) {
        $conn->rollback();
        $message = '<p class="error-message">Wystąpił błąd podczas tworzenia wioski: ' . $e->getMessage() . '</p>';
    }

}

// Wyświetl stronę utworzoną przy użyciu wspólnego szablonu
$pageTitle = 'Stwórz Wioskę';
require 'header.php';
?>
<div class="container">
    <h1>Witaj, <?= htmlspecialchars($username) ?>!</h1>
    <?= $message ?>
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strpos($message, 'error-message') !== false): ?>
        <p>Wygląda na to, że nie masz jeszcze swojej wioski.</p>
        <p>Kliknij poniższy przycisk, aby założyć swoją pierwszą osadę i rozpocząć przygodę!</p>
        <form action="create_village.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="submit" name="create_village" value="Załóż moją pierwszą wioskę" class="btn btn-primary">
        </form>
    <?php endif; ?>
</div>
<?php require 'footer.php'; ?>
<?php
// Zamknij połączenie z bazą po wyświetleniu strony
$database->closeConnection();
?> 