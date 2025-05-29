<?php
// require_once 'init.php'; // init.php jest już dołączany w header.php
?>
    <footer class="site-footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>Tribal Wars</h3>
            <p>Strategiczna gra przeglądarkowa</p>
        </div>
        <div class="footer-links">
            <h4>Szybkie linki</h4>
            <ul>
                <li><a href="auth/register.php">Rejestracja</a></li>
                <li><a href="auth/login.php">Logowanie</a></li>
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
    
</body>
</html>
