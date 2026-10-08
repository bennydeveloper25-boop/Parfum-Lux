<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class CorsMiddleware {
    public static function handle(Request $req): void {
        $cfg = require __DIR__ . '/../../config/app.php';
        header('Access-Control-Allow-Origin: ' . ($cfg['frontend_origin'] ?? '*'));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        if ($req->method === 'OPTIONS') { http_response_code(204); exit; }
    }
}
