<?php
// Skrypt: probot.php
// Rozwija losowo budynki w wioskach barbarzyńskich (user_id = -1)
require_once 'config/config.php';
require_once 'lib/Database.php';
require_once 'lib/BuildingManager.php';

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// Pobierz wszystkie wioski barbarzyńskie
$sql = "SELECT * FROM villages WHERE user_id = -1";
$res = $conn->query($sql);
$barb_villages = [];
while ($row = $res->fetch_assoc()) {
    $barb_villages[] = $row;
}
$res->close();

if (empty($barb_villages)) {
    echo json_encode(['success'=>false,'msg'=>'Brak wiosek barbarzyńskich.']);
    exit;
}

// Lista budynków do rozwoju (możesz rozbudować)
$buildings = [
    'main', 'barracks', 'stable', 'garage', 'smithy', 'market',
    'wood', 'clay', 'iron', 'farm', 'storage', 'wall'
];

$bm = new BuildingManager($conn);
$developed = 0;
foreach ($barb_villages as $village) {
    // Losowy budynek
    $building = $buildings[array_rand($buildings)];
    $current_level = (int)$village[$building];
    $max_level = $bm->getMaxLevel($building);
    if ($current_level < $max_level && $bm->canUpgrade($village, $building)) {
        $bm->upgradeBuilding($village['id'], $building);
        $developed++;
        // Loguj akcję AI
        $stmt_log = $conn->prepare("INSERT INTO ai_logs (action, village_id, details) VALUES (?, ?, ?)");
        $action = 'upgrade_building';
        $details = json_encode([
            'building' => $building,
            'from_level' => $current_level,
            'to_level' => $current_level+1
        ]);
        $stmt_log->bind_param('sis', $action, $village['id'], $details);
        $stmt_log->execute();
        $stmt_log->close();
    }
}

$database->closeConnection();
echo json_encode(['success'=>true,'msg'=>'Rozwinięto '.$developed.' budynków w wioskach barbarzyńskich.']); 