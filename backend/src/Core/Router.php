<?php
declare(strict_types=1);
namespace App\Core;

final class Router {
    private array $routes = [];
    public function get(string $path, callable $h, array $mw = []): void { $this->add('GET', $path, $h, $mw); }
    public function post(string $path, callable $h, array $mw = []): void { $this->add('POST', $path, $h, $mw); }
    public function put(string $path, callable $h, array $mw = []): void { $this->add('PUT', $path, $h, $mw); }
    public function delete(string $path, callable $h, array $mw = []): void { $this->add('DELETE', $path, $h, $mw); }
    private function add(string $m, string $path, callable $h, array $mw): void {
        $this->routes[] = ['method' => $m, 'path' => $path, 'handler' => $h, 'mw' => $mw,
            'regex' => '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '$#'];
    }
    public function dispatch(Request $req): void {
        foreach ($this->routes as $r) {
            if ($r['method'] !== $req->method) continue;
            if (!preg_match($r['regex'], $req->path, $m)) continue;
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($r['mw'] as $middleware) { $middleware($req); }
            ($r['handler'])($req, $params);
            return;
        }
        Response::error('NOT_FOUND', 'Ruta no encontrada: ' . $req->method . ' ' . $req->path, 404);
    }
}
