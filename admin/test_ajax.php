<?php
// test_ajax.php
// Ten plik ma na celu sprawdzenie, czy get_resources.php jest dostępny z poziomu serwera.

$file_path = $_SERVER['DOCUMENT_ROOT'] . '/ajax/resources/get_resources.php';

if (file_exists($file_path)) {
    echo "Plik istnieje: " . $file_path . "\n";
    // Spróbuj załadować zawartość pliku
    $content = file_get_contents($file_path);
    if ($content !== false) {
        echo "Zawartość pliku:\n";
        echo $content;
    } else {
        echo "Błąd odczytu pliku.\n";
    }
} else {
    echo "Plik nie istnieje: " . $file_path . "\n";
}
?>
