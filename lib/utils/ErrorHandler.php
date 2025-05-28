<?php

/**
 * Klasa ErrorHandler - obsługa błędów i wyjątków
 */
class ErrorHandler 
{
    /**
     * Inicjalizuje obsługę błędów
     */
    public static function initialize() 
    {
        // Ustaw obsługę błędów PHP
        set_error_handler([self::class, 'handleError']);
        
        // Ustaw obsługę wyjątków PHP
        set_exception_handler([self::class, 'handleException']);
        
        // Ustaw obsługę błędów, które nie zostały przechwycone
        register_shutdown_function([self::class, 'handleFatalError']);
    }
    
    /**
     * Obsługuje błędy PHP
     *
     * @param int $errno Numer błędu
     * @param string $errstr Komunikat błędu
     * @param string $errfile Plik, w którym wystąpił błąd
     * @param int $errline Linia, w której wystąpił błąd
     * @return bool True, jeśli błąd został obsłużony
     */
    public static function handleError($errno, $errstr, $errfile, $errline) 
    {
        // Ignoruj błędy, które są wyłączone w konfiguracji PHP
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Loguj błąd
        self::logError('ERROR', $errstr, $errfile, $errline);
        
        // Wyświetl komunikat błędu, gdy tryb debugowania jest włączony
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div class='error-message'>Błąd: $errstr w pliku $errfile linia $errline</div>";
        } else {
            // W przeciwnym razie wyświetl przyjazny komunikat
            if ($errno == E_USER_ERROR) {
                self::displayUserFriendlyError("Wystąpił błąd w aplikacji. Administratorzy zostali powiadomieni.");
            }
        }
        
        // Nie pozwól PHP na standardową obsługę błędu
        return true;
    }
    
    /**
     * Obsługuje wyjątki PHP
     *
     * @param \Throwable $exception Wyjątek
     * @return void
     */
    public static function handleException($exception) 
    {
        // Loguj wyjątek
        self::logError(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // Wyświetl komunikat błędu, gdy tryb debugowania jest włączony
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div class='error-message'>
                <h3>Wyjątek: " . get_class($exception) . "</h3>
                <p>{$exception->getMessage()}</p>
                <p>Plik: {$exception->getFile()} linia {$exception->getLine()}</p>
                <pre>{$exception->getTraceAsString()}</pre>
            </div>";
        } else {
            // W przeciwnym razie wyświetl przyjazny komunikat
            self::displayUserFriendlyError("Wystąpił błąd w aplikacji. Administratorzy zostali powiadomieni.");
        }
    }
    
    /**
     * Obsługuje błędy krytyczne (fatal errors)
     *
     * @return void
     */
    public static function handleFatalError() 
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Loguj błąd krytyczny
            self::logError(
                'FATAL',
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            // Wyświetl komunikat błędu, gdy tryb debugowania jest włączony
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                echo "<div class='error-message'>
                    <h3>Błąd krytyczny</h3>
                    <p>{$error['message']}</p>
                    <p>Plik: {$error['file']} linia {$error['line']}</p>
                </div>";
            } else {
                // W przeciwnym razie wyświetl przyjazny komunikat
                self::displayUserFriendlyError("Wystąpił błąd krytyczny. Administratorzy zostali powiadomieni.");
            }
        }
    }
    
    /**
     * Loguje błąd do pliku
     *
     * @param string $type Typ błędu
     * @param string $message Komunikat błędu
     * @param string $file Plik, w którym wystąpił błąd
     * @param int $line Linia, w której wystąpił błąd
     * @param string $trace Stack trace (opcjonalnie)
     * @return void
     */
    private static function logError($type, $message, $file, $line, $trace = '') 
    {
        $log_file = 'logs/errors.log';
        
        // Utwórz katalog logs, jeśli nie istnieje
        if (!file_exists('logs')) {
            mkdir('logs', 0777, true);
        }
        
        // Przygotuj wpis do logu
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $type: $message w $file linia $line";
        
        if (!empty($trace)) {
            $log_entry .= "\nTrace: $trace";
        }
        
        $log_entry .= "\n" . str_repeat('-', 80) . "\n";
        
        // Zapisz do pliku
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        // Jeśli to poważny błąd, można dodatkowo powiadomić administratora (np. e-mail)
        if ($type == 'FATAL' || $type == 'EXCEPTION') {
            // TODO: Dodać wysyłanie powiadomień e-mail do administratora
            // self::notifyAdmin($type, $message, $file, $line);
        }
    }
    
    /**
     * Wyświetla przyjazny komunikat błędu dla użytkownika
     *
     * @param string $message Komunikat błędu
     * @return void
     */
    private static function displayUserFriendlyError($message) 
    {
        // Sprawdź, czy nagłówki zostały już wysłane
        if (!headers_sent()) {
            // Ustaw nagłówek HTTP dla błędu
            header('HTTP/1.1 500 Internal Server Error');
            
            // Jeśli to jest żądanie AJAX, zwróć JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => $message]);
                exit;
            }
        }
        
        // Normalne żądanie HTTP - wyświetl stronę błędu
        echo "<!DOCTYPE html>
        <html lang='pl'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Błąd aplikacji</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; }
                .error-container { max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #e3e3e3; border-radius: 5px; background: #f9f9f9; }
                h1 { color: #d9534f; }
                .back-link { margin-top: 20px; }
                .back-link a { color: #0275d8; text-decoration: none; }
                .back-link a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>Błąd aplikacji</h1>
                <p>$message</p>
                <div class='back-link'>
                    <a href='javascript:history.back()'>Wróć do poprzedniej strony</a> lub <a href='index.php'>przejdź do strony głównej</a>
                </div>
            </div>
        </body>
        </html>";
        
        // Zakończ wykonywanie skryptu
        exit;
    }
} 