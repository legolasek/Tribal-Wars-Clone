<?php
require 'init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();
require_once 'lib/ResearchManager.php';

// Database connection provided by init.php context
$researchManager = new ResearchManager($conn);

// Weryfikacja zalogowania
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'error' => 'Nie jesteś zalogowany.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Sprawdź, czy otrzymano ID badania do anulowania
if (!isset($_POST['research_queue_id'])) {
    $response = [
        'success' => false,
        'error' => 'Brak ID badania do anulowania.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$research_queue_id = (int) $_POST['research_queue_id'];

// Sprawdź, czy badanie istnieje i należy do gracza
$stmt = $conn->prepare("
    SELECT rq.id, rq.village_id, v.user_id, rt.name_pl 
    FROM research_queue rq
    JOIN villages v ON rq.village_id = v.id
    JOIN research_types rt ON rq.research_type_id = rt.id
    WHERE rq.id = ?
");
$stmt->bind_param("i", $research_queue_id);
$stmt->execute();
$result = $stmt->get_result();
$queue_item = $result->fetch_assoc();
$stmt->close();

if (!$queue_item) {
    $response = [
        'success' => false,
        'error' => 'Nie znaleziono badania o podanym ID.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Sprawdź, czy badanie należy do gracza
if ($queue_item['user_id'] != $user_id) {
    $response = [
        'success' => false,
        'error' => 'Nie masz uprawnień do anulowania tego badania.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Anuluj badanie i zwróć część zasobów
$cancel_result = $researchManager->cancelResearch($research_queue_id, $user_id);

if ($cancel_result['success']) {
    // Pobierz zaktualizowane informacje o zasobach
    $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
    $stmt->bind_param("i", $queue_item['village_id']);
    $stmt->execute();
    $updated_resources = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Badanie "' . $queue_item['name_pl'] . '" zostało anulowane.',
        'refunded' => $cancel_result['refunded'],
        'updated_resources' => $updated_resources
    ];
} else {
    $response = [
        'success' => false,
        'error' => $cancel_result['error']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 