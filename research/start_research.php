<?php
require '../init.php';
validateCSRF();
require_once '../lib/managers/ResearchManager.php';

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

// Sprawdź, czy otrzymano wszystkie wymagane parametry
if (!isset($_POST['village_id']) || !isset($_POST['research_type_id'])) {
    $response = [
        'success' => false,
        'error' => 'Brak wymaganych parametrów.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$village_id = (int) $_POST['village_id'];
$research_type_id = (int) $_POST['research_type_id'];

// Sprawdź, czy wioska należy do gracza
$stmt = $conn->prepare("SELECT id, wood, clay, iron FROM villages WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $village_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$village = $result->fetch_assoc();
$stmt->close();

if (!$village) {
    $response = [
        'success' => false,
        'error' => 'Nie znaleziono wioski lub nie masz do niej dostępu.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Pobierz informacje o typie badania
$researchManager = new ResearchManager($conn);
$research_type = $researchManager->getResearchTypeById($research_type_id);
if (!$research_type) {
    $response = [
        'success' => false,
        'error' => 'Nieprawidłowy typ badania.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Pobierz aktualny poziom badania (jeśli istnieje)
$current_level = 0;
$stmt = $conn->prepare("
    SELECT level FROM village_research 
    WHERE village_id = ? AND research_type_id = ?
");
$stmt->bind_param("ii", $village_id, $research_type_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $current_level = (int) $row['level'];
}
$stmt->close();

$target_level = $current_level + 1;

// Sprawdź, czy już istnieje takie badanie w kolejce
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM research_queue 
    WHERE village_id = ? AND research_type_id = ?
");
$stmt->bind_param("ii", $village_id, $research_type_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] > 0) {
    $response = [
        'success' => false,
        'error' => 'Badanie tego typu jest już w trakcie realizacji.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Sprawdź, czy spełniono wymagania do przeprowadzenia badania
$requirements_check = $researchManager->checkResearchRequirements($research_type_id, $village_id, $target_level);

if (!$requirements_check['can_research']) {
    $response = [
        'success' => false,
        'error' => 'Nie spełniono wymagań do przeprowadzenia badania.',
        'details' => $requirements_check
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Pobierz koszt badania
$research_cost = $researchManager->getResearchCost($research_type_id, $target_level);

if (!$research_cost) {
    $response = [
        'success' => false,
        'error' => 'Nie można obliczyć kosztu badania.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Sprawdź, czy gracz ma wystarczające zasoby
if ($village['wood'] < $research_cost['wood'] || 
    $village['clay'] < $research_cost['clay'] || 
    $village['iron'] < $research_cost['iron']) {
    $response = [
        'success' => false,
        'error' => 'Niewystarczające zasoby do przeprowadzenia badania.',
        'required' => $research_cost,
        'available' => [
            'wood' => $village['wood'],
            'clay' => $village['clay'],
            'iron' => $village['iron']
        ]
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Rozpocznij badanie
$resources = [
    'wood' => $village['wood'],
    'clay' => $village['clay'],
    'iron' => $village['iron']
];

$result = $researchManager->startResearch($village_id, $research_type_id, $target_level, $resources);

if ($result['success']) {
    // Pobierz zaktualizowane informacje o zasobach
    $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $updated_resources = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Badanie rozpoczęte pomyślnie.',
        'research_id' => $result['research_id'],
        'research_name' => $research_type['name_pl'],
        'level_after' => $target_level,
        'ends_at' => $result['ends_at'],
        'updated_resources' => $updated_resources
    ];
} else {
    $response = [
        'success' => false,
        'error' => $result['error']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 