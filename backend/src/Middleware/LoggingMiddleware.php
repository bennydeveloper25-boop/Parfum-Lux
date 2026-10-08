<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Request;
use App\Core\Logger;

final class LoggingMiddleware {
    public static function handle(Request $req): void {
        Logger::info('REQUEST', [
            'id' => $req->requestId,
            'method' => $req->method,
            'path' => $req->path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
}