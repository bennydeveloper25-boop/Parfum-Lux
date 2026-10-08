<?php
// Router para php -S (desarrollo). Sirve estáticos si existen, si no va a index.php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // deja que php -S sirva el fichero
}
require __DIR__ . '/index.php';
