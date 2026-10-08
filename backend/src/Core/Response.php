<?php
declare(strict_types=1);
namespace App\Core;

final class Response {
    /** Serializer: envelope uniforme */
    public static function json(mixed $data, int $status = 200, array $extraHeaders = []): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($extraHeaders as $k => $v) header("$k: $v");
        if (is_array($data) && array_key_exists('success', $data)) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['success' => $status < 400, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
    public static function ok(mixed $data, array $meta = []): void {
        self::json(['success' => true, 'data' => $data, 'meta' => $meta]);
    }
    public static function created(mixed $data): void {
        self::json(['success' => true, 'data' => $data], 201);
    }
    public static function error(string $code, string $message, int $status = 400, array $details = []): void {
        self::json(['success' => false, 'error' => ['code' => $code, 'message' => $message, 'details' => $details]], $status);
    }
}
