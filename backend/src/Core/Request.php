<?php
declare(strict_types=1);
namespace App\Core;

final class Request {
    public string $method;
    public string $path;
    public array $query;
    public array $headers;
    public mixed $body;
    public ?array $user = null;
    public string $requestId;

    public static function fromGlobals(): self {
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $r->path = rtrim($uri, '/') === '' ? '/' : rtrim($uri, '/');
        $r->query = $_GET;
        $r->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $raw = file_get_contents('php://input') ?: '';
        $ct = $r->header('Content-Type');
        if (str_contains((string)$ct, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            $r->body = is_array($decoded) ? $decoded : [];
        } elseif ($raw !== '') {
            parse_str($raw, $parsed);
            $r->body = $parsed ?: $_POST;
        } else {
            $r->body = $_POST ?: [];
        }
        $r->requestId = bin2hex(random_bytes(8));
        return $r;
    }

    public function header(string $name): string {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) return (string)$v;
        }
        return '';
    }

    /** Deserializer: body ya normalizado a array */
    public function input(string $key, mixed $default = null): mixed {
        if (is_array($this->body) && array_key_exists($key, $this->body)) return $this->body[$key];
        return $this->query[$key] ?? $default;
    }
}
