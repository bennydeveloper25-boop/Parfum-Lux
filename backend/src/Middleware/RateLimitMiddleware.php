<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\RateLimiter;

final class RateLimitMiddleware {
    public static function handle(Request $req): void {
        $cfg = require __DIR__ . '/../../config/app.php';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::check($ip, $cfg['rate_max'], $cfg['rate_window'])) {
            Response::error('RATE_LIMITED', 'Demasiadas peticiones. Intenta más tarde.', 429);
        }
    }
}