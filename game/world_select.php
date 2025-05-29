<?php
require '../init.php';

// Sprawdź czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'game.php';
$error = '';

// Obsługa wyboru świata
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Funkcja validateCSRF() już sprawdza token CSRF w żądaniach POST
    // Sprawdzanie wykonywane jest automatycznie dla każdego żądania POST w funkcji validateCSRF()
    $world_id = isset($_POST['world_id']) ? (int)$_POST['world_id'] : 0;

    // Sprawdź czy świat istnieje
    $stmt = $conn->prepare("SELECT id FROM worlds WHERE id = ?");
    $stmt->bind_param("i", $world_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = 'Wybrany świat nie istnieje.';
    } else {
        // Ustaw ID świata w sesji
        $_SESSION['world_id'] = $world_id;
        
        // AUTOMATYCZNE TWORZENIE WIOSKI, jeśli nie istnieje na tym świecie
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? AND world_id = ? LIMIT 1");
        $stmt->bind_param("ii", $user_id, $world_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            require_once 'lib/VillageManager.php';
            $villageManager = new VillageManager($conn);
            // Losowe koordynaty w centrum mapy (możesz zmienić zakres)
            $x = rand(40, 60);
            $y = rand(40, 60);
            $villageManager->createVillage($user_id, 'Wioska gracza', $x, $y);
        }
        $stmt->close();
        // Przekieruj do właściwej strony
        $final_redirect_url = $redirect;
        if (substr($final_redirect_url, 0, 1) !== '/' && !preg_match('/^[a-zA-Z]+:\/\//', $final_redirect_url)) {
             $final_redirect_url = '/' . $final_redirect_url;
        }
        header('Location: ' . $final_redirect_url);
        exit();
    }
}

// Pobierz listę światów
$stmt = $conn->prepare("SELECT id, name, created_at FROM worlds ORDER BY id DESC");
$stmt->execute();
$worlds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Wybór Świata';
require '../header.php';
?>

<div class="main-container">
    <div class="world-select-container">
        <h1>Wybierz Świat</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($worlds)): ?>
            <p>Brak dostępnych światów. Skontaktuj się z administratorem.</p>
        <?php else: ?>
            <form method="POST" action="world_select.php?redirect=<?= urlencode($_GET['redirect'] ?? 'game.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="worlds-list">
                    <?php foreach ($worlds as $world): ?>
                        <div class="world-item">
                            <input type="radio" name="world_id" id="world_<?= $world['id'] ?>" value="<?= $world['id'] ?>" <?= isset($_SESSION['world_id']) && $_SESSION['world_id'] == $world['id'] ? 'checked' : '' ?>>
                            <label for="world_<?= $world['id'] ?>">
                                <strong><?= htmlspecialchars($world['name']) ?></strong>
                                <span class="world-date">Utworzony: <?= date('d.m.Y', strtotime($world['created_at'])) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Graj</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
    .main-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh; /* Increase height to center vertically */
        padding: var(--spacing-xl) var(--spacing-lg); /* Add some padding */
    }
    .world-select-container {
        background-color: var(--beige-light);
        border-radius: var(--border-radius-large); /* Larger radius */
        padding: var(--spacing-xl); /* More padding */
        box-shadow: var(--box-shadow-hover); /* Stronger shadow */
        border: 1px solid var(--beige-darker); /* Darker border */
        max-width: 500px;
        width: 100%;
        text-align: center; /* Center content */
    }
    .world-select-container h1 {
        text-align: center;
        color: var(--brown-primary); /* Use primary brown */
        margin-bottom: var(--spacing-lg); /* More space below heading */
        font-size: var(--font-size-xlarge); /* Larger font */
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
    }
    .worlds-list {
        margin: var(--spacing-lg) 0;
        text-align: left; /* Align list items to the left */
    }
    .world-item {
        background-color: var(--beige-medium);
        padding: var(--spacing-md); /* More padding */
        border-radius: var(--border-radius-small);
        margin-bottom: var(--spacing-sm); /* Less space between items */
        cursor: pointer;
        transition: background-color var(--transition-fast), box-shadow var(--transition-fast);
        display: flex;
        align-items: center;
        border: 1px solid var(--beige-dark);
    }
    .world-item:hover {
        background-color: var(--beige-dark);
        box-shadow: var(--box-shadow-inset); /* Indicate selection readiness */
    }
    .world-item input[type="radio"] {
        margin-right: var(--spacing-md); /* More space */
        transform: scale(1.3); /* Slightly larger radio button */
        accent-color: var(--brown-primary); /* Change radio button color */
    }
    .world-item label {
        display: flex;
        flex-direction: column;
        cursor: pointer;
        flex-grow: 1;
        color: var(--brown-dark); /* Darker text color */
        font-size: var(--font-size-normal); /* Ensure normal size */
    }
    .world-item label strong {
        color: var(--brown-secondary); /* Highlight world name */
        font-size: var(--font-size-large); /* Larger world name */
        margin-bottom: var(--spacing-xs); /* Space below name */
    }
    .world-date {
        font-size: var(--font-size-small); /* Smaller date font */
        color: #666; /* Muted color for date */
        margin-top: 0;
    }
    .form-actions {
        display: flex;
        justify-content: center;
        margin-top: var(--spacing-lg); /* More space above button */
    }
    
    /* Update btn-primary class usage */
    .form-actions .btn.btn-primary {
        background-color: var(--brown-primary);
        color: white;
        border: none;
        padding: 12px 40px; /* Adjust padding */
        font-size: var(--font-size-large); /* Larger font */
        font-weight: bold;
        border-radius: var(--border-radius-small);
        cursor: pointer;
        transition: background-color var(--transition-medium), transform var(--transition-medium);
        text-transform: uppercase; /* Uppercase text */
        letter-spacing: 0.5px;
    }
    .form-actions .btn.btn-primary:hover {
        background-color: var(--brown-hover);
        transform: translateY(-2px); /* Slight lift effect */
    }
    
    .error-message {
        color: var(--red-error);
        background-color: var(--red-error-background); /* Use background variable */
        padding: var(--spacing-sm); /* Adjust padding */
        border-left: 4px solid var(--red-error); /* Keep border */
        margin-bottom: var(--spacing-md); /* Adjust margin */
        border-radius: var(--border-radius-small); /* Add radius */
        text-align: left; /* Align text left */
        font-weight: bold;
    }
    
    /* Basic responsiveness */
    @media (max-width: 600px) {
        .world-select-container {
            padding: var(--spacing-md); /* Adjust padding on smaller screens */
            margin: var(--spacing-lg) var(--spacing-sm); /* Adjust margin */
        }
        .form-actions .btn.btn-primary {
            width: 100%; /* Full width button */
            padding: var(--spacing-md) var(--spacing-lg); /* Adjust button padding */
        }
    }
</style>

<?php require '../footer.php'; ?> 