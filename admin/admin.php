<?php
require '../init.php';
// --- LOGIKA: obsługa AJAX, POST, GET, usuwanie, edycja, szczegóły ---

// --- USTAWIENIA ADMINA ---
$admin_login = 'admin'; // Możesz zmienić na swój login
$admin_pass = 'admin'; // Możesz zmienić na swoje hasło (w produkcji: hash!)

// --- LOGOWANIE ADMINA ---
if (!isset($_SESSION['is_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'], $_POST['admin_pass'])) {
        if ($_POST['admin_login'] === $admin_login && $_POST['admin_pass'] === $admin_pass) {
            $_SESSION['is_admin'] = true;
            header('Location: admin.php');
            exit();
        } else {
            $error = 'Nieprawidłowy login lub hasło.';
        }
    }
    // Formularz logowania
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>Panel administratora - logowanie</title><link rel="stylesheet" href="../css/main.css"></head><body style="background:#f5e9d7;">';
    echo '<div style="max-width:400px;margin:80px auto;padding:30px;background:#fff;border-radius:10px;box-shadow:0 2px 8px #ccc;">';
    echo '<h2>Panel administratora</h2>';
    if (isset($error)) echo '<div style="color:#c0392b;margin-bottom:10px;">'.$error.'</div>';
    echo '<form method="post"><input type="text" name="admin_login" placeholder="Login" required style="width:100%;margin-bottom:10px;padding:8px;">';
    echo '<input type="password" name="admin_pass" placeholder="Hasło" required style="width:100%;margin-bottom:10px;padding:8px;">';
    echo '<button type="submit" style="width:100%;padding:10px;background:#8d5c2c;color:#fff;border:none;border-radius:5px;">Zaloguj się</button>';
    echo '</form></div></body></html>';
    exit();
}

// --- PANEL ADMINA ---
$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// Upewnij się, że istnieje użytkownik o id = -1 (Barbarzyńcy)
$conn->query("INSERT IGNORE INTO users (id, username, email, password, is_admin, is_banned, created_at) VALUES (-1, 'Barbarzyńcy', 'barbarians@localhost', '', 0, 0, NOW())");

// Akcje admina
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'ban_user') {
        $stmt = $conn->prepare('UPDATE users SET is_banned = 1 WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?tab=users'); exit();
    }
    if ($_GET['action'] === 'delete_village') {
        $stmt = $conn->prepare('DELETE FROM villages WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?tab=villages'); exit();
    }
}

// Pobierz graczy
$users = [];
$res = $conn->query('SELECT id, username, email, is_banned, created_at FROM users ORDER BY id');
while ($row = $res->fetch_assoc()) $users[] = $row;
$res->close();

// Pobierz wioski
$villages = [];
$res = $conn->query('SELECT v.id, v.name, v.x_coord, v.y_coord, v.wood, v.clay, v.iron, v.population, u.username as owner FROM villages v JOIN users u ON v.user_id = u.id ORDER BY v.id');
while ($row = $res->fetch_assoc()) $villages[] = $row;
$res->close();

$tab = $_GET['tab'] ?? 'users';

