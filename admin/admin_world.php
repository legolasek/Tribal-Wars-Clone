<?php
require_once '../lib/VillageManager.php';
// admin_world.php - Generator świata dla administratora
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $map_size = isset($_POST['map_size']) ? (int)$_POST['map_size'] : 100;
    // Wyczyść istniejące dane świata
    $tables = ['building_queue','unit_queue','research_queue','trade_routes','villages','village_buildings'];
    foreach ($tables as $table) {
        $conn->query("DELETE FROM $table");
    }
    // Pobierz wszystkich użytkowników
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE is_admin = 0");
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // Generuj wioski dla każdego użytkownika
    $vm = new VillageManager($conn);
    foreach ($users as $user) {
        // Szukaj unikalnych koordynatów
        do {
            $x = rand(0, $map_size - 1);
            $y = rand(0, $map_size - 1);
            $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE x_coord = ? AND y_coord = ?");
            $check->bind_param('ii', $x, $y);
            $check->execute();
            $cnt = $check->get_result()->fetch_assoc()['cnt'];
            $check->close();
        } while ($cnt > 0);
        // Tworzenie wioski
        $vm->createVillage($user['id'], 'Wioska '.$user['username'], $x, $y);
    }
    echo '<p class="success-message">Świat wygenerowany pomyślnie!</p>';
}
?>
<form method="POST" action="admin.php?screen=world" class="form-container">
    <h2>Generator Świata</h2>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label for="map_size">Rozmiar mapy (przykładowe X i Y):</label>
    <input type="number" id="map_size" name="map_size" value="100" min="10" max="500">
    <button type="submit" class="btn btn-primary">Generuj Świat</button>
</form> 