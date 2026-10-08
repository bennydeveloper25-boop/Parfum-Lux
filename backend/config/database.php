<?php
declare(strict_types=1);
// Guard: require múltiple por petición (ver config/app.php).
if (!function_exists('dbenv')) {
function dbenv(string $key, string $default = ''): string {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $p = __DIR__ . '/../.env';
        if (is_file($p)) {
            foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $vars[trim($k)] = trim($v);
            }
        }
    }
    return $vars[$key] ?? $default;
}
}
return [
    'host' => dbenv('DB_HOST', '127.0.0.1'),
    'port' => dbenv('DB_PORT', '3306'),
    'name' => dbenv('DB_NAME', 'parfumlux'),
    'user' => dbenv('DB_USER', 'root'),
    'pass' => dbenv('DB_PASS', ''),
    'charset' => dbenv('DB_CHARSET', 'utf8mb4'),
];