// --- obsługa usuwania wszystkich wiosek barbarzyńskich ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_all_barbarians') {
    $stmt = $conn->prepare('DELETE FROM villages WHERE user_id = -1');
    $stmt->execute();
    $stmt->close();
    header('Location: admin.php?tab=barbarians'); exit();
}
// --- obsługa dodawania nowych wiosek barbarzyńskich ---
if (isset($_POST['add_barb_villages']) && isset($_POST['barb_count'])) {
    $count = max(1, min(50, (int)$_POST['barb_count']));
    $map_size = 100;
    $used_coords = [];
    $res = $conn->query('SELECT x_coord, y_coord FROM villages');
    while ($row = $res->fetch_assoc()) {
        $used_coords[$row['x_coord'].'_'.$row['y_coord']] = true;
    }
    $res->close();
    $added = 0;
    for ($i=0; $i<$count; $i++) {
        do {
            $x = rand(0, $map_size-1);
            $y = rand(0, $map_size-1);
            $key = $x.'_'.$y;
        } while (isset($used_coords[$key]));
        $used_coords[$key] = true;
        $name = 'Barbarzyńska '.$x.'|'.$y;
        $stmt = $conn->prepare('INSERT INTO villages (name, user_id, x_coord, y_coord, wood, clay, iron, population) VALUES (?, -1, ?, ?, 500, 500, 500, 0)');
        $stmt->bind_param('sii', $name, $x, $y);
        if ($stmt->execute()) $added++;
        $stmt->close();
    }
    $barb_add_msg = 'Dodano '.$added.' nowych wiosek barbarzyńskich!';
    header('Location: admin.php?tab=barbarians'); exit();
}
// --- obsługa AJAX podglądu szczegółów wioski barbarzyńskiej ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'barb_details' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare('SELECT * FROM villages WHERE id = ? AND user_id = -1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $v = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$v) { echo '<div style="color:#c0392b;">Nie znaleziono wioski.</div>'; exit; }
    $buildings = ['main'=>'Ratusz','barracks'=>'Koszary','stable'=>'Stajnia','garage'=>'Warsztat','smithy'=>'Kuźnia','market'=>'Targowisko','wood'=>'Tartak','clay'=>'Cegielnia','iron'=>'Huta żelaza','farm'=>'Farma','storage'=>'Magazyn','wall'=>'Mur'];
    $units = ['spear'=>'Pikinier','sword'=>'Miecznik','axe'=>'Topornik','archer'=>'Łucznik','scout'=>'Zwiadowca','light'=>'Lekka kawaleria','heavy'=>'Ciężka kawaleria','ram'=>'Taran','catapult'=>'Katapulta'];
    echo '<div style="display:flex;gap:32px;align-items:flex-start;">';
    echo '<img src="img/ds_graphic/map/village_barb.png" alt="Barbarzyńska" style="width:64px;height:64px;box-shadow:0 2px 8px #ccc;border-radius:8px;">';
    echo '<div>';
    echo '<h3 style="margin-top:0;margin-bottom:10px;">'.$v['name'].' <span style="font-size:0.8em;color:#888;">('.$v['x_coord'].'|'.$v['y_coord'].')</span></h3>';
    echo '<form id="barb-edit-form" onsubmit="return saveBarbEdit(this);" style="margin-bottom:8px;">';
    echo '<input type="hidden" name="village_id" value="'.$v['id'].'">';
    echo '<b>Surowce:</b><br>';
    echo 'Drewno <input type="number" name="wood" value="'.$v['wood'].'" min="0" max="1000000" style="width:70px;"> ';
    echo 'Glina <input type="number" name="clay" value="'.$v['clay'].'" min="0" max="1000000" style="width:70px;"> ';
    echo 'Żelazo <input type="number" name="iron" value="'.$v['iron'].'" min="0" max="1000000" style="width:70px;"><br>';
    echo '<b>Populacja:</b> <input type="number" name="population" value="'.$v['population'].'" min="0" max="100000" style="width:90px;"><br><br>';
    echo '<b>Poziomy budynków:</b><br><table style="margin-top:4px;margin-bottom:8px;">';
    foreach ($buildings as $col=>$name) {
        $lvl = isset($v[$col]) ? (int)$v[$col] : 0;
        echo '<tr><td style="padding:2px 10px 2px 0;">'.$name.'</td><td style="padding:2px 0;"><input type="number" name="b_'.$col.'" value="'.$lvl.'" min="0" max="30" style="width:60px;"></td></tr>';
    }
    echo '</table>';
    echo '<b>Jednostki:</b><br><table style="margin-top:4px;">';
    foreach ($units as $col=>$name) {
        $cnt = isset($v[$col]) ? (int)$v[$col] : 0;
        echo '<tr><td style="padding:2px 10px 2px 0;">'.$name.'</td><td style="padding:2px 0;"><input type="number" name="u_'.$col.'" value="'.$cnt.'" min="0" max="100000" style="width:70px;"></td></tr>';
    }
    echo '</table>';
    echo '<button type="submit" style="margin-top:10px;padding:7px 18px;background:#2980b9;color:#fff;border:none;border-radius:5px;cursor:pointer;">Zapisz zmiany</button>';
    echo '<span id="barb-edit-msg" style="margin-left:16px;font-weight:bold;color:#27ae60;"></span>';
    echo '</form>';
    echo '</div></div>';
    echo '<script>function saveBarbEdit(form){const fd=new FormData(form);fetch(\'admin.php?ajax=save_barb_edit\',{method:\'POST\',body:fd}).then(r=>r.json()).then(d=>{document.getElementById(\'barb-edit-msg\').innerText=d.msg;if(d.success)setTimeout(()=>location.reload(),900);}).catch(()=>{document.getElementById(\'barb-edit-msg\').innerText=\'Błąd zapisu!\';});return false;}</script>';
    exit;
}
// --- obsługa AJAX zapisu edycji wioski barbarzyńskiej ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'save_barb_edit' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['village_id'] ?? 0);
    $stmt = $conn->prepare('SELECT * FROM villages WHERE id = ? AND user_id = -1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $v = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$v) { echo json_encode(['success'=>false,'msg'=>'Nie znaleziono wioski.']); exit; }
    $fields = ['wood','clay','iron','population'];
    $buildings = ['main','barracks','stable','garage','smithy','market','wood','clay','iron','farm','storage','wall'];
    $units = ['spear','sword','axe','archer','scout','light','heavy','ram','catapult'];
    $set = [];
    foreach ($fields as $f) {
        $val = max(0, min(1000000, (int)($_POST[$f] ?? 0)));
        $set[] = "$f=$val";
    }
    foreach ($buildings as $b) {
        $val = max(0, min(30, (int)($_POST['b_'.$b] ?? 0)));
        $set[] = "$b=$val";
    }
    foreach ($units as $u) {
        $val = max(0, min(100000, (int)($_POST['u_'.$u] ?? 0)));
        $set[] = "$u=$val";
    }
    $sql = 'UPDATE villages SET '.implode(',', $set).' WHERE id = '.$id.' AND user_id = -1';
    if ($conn->query($sql)) {
        echo json_encode(['success'=>true,'msg'=>'Zapisano zmiany!']);
    } else {
        echo json_encode(['success'=>false,'msg'=>'Błąd zapisu!']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel administratora</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { background: #f5e9d7; }
        .admin-container { max-width: 1100px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #ccc; padding: 30px; }
        .admin-tabs { display: flex; gap: 20px; margin-bottom: 30px; }
        .admin-tabs a { text-decoration: none; color: #8d5c2c; font-weight: bold; padding: 8px 18px; border-radius: 6px; background: #f5e9d7; transition: background .2s; }
        .admin-tabs a.active, .admin-tabs a:hover { background: #8d5c2c; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 10px 8px; border: 1px solid #e0c9a6; text-align: center; }
        th { background: #f5e9d7; }
        tr.banned { background: #ffeaea; }
        .admin-action-btn { padding: 4px 10px; border-radius: 4px; border: none; cursor: pointer; font-size: 0.95em; }
        .ban { background: #c0392b; color: #fff; }
        .delete { background: #7f8c8d; color: #fff; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Panel administratora</h1>
    <div class="admin-tabs">
        <a href="admin.php?tab=users" class="<?php if($tab==='users')echo'active'; ?>">Gracze</a>
        <a href="admin.php?tab=villages" class="<?php if($tab==='villages')echo'active'; ?>">Wioski</a>
        <a href="admin.php?tab=barbarians" class="<?php if($tab==='barbarians')echo'active'; ?>">Barbarzyńcy/ProBot</a>
    </div>
    <?php if ($tab==='users'): ?>
        <h2>Gracze</h2>
        <table>
            <tr><th>ID</th><th>Login</th><th>Email</th><th>Zarejestrowany</th><th>Status</th><th>Akcje</th></tr>
            <?php foreach ($users as $u): ?>
                <tr<?php if($u['is_banned'])echo' class="banned"'; ?>>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo $u['created_at']; ?></td>
                    <td><?php echo $u['is_banned'] ? '<span style="color:#c0392b;">Zbanowany</span>' : '<span style="color:#27ae60;">Aktywny</span>'; ?></td>
                    <td>
                        <?php if (!$u['is_banned']): ?>
                            <a href="admin.php?action=ban_user&id=<?php echo $u['id']; ?>" class="admin-action-btn ban" onclick="return confirm('Zbanować tego gracza?');">Banuj</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php elseif ($tab==='villages'): ?>
        <h2>Wioski</h2>
        <table>
            <tr><th>ID</th><th>Nazwa</th><th>Właściciel</th><th>Koordynaty</th><th>Drewno</th><th>Glina</th><th>Żelazo</th><th>Populacja</th><th>Akcje</th></tr>
            <?php foreach ($villages as $v): ?>
                <tr>
                    <td><?php echo $v['id']; ?></td>
                    <td><?php echo htmlspecialchars($v['name']); ?></td>
                    <td><?php echo htmlspecialchars($v['owner']); ?></td>
                    <td><?php echo $v['x_coord']; ?>|<?php echo $v['y_coord']; ?></td>
                    <td><?php echo $v['wood']; ?></td>
                    <td><?php echo $v['clay']; ?></td>
                    <td><?php echo $v['iron']; ?></td>
                    <td><?php echo $v['population']; ?></td>
                    <td>
                        <a href="village_view.php?id=<?php echo $v['id']; ?>" class="admin-action-btn">Podgląd</a>
                        <a href="admin.php?action=delete_village&id=<?php echo $v['id']; ?>" class="admin-action-btn delete" onclick="return confirm('Usunąć tę wioskę?');">Usuń</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php elseif ($tab==='barbarians'): ?>
        <h2>Wioski barbarzyńskie (AI)</h2>
        <div style="margin-bottom:18px;">
            <button onclick="runProBot()" style="padding:7px 18px;background:#2980b9;color:#fff;border:none;border-radius:5px;cursor:pointer;">Rozwiń losowo wioski barbarzyńskie (AI)</button>
            <button onclick="if(confirm('Usunąć wszystkie wioski barbarzyńskie?'))location.href='admin.php?action=delete_all_barbarians';" style="padding:7px 18px;background:#c0392b;color:#fff;border:none;border-radius:5px;cursor:pointer;margin-left:10px;">Usuń wszystkie wioski barbarzyńskie</button>
        </div>
        <div style="margin-bottom:24px; background:#f5e9d7; padding:18px; border-radius:8px; max-width:500px;">
            <form method="post" style="display:flex;align-items:center;gap:16px;" onsubmit="return confirm('Na pewno dodać nowe wioski barbarzyńskie?');">
                <label for="barb_count"><b>Dodaj wioski barbarzyńskie:</b></label>
                <input type="number" min="1" max="50" name="barb_count" id="barb_count" value="5" style="width:60px;padding:5px;">
                <button type="submit" name="add_barb_villages" style="padding:7px 18px;background:#27ae60;color:#fff;border:none;border-radius:5px;cursor:pointer;">Dodaj</button>
            </form>
            <div style="font-size:0.95em;color:#888;margin-top:6px;">Wioski pojawią się w losowych miejscach na mapie.</div>
            <?php if (isset($barb_add_msg)) echo '<div style="color:#2980b9;font-weight:bold;margin-top:8px;">'.$barb_add_msg.'</div>'; ?>
        </div>
        <div id="probot-result" style="margin-bottom:18px;color:#27ae60;font-weight:bold;"></div>
        <table>
            <tr><th>ID</th><th>Nazwa</th><th>Koordynaty</th><th>Drewno</th><th>Glina</th><th>Żelazo</th><th>Populacja</th><th>Grafika</th><th>Akcje</th></tr>
            <?php
            $barb_villages = [];
            $res = $conn->query('SELECT id, name, x_coord, y_coord, wood, clay, iron, population FROM villages WHERE user_id = -1 ORDER BY id');
            while ($row = $res->fetch_assoc()) $barb_villages[] = $row;
            $res->close();
            foreach ($barb_villages as $v): ?>
                <tr>
                    <td><?php echo $v['id']; ?></td>
                    <td><?php echo htmlspecialchars($v['name']); ?></td>
                    <td><?php echo $v['x_coord']; ?>|<?php echo $v['y_coord']; ?></td>
                    <td><?php echo $v['wood']; ?></td>
                    <td><?php echo $v['clay']; ?></td>
                    <td><?php echo $v['iron']; ?></td>
                    <td><?php echo $v['population']; ?></td>
                    <td><img src="img/ds_graphic/map/village_barb.png" alt="Barbarzyńska" style="width:32px;height:32px;"></td>
                    <td><button onclick="showBarbDetails(<?php echo $v['id']; ?>)" style="padding:4px 12px;background:#8d5c2c;color:#fff;border:none;border-radius:4px;cursor:pointer;">Szczegóły</button></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div id="barb-details-popup" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);z-index:9999;align-items:center;justify-content:center;">
            <div id="barb-details-content" style="background:#fff;padding:32px 36px 24px 36px;border-radius:12px;min-width:340px;max-width:95vw;box-shadow:0 4px 32px #0002;position:relative;">
                <button onclick="closeBarbDetails()" style="position:absolute;top:12px;right:12px;background:#c0392b;color:#fff;border:none;border-radius:50%;width:32px;height:32px;font-size:1.2em;cursor:pointer;">&times;</button>
                <div id="barb-details-body">Ładowanie...</div>
            </div>
        </div>
        <script>
        function runProBot() {
            const btns = document.querySelectorAll('button');
            btns.forEach(btn=>btn.disabled=true);
            fetch('probot.php')
                .then(r=>r.json())
                .then(d=>{
                    document.getElementById('probot-result').innerText = d.msg;
                    setTimeout(()=>location.reload(), 1200);
                })
                .catch(()=>{
                    document.getElementById('probot-result').innerText = 'Błąd działania AI!';
                })
                .finally(()=>btns.forEach(btn=>btn.disabled=false));
        }
        function showBarbDetails(id) {
            document.getElementById('barb-details-popup').style.display = 'flex';
            fetch('admin.php?ajax=barb_details&id='+id)
                .then(r=>r.text())
                .then(html=>{
                    document.getElementById('barb-details-body').innerHTML = html;
                });
        }
        function closeBarbDetails() {
            document.getElementById('barb-details-popup').style.display = 'none';
        }
        </script>
    <?php endif; ?>
</div>
</body>
</html> 