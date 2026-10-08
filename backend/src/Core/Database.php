<?php
declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOException;

final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $cfg = require __DIR__ . '/../../config/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET NAMES '{$cfg['charset']}' COLLATE 'utf8mb4_0900_ai_ci'");
        } catch (PDOException $e) {
            $app = require __DIR__ . '/../../config/app.php';
            $detail = ($app['debug'] ?? false) ? $e->getMessage() . " | DSN=mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']} user={$cfg['user']} | pdo_mysql=" . (extension_loaded('pdo_mysql') ? 'si' : 'NO-could-not-find-driver') : 'Revisa backend/.env';
            Response::json(['success' => false, 'error' => ['code' => 'DB_CONNECTION', 'message' => 'No se pudo conectar a MySQL parfumlux. ' . $detail]], 500);
            exit;
        }
        return self::$pdo;
    }
    /** Auditoría best-effort: todo lo que el usuario ingresa queda en audit_log */
    public static function audit(string $action, string $entity, mixed $entityId = null, mixed $meta = null, mixed $userId = null): void {
        try {
            $pdo = self::$pdo;
            if (!$pdo) return;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $metaJson = $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$userId, $action, $entity, $entityId !== null ? (string)$entityId : null, $metaJson, $ip]);
        } catch (\Throwable $e) { /* nunca romper la petición por auditoría */ }
    }
}
