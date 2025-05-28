<?php
require 'init.php';
// Jeśli użytkownik jest zalogowany, przejdź do wyboru świata lub bezpośrednio do gry
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['world_id'])) {
        header('Location: world_select.php?redirect=game.php');
        exit();
    }
    header('Location: game.php');
    exit();
}

// --- PRZETWARZANIE DANYCH ---
// Pobierz statystyki dla strony głównej przy użyciu Prepared Statements
$stats = [
    'worlds' => 0,
    'players' => 0,
    'villages' => 0
];

if ($conn) {
    // Liczba światów
    $stmt_worlds = $conn->prepare("SELECT COUNT(*) AS count FROM worlds");
    if ($stmt_worlds) {
        $stmt_worlds->execute();
        $result_worlds = $stmt_worlds->get_result();
        if ($row = $result_worlds->fetch_assoc()) {
            $stats['worlds'] = $row['count'];
        }
        $stmt_worlds->close();
    }
    
    // Liczba graczy
    $stmt_players = $conn->prepare("SELECT COUNT(*) AS count FROM users");
     if ($stmt_players) {
        $stmt_players->execute();
        $result_players = $stmt_players->get_result();
        if ($row = $result_players->fetch_assoc()) {
            $stats['players'] = $row['count'];
        }
        $stmt_players->close();
    }
    
    // Liczba wiosek
    $stmt_villages = $conn->prepare("SELECT COUNT(*) AS count FROM villages");
     if ($stmt_villages) {
        $stmt_villages->execute();
        $result_villages = $stmt_villages->get_result();
        if ($row = $result_villages->fetch_assoc()) {
            $stats['villages'] = $row['count'];
        }
        $stmt_villages->close();
    }
}

// --- PREZENTACJA (HTML) ---
$pageTitle = 'Tribal Wars - Nowa Wersja';
require 'header.php';
?>
<main class="homepage">
    <section class="hero" style="background-image: url('img/village_bg.jpg');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h2>Witaj w nowej wersji Tribal Wars!</h2>
            <p>Odkryj nowoczesne Plemiona z dynamiczną rozgrywką i strategicznymi wyzwaniami.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary">Zarejestruj się</a>
                <a href="login.php" class="btn btn-secondary">Zaloguj się</a>
            </div>
        </div>
    </section>
    
    <section class="stats-bar">
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['worlds']) ?></span>
            <span class="stat-label">Światy</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['players']) ?></span>
            <span class="stat-label">Graczy</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['villages']) ?></span>
            <span class="stat-label">Wiosek</span>
        </div>
    </section>
    
        <section class="features">
            <h2>Najważniejsze funkcje</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/resources.png" alt="Surowce">
                    </div>
                    <h3>Produkcja surowców</h3>
                    <p>Zarządzaj wydobyciem drewna, gliny i żelaza w czasie rzeczywistym. Rozbudowuj kopalnie, by zwiększyć produkcję.</p>
                    <a href="register.php" class="feature-link">Rozpocznij produkcję</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/buildings/main.png" alt="Budynki">
                    </div>
                    <h3>Rozwój wiosek</h3>
                    <p>Buduj i ulepszaj budynki, aby wzmocnić swoją pozycję. Każdy budynek daje ci nowe możliwości i przewagę.</p>
                    <a href="register.php" class="feature-link">Buduj imperium</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/map/map.png" alt="Mapa świata">
                    </div>
                    <h3>Interaktywna mapa</h3>
                    <p>Odkryj mapę świata z możliwością przeciągania. Planuj strategiczne ataki i podbijaj nowe terytoria.</p>
                    <a href="register.php" class="feature-link">Eksploruj świat</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/unit/spear.png" alt="Jednostki">
                    </div>
                    <h3>Jednostki bojowe</h3>
                    <p>Rekrutuj różne rodzaje jednostek i atakuj wrogów. Twórz potężne armie i broń swojego terytorium.</p>
                    <a href="register.php" class="feature-link">Twórz armię</a>
                </div>
            </div>
        </section>
    
    <section class="game-description">
        <div class="description-container">
            <h2>Strategiczna gra przeglądarkowa</h2>
            <p>Tribal Wars to gra strategiczna, w której jako przywódca małej wioski musisz rozwijać swoje terytorium, zdobywać surowce i budować armię. Twórz sojusze z innymi graczami, podbijaj nowe terytoria i stań się najpotężniejszym władcą w świecie Plemion!</p>
            
            <h3>Kluczowe elementy rozgrywki:</h3>
            <ul class="feature-list">
                <li><strong>Rozwój ekonomiczny</strong> - buduj kopalnie i zwiększaj produkcję surowców</li>
                <li><strong>Ekspansja terytorialna</strong> - zakładaj nowe wioski i powiększaj swoje imperium</li>
                <li><strong>Wojskowość</strong> - trenuj różne rodzaje jednostek i prowadź wojny</li>
                <li><strong>Dyplomacja</strong> - zawieraj sojusze i współpracuj z innymi graczami</li>
            </ul>
            
            <div class="cta-box">
                <h3>Dołącz do gry już teraz!</h3>
                <p>Rejestracja zajmuje tylko chwilę, a gra jest całkowicie darmowa.</p>
                <a href="register.php" class="btn btn-primary">Rozpocznij grę</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>Tribal Wars</h3>
            <p>Strategiczna gra przeglądarkowa</p>
        </div>
        <div class="footer-links">
            <h4>Szybkie linki</h4>
            <ul>
                <li><a href="register.php">Rejestracja</a></li>
                <li><a href="login.php">Logowanie</a></li>
                <li><a href="#">Pomoc</a></li>
                <li><a href="#">Regulamin</a></li>
            </ul>
        </div>
        <div class="footer-info">
            <h4>O projekcie</h4>
            <p>Ta wersja Tribal Wars jest nowoczesną implementacją klasycznej gry strategicznej. Projekt powstał w oparciu o PHP, MySQL, HTML5 i CSS3.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Tribal Wars. Wszelkie prawa zastrzeżone.</p>
    </div>
    </footer>

<?php
// Zamknij połączenie z bazą po renderowaniu strony (lub na końcu skryptu)
// W tym przypadku init.php otwiera połączenie, można zamknąć je tutaj.
if (isset($database)) {
    $database->closeConnection();
}
?>
