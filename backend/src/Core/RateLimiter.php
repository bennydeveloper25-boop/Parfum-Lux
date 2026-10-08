<?php
declare(strict_types=1);
namespace App\Core;

final class RateLimiter {
    public static function check(string $ip, int $max, int $window): bool {
        $dir = __DIR__ . '/../../storage/rate';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip) . '.json';
        $now = time();
        $data = ['hits' => [], 'blocked_until' => 0];
        if (is_file($file)) {
            $d = json_decode((string)file_get_contents($file), true);
            if (is_array($d)) $data = array_merge($data, $d);
        }
        $data['hits'] = array_values(array_filter((array)$data['hits'], fn($t) => ($now - (int)$t) < $window));
        if (($data['blocked_until'] ?? 0) > $now) return false;
        $data['hits'][] = $now;
        if (count($data['hits']) > $max) {
            $data['blocked_until'] = $now + $window;
            @file_put_contents($file, json_encode($data));
            return false;
        }
        @file_put_contents($file, json_encode($data));
        return true;
    }
}
