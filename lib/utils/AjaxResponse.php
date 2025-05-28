<?php

/**
 * Klasa AjaxResponse - obsługa odpowiedzi AJAX
 */
class AjaxResponse 
{
    /**
     * Wyślij odpowiedź JSON z sukcesem
     *
     * @param mixed $data Dane do wysłania
     * @param string $message Komunikat sukcesu (opcjonalnie)
     * @return void
     */
    public static function success($data = null, $message = '') 
    {
        self::send([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Wyślij odpowiedź JSON z błędem
     *
     * @param string $message Komunikat błędu
     * @param mixed $data Dane dodatkowe (opcjonalnie)
     * @param int $code Kod błędu (opcjonalnie)
     * @return void
     */
    public static function error($message, $data = null, $code = 400) 
    {
        // Ustaw odpowiedni nagłówek HTTP
        http_response_code($code);
        
        self::send([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'code' => $code
        ]);
    }
    
    /**
     * Wyślij odpowiedź JSON z ostrzeżeniem
     *
     * @param string $message Komunikat ostrzeżenia
     * @param mixed $data Dane dodatkowe (opcjonalnie)
     * @return void
     */
    public static function warning($message, $data = null) 
    {
        self::send([
            'status' => 'warning',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Wyślij odpowiedź JSON z informacją
     *
     * @param string $message Komunikat informacyjny
     * @param mixed $data Dane dodatkowe (opcjonalnie)
     * @return void
     */
    public static function info($message, $data = null) 
    {
        self::send([
            'status' => 'info',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Wyślij odpowiedź JSON
     *
     * @param array $data Dane do wysłania
     * @return void
     */
    private static function send($data) 
    {
        // Ustaw nagłówek dla JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Dodaj nagłówki zapobiegające buforowaniu
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Dodaj nagłówek zapobiegający CORS problemom
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Dodaj timestamp
        $data['timestamp'] = time();
        
        // Konwertuj dane do JSON i wyślij
        echo json_encode($data);
        
        // Zakończ wykonywanie skryptu
        exit;
    }
    
    /**
     * Sprawdź, czy bieżące żądanie jest żądaniem AJAX
     *
     * @return bool True, jeśli to żądanie AJAX
     */
    public static function isAjaxRequest() 
    {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }
    
    /**
     * Obsłuż wyjątek i wyślij odpowiedź AJAX z błędem
     * Użyj tej metody w blokach try-catch dla żądań AJAX
     *
     * @param \Throwable $exception Wyjątek do obsłużenia
     * @param bool $logException Czy logować wyjątek (domyślnie true)
     * @return void
     */
    public static function handleException($exception, $logException = true) 
    {
        // Opcjonalnie loguj wyjątek
        if ($logException && class_exists('ErrorHandler')) {
            ErrorHandler::handleException($exception);
        }
        
        // W trybie debugowania, wyślij więcej informacji o błędzie
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::error(
                $exception->getMessage(),
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ],
                500
            );
        } else {
            // W trybie produkcyjnym, wyślij tylko ogólny komunikat
            self::error('Wystąpił błąd podczas przetwarzania żądania.', null, 500);
        }
    }
} 