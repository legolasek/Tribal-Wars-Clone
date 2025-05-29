<?php
require '../init.php';
require_once '../lib/managers/ResearchManager.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Brak dostępu</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Pobierz wioskę gracza
$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$village = $res->fetch_assoc();
$stmt->close();
if (!$village) {
    echo '<div class="error">Brak wioski</div>';
    exit;
}
$village_id = $village['id'];

$researchManager = new ResearchManager($conn);
$queue = $researchManager->getResearchQueue($village_id);

// Mapowanie ikon badań (przykład - używamy ikon jednostek tymczasowo)
$research_icons = [
    'spear_research' => 'unit_spear.png', // Przykład: badanie pikiniera
    'sword_research' => 'unit_sword.png', // Przykład: badanie miecznika
    // Dodaj mapowania dla innych badań, używając internal_name badań
];

if (empty($queue)) {
    echo '<div class="queue-empty">Brak badań w kolejce.</div>';
    exit;
}
echo '<div class="research-queue-list">'; // Zmieniono nazwę klasy, aby odróżnić od kolejki budowy/rekrutacji

foreach ($queue as $item) {
    $end_time = strtotime($item['ends_at']);
    $start_time = strtotime($item['starts_at']); // Zakładamy, że starts_at jest dostępne w danych kolejki
    $total_time = $end_time - $start_time;
    $remaining_time = $end_time - time();
    $progress_percent = ($total_time > 0) ? 100 - (($remaining_time / $total_time) * 100) : 0;
     // Użyj internal_name badania do pobrania ikony
    $icon_url = isset($research_icons[$item['research_internal_name']]) ? '../img/research/' . $research_icons[$item['research_internal_name']] : '';

    echo '<div class="queue-item research" data-starts-at="' . $start_time . '" data-duration="' . $total_time . '">';
    echo '  <div class="item-header">';
    echo '    <div class="item-title">';
    if ($icon_url) {
         // Ikona badania
        echo '      <img src="' . $icon_url . '" class="research-icon" alt="' . htmlspecialchars($item['research_name_pl']) . '">';
    }
    echo '      <span class="research-name">' . htmlspecialchars($item['research_name_pl']) . '</span>';
    echo '      <span class="research-level">(poziom ' . (int)$item['level_after'] . ')</span>';
    echo '    </div>';
    echo '    <div class="item-actions">';
    // Przycisk Anuluj - dodajemy data-id dla obsługi AJAX
    echo '      <button class="cancel-button research" data-id="' . $item['id'] . '">Anuluj</button>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="item-progress">';
    echo '    <div class="progress-bar">';
    // Używamy klasy progress-fill
    echo '      <div class="progress-fill" style="width: ' . max(0, min(100, $progress_percent)) . '%" data-ends-at="' . $end_time . '"></div>';
    echo '    </div>';
    echo '    <div class="progress-time">';
    // Używamy klasy time-remaining
    echo '      <span class="time-remaining" data-remaining="' . $remaining_time . '">' . formatTime($remaining_time) . '</span>'; // Zakładamy, że formatTime JS jest dostępne globalnie
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

echo '</div>'; 