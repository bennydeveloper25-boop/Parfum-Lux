<?php
declare(strict_types=1);
// Guard: este fichero se require varias veces por petición (sin require_once).
if (!function_exists('env')) {
function env(string $key, string $default = ''): string {
    static $vars = null;
    if ($vars === null) {
        $vars = $_ENV;
        foreach (['.env', '../.env', __DIR__ . '/../.env'] as $f) {
            $p = $f === '.env' ? __DIR__ . '/../.env' : $f;
            if (is_file($p)) {
                foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                    $vars[trim($k)] = trim($v);
                }
                break;
            }
        }
    }
    return $vars[$key] ?? $default;
}
}
return [
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'jwt_secret' => env('JWT_SECRET', 'parfum-lux-dev-secret'),
    'jwt_ttl' => (int)env('JWT_TTL', '86400'),
    'frontend_origin' => env('FRONTEND_ORIGIN', '*'),
    'rate_max' => (int)env('RATE_LIMIT_MAX', '120'),
    'rate_window' => (int)env('RATE_LIMIT_WINDOW', '60'),
];
