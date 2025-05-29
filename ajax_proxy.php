<?php
// ajax_proxy.php
// Ten plik działa jako proxy dla żądań AJAX do get_resources.php.
// Jest to obejście problemu z błędem 404, który występuje przy bezpośrednim dostępie do get_resources.php.

// Dołącz ajax/get_resources.php
require_once __DIR__ . '/ajax/resources/get_resources.php';

// Po dołączeniu get_resources.php, jego kod zostanie wykonany,
// a AjaxResponse::success() lub AjaxResponse::error() wyśle odpowiedź JSON i zakończy skrypt.
// Nie ma potrzeby dodawania tutaj żadnego dodatkowego kodu.
?>
