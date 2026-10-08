<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware {
    /** @param string[] $roles */
    public static function require(array $roles = []): callable {
        return function (Request $req) use ($roles): void {
            $token = Auth::bearer($req);
            if (!$token) Response::error('UNAUTHORIZED', 'Falta token Bearer.', 401);
            $payload = Auth::verify($token);
            if (!$payload) Response::error('UNAUTHORIZED', 'Token inválido o expirado.', 401);
            if ($roles && !in_array($payload['role'] ?? '', $roles, true)) {
                Response::error('FORBIDDEN', 'No tienes permiso (rol requerido: ' . implode(',', $roles) . ').', 403);
            }
            $req->user = $payload;
        };
    }
    public static function optional(): callable {
        return function (Request $req): void {
            $token = Auth::bearer($req);
            if ($token) {
                $p = Auth::verify($token);
                if ($p) $req->user = $p;
            }
        };
    }
}
