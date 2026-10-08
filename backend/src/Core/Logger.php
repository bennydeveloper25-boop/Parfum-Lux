<?php
declare(strict_types=1);
namespace App\Core;

final class Logger {
    public static function log(string $level, string $message, array $ctx = []): void {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $line = sprintf("[%s] %s %s %s\n", date('c'), strtoupper($level), $message, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : '');
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND);
    }
    public static function info(string $m, array $c = []): void { self::log('info', $m, $c); }
    public static function warn(string $m, array $c = []): void { self::log('warn', $m, $c); }
    public static function error(string $m, array $c = []): void { self::log('error', $m, $c); }
}
