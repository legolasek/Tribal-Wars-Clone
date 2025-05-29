<?php
require_once __DIR__ . '/init.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/lib/functions.php';
// If user is logged in, prepare resource display
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/lib/managers/VillageManager.php';
    require_once __DIR__ . '/lib/managers/ResourceManager.php'; // Potrzebujemy ResourceManager do produkcji
    require_once __DIR__ . '/lib/managers/BuildingManager.php'; // Poprawiona ścieżka
    require_once __DIR__ . '/lib/managers/BuildingConfigManager.php'; // Ta ścieżka jest poprawna
    require_once __DIR__ . '/lib/managers/NotificationManager.php'; // Do powiadomień

    $vm = new VillageManager($conn);
    
    // Utworzenie instancji BuildingConfigManager i BuildingManager
    $bcm = new BuildingConfigManager($conn);
    $bm = new BuildingManager($conn, $bcm); // BuildingManager potrzebuje połączenia i BuildingConfigManager

    // Utworzenie instancji ResourceManager
    $rm = new ResourceManager($conn, $bm); // ResourceManager potrzebuje połączenia i BuildingManager

    // Ensure resources are up to date
    $firstVidData = $vm->getFirstVillage($_SESSION['user_id']);
    if ($firstVidData) {
        $village_id = $firstVidData['id'];
        $vm->updateResources($village_id); // Aktualizuje surowce w bazie
        
        // Pobierz aktualne dane wioski PO aktualizacji surowców
        $currentRes = $vm->getVillageInfo($village_id); // Pobiera podstawowe info

        // Pobierz godzinową produkcję surowców i dodaj do $currentRes
        if ($currentRes) {
            $productionRates = $rm->getProductionRates($village_id); // Użyj ResourceManager::getProductionRates
            $currentRes['wood_production_per_hour'] = $productionRates['wood'] ?? 0;
            $currentRes['clay_production_per_hour'] = $productionRates['clay'] ?? 0;
            $currentRes['iron_production_per_hour'] = $productionRates['iron'] ?? 0;
        }

    } else {
        // User logged in but has no village - should not happen if registration works, 
        // but handle defensively
        // Maybe redirect to create_village.php if not already there?
         $currentRes = null;
         $firstVidData = null;
    }

    // Pobierz nieprzeczytane powiadomienia dla użytkownika
    $unread_notifications = [];
    if (isset($_SESSION['user_id'])) {
        // Upewnij się, że Autoloader działa i klasa NotificationManager jest dostępna
        $notificationManager = new NotificationManager($conn);
        $unread_notifications = $notificationManager->getNotifications($_SESSION['user_id'], true, 5);
    }
    $unread_count = count($unread_notifications);

}
// Ensure CSRF token is available
getCSRFToken();

if (!isset($pageTitle)) {
    $pageTitle = 'Tribal Wars';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="/css/main.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
    <!-- Skrypty JS -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
        // Ustaw globalną zmienną JavaScript z ID wioski
        window.currentVillageId = <?= json_encode($firstVidData['id'] ?? null) ?>;
    </script>
    <?php endif; ?>

    <?php
    // Pobierz komunikaty z sesji i przekaż do JavaScript
    $gameMessages = [];
    if (isset($_SESSION['game_messages'])) {
        $gameMessages = $_SESSION['game_messages'];
        unset($_SESSION['game_messages']); // Usuń komunikaty po pobraniu
    }
    ?>
    <script>
        window.gameMessages = <?= json_encode($gameMessages) ?>;
    </script>
    <script src="/js/resources.js" defer></script>
    <script src="/js/notifications.js" defer></script>
    <script src="/js/buildings.js"></script>
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    
    <header class="site-header">
        <div class="logo">
            <h1>Tribal Wars</h1>
        </div>
        <nav class="main-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Nawigacja dla zalogowanego użytkownika -->
                <a href="/game/game.php" class="<?= $current_page === 'game.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Przegląd</a>
                <a href="/map/map.php" class="<?= $current_page === 'map.php' ? 'active' : '' ?>"><i class="fas fa-map"></i> Mapa</a>
                <a href="/messages/reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fas fa-scroll"></i> Raporty</a>
                <a href="/messages/messages.php" class="<?= $current_page === 'messages.php' ? 'active' : '' ?>"><i class="fas fa-envelope"></i> Wiadomości</a>
                <a href="/player/ranking.php" class="<?= $current_page === 'ranking.php' ? 'active' : '' ?>"><i class="fas fa-trophy"></i> Ranking</a>
                <a href="/player/settings.php" class="<?= $current_page === 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Ustawienia</a>
                <a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Wyloguj</a>
                
                <div class="notifications-icon">
                    <a href="#" id="notifications-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge" id="notification-count"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                    <div id="notifications-dropdown" class="dropdown-content">
                        <h3>Powiadomienia</h3>
                        <div id="notifications-list">
                        <?php if (empty($unread_notifications)): ?>
                            <div class="no-notifications">Brak nowych powiadomień</div>
                        <?php else: ?>
                            <ul class="notifications-list-items">
                                <?php foreach ($unread_notifications as $notification): ?>
                                    <li class="notification-item notification-<?= htmlspecialchars($notification['type']) ?>" data-id="<?= $notification['id'] ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-<?= $notification['type'] === 'success' ? 'check-circle' : ($notification['type'] === 'error' ? 'exclamation-circle' : ($notification['type'] === 'info' ? 'info-circle' : 'bell')) ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                            <div class="notification-time"><?= relativeTime(strtotime($notification['created_at'])) ?></div>
                                        </div>
                                        <button class="mark-read-btn" data-id="<?= $notification['id'] ?>" title="Oznacz jako przeczytane">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                        <div class="notifications-footer">
                            <a href="#" id="mark-all-read">Oznacz wszystkie jako przeczytane</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Nawigacja dla niezalogowanego użytkownika (strona główna) -->
                <a href="/index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Strona główna</a>
                <a href="/auth/register.php" class="<?= $current_page === 'register.php' ? 'active' : '' ?>">Rejestracja</a>
                <a href="/auth/login.php" class="<?= $current_page === 'login.php' ? 'active' : '' ?>">Logowanie</a>
            <?php endif; ?>
        </nav>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="header-world">
                Świat: <?= htmlspecialchars(getCurrentWorldName($conn)) ?>
            </div>
        <?php endif; ?>
    </header>
    
    <?php if (isset($_SESSION['user_id']) && !empty($currentRes)): ?>
        <div id="resource-bar" class="resource-bar" data-village-id="<?= $firstVidData['id'] ?>">
            <ul>
                <li class="resource-wood">
                    <?= displayResource('wood', $currentRes['wood'], true, $currentRes['warehouse_capacity']) ?>
                    <span class="resource-production" id="prod-wood">+<?= formatNumber($currentRes['wood_production_per_hour']) ?>/h</span>
                </li>
                <li class="resource-clay">
                    <?= displayResource('clay', $currentRes['clay'], true, $currentRes['warehouse_capacity']) ?>
                     <span class="resource-production" id="prod-clay">+<?= formatNumber($currentRes['clay_production_per_hour']) ?>/h</span>
                </li>
                <li class="resource-iron">
                    <?= displayResource('iron', $currentRes['iron'], true, $currentRes['warehouse_capacity']) ?>
                     <span class="resource-production" id="prod-iron">+<?= formatNumber($currentRes['iron_production_per_hour']) ?>/h</span>
                </li>
                <li class="resource-population">
                    <?= displayResource('population', $currentRes['population'], true, $currentRes['farm_capacity']) ?>
                </li>
            </ul>
        </div>
    <?php endif; ?>
