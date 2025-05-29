<?php
require '../init.php';
validateCSRF();
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Zaktualizowana ścieżka
// BuildingManager is not needed directly here, VillageManager handles initial building creation
// require_once __DIR__ . '/lib/managers/BuildingManager.php';

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// $buildingManager = new BuildingManager($conn); // Not needed directly here

$message = '';

$villageManager = new VillageManager($conn); // Instantiate VillageManager

// Sprawdź, czy użytkownik już ma wioskę (na wszelki wypadek, gdyby tu trafił bezpośrednio)
// Użyj VillageManager do sprawdzenia
$existingVillage = $villageManager->getFirstVillage($user_id);

if ($existingVillage) {
    header("Location: ../game/game.php"); // Już ma wioskę, idź do gry
    exit();
}

// Remove manual user check - VillageManager methods implicitly check ownership/existence
/*
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
*/



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_village'])) {
    // Na razie tworzymy wioskę w losowym, wolnym miejscu na mapie i aktualnym świecie.
    // TODO: W przyszłości można dodać wybór kierunku lub konkretnego miejsca.
    
    // Deleguj tworzenie wioski do VillageManager
    $creationResult = $villageManager->createVillage($user_id);

    if ($creationResult['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($creationResult['message']) . ' Za chwilę zostaniesz przekierowany do gry...</p>';
        // Store new village ID in session if needed elsewhere
        if (isset($creationResult['village_id'])) {
             $_SESSION['village_id'] = $creationResult['village_id'];
        }
        header("Refresh: 3; url=../game/game.php"); // Przekieruj do gry po 3 sekundach
        // Nie wywołuj exit() od razu, aby wiadomość była widoczna
    } else {
        $message = '<p class="error-message">Wystąpił błąd podczas tworzenia wioski: ' . htmlspecialchars($creationResult['message']) . '</p>';
    }

    // Remove manual transaction and queries - handled by VillageManager::createVillage
    /*
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
        // ... logic for adding initial buildings ...

        $conn->commit();
        $message = '<p class="success-message">Wioska została pomyślnie utworzona! Za chwilę zostaniesz przekierowany do gry...</p>';
        header("Refresh: 3; url=../game/game.php"); // Przekieruj do gry po 3 sekundach
        // Nie wywołuj exit() od razu, aby wiadomość była widoczna
    } catch (Exception $e) {
        $conn->rollback();
        $message = '<p class="error-message">Wystąpił błąd podczas tworzenia wioski: ' . $e->getMessage() . '</p>';
    }
    */

}

// Wyświetl stronę utworzoną przy użyciu wspólnego szablonu
$pageTitle = 'Stwórz Wioskę';
require '../header.php';
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
<?php require '../footer.php'; ?>
<?php
// Remove manual DB connection close
// $database->closeConnection();
?> 