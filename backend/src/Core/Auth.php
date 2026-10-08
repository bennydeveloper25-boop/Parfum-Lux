<?php
declare(strict_types=1);
namespace App\Core;

final class Auth {
    private static function secret(): string {
        $cfg = require __DIR__ . '/../../config/app.php';
        return (string)($cfg['jwt_secret'] ?? 'dev');
    }
    private static function b64u(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
    private static function b64ud(string $s): string { return base64_decode(strtr($s, '-_', '+/')); }

    public static function issue(array $user): string {
        $cfg = require __DIR__ . '/../../config/app.php';
        $header = self::b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64u(json_encode([
            'sub' => $user['id'], 'email' => $user['email'], 'role' => $user['role'] ?? 'cliente',
            'iat' => time(), 'exp' => time() + (int)($cfg['jwt_ttl'] ?? 86400),
        ]));
        $sig = self::b64u(hash_hmac('sha256', "$header.$payload", self::secret(), true));
        return "$header.$payload.$sig";
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $expect = self::b64u(hash_hmac('sha256', "$h.$p", self::secret(), true));
        if (!hash_equals($expect, $s)) return null;
        $payload = json_decode(self::b64ud($p), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
        return $payload;
    }

    public static function bearer(Request $req): ?string {
        $h = $req->header('Authorization');
        if (str_starts_with($h, 'Bearer ')) return substr($h, 7);
        return null;
    }
}
