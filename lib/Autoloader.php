<?php

/**
 * Klasa Autoloader - automatyczne ładowanie klas
 */
class Autoloader 
{
    /**
     * Rejestruje autoloader
     */
    public static function register() 
    {
        spl_autoload_register([self::class, 'loadClass']);
    }
    
    /**
     * Ładuje klasę na podstawie jej nazwy
     *
     * @param string $className Nazwa klasy do załadowania
     * @return void
     */
    public static function loadClass($className) 
    {
        // Sprawdź czy nazwa klasy zawiera przestrzeń nazw (namespace)
        if (strpos($className, '\\') !== false) {
            // Zamień backslash na directory separator
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        }
        
        // Podstawowe ścieżki, gdzie mogą znajdować się klasy
        $paths = [
            'lib/',
            'lib/managers/',
            'lib/models/',
            'lib/utils/',
        ];
        
        // Sprawdź każdą ścieżkę
        foreach ($paths as $path) {
            $file = $path . $className . '.php';
            
            // Jeśli plik istnieje, załaduj go
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}

// Zarejestruj autoloader
Autoloader::register(); 