<?php
declare(strict_types=1);
// La API nunca debe imprimir warnings dentro del JSON: van al log.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Autoloader simple PSR-4 (sin composer/vendor)
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $rel = str_replace('\\', '/', substr($class, 4)); // ej Controller/HealthController
    $base = __DIR__ . '/../src/';
    // 1. Intento directo: src/Controller/HealthController.php, src/Core/Router.php ...
    $direct = $base . $rel . '.php';
    if (is_file($direct)) { require_once $direct; return; }
    // 2. Ficheros agrupados por capa
    $map = [
        'Dto/' => $base . 'Dto/Dto.php',
        'Entity/' => $base . 'Entity/Entity.php',
        'Repository/' => $base . 'Repository/Repository.php',
        'Service/' => $base . 'Service/Service.php',
        'Controller/' => $base . 'Controller/Controller.php',
    ];
    foreach ($map as $prefix => $file) {
        if (str_starts_with($rel, $prefix) && is_file($file)) { require_once $file; return; }
    }
});

use App\Core\{Request, Response, Router};
use App\Middleware\{CorsMiddleware, LoggingMiddleware, RateLimitMiddleware, AuthMiddleware};
use App\Controller\{HealthController, AuthController, ProductController, OrderController, ReviewController, ContactController, FaqController, ScentFamilyController};

$router = new Router();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
$mwGlobal = [LoggingMiddleware::handle(...), RateLimitMiddleware::handle(...)];

$hc = new HealthController();
$router->get('/api/health', [$hc, 'get'], $mwGlobal);
$router->get('/api', function (Request $req): void {
    Response::ok(['service' => 'Parfum Lux API', 'version' => '1.0', 'docs' => '/docs/API.md', 'endpoints' => ['GET /api/health', 'GET /api/products', 'POST /api/auth/login', 'POST /api/auth/register']]);
}, $mwGlobal);

$ac = new AuthController();
$router->post('/api/auth/login', [$ac, 'login'], $mwGlobal);
$router->post('/api/auth/register', [$ac, 'register'], $mwGlobal);
$router->get('/api/auth/me', [$ac, 'me'], array_merge($mwGlobal, [AuthMiddleware::require([])]));
$router->put('/api/auth/profile', [$ac, 'updateProfile'], array_merge($mwGlobal, [AuthMiddleware::require([])]));

$pc = new ProductController();
$router->get('/api/products', [$pc, 'list'], $mwGlobal);
$router->get('/api/products/featured', [$pc, 'featured'], $mwGlobal);
$router->get('/api/products/{id}', [$pc, 'get'], $mwGlobal);
$router->post('/api/products', [$pc, 'create'], array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]));
$router->put('/api/products/{id}', [$pc, 'update'], array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]));
$router->delete('/api/products/{id}', [$pc, 'delete'], array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]));
$router->post('/api/variants', [$pc, 'createVariant'], array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]));

$oc = new OrderController();
$router->post('/api/orders', [$oc, 'create'], array_merge($mwGlobal, [AuthMiddleware::optional()]));
$router->get('/api/orders/{id}', [$oc, 'get'], array_merge($mwGlobal, [AuthMiddleware::optional()]));
$router->get('/api/orders', [$oc, 'list'], array_merge($mwGlobal, [AuthMiddleware::require([])]));
$router->put('/api/orders/{id}', [$oc, 'updateStatus'], array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]));

$rc = new ReviewController();
$router->get('/api/products/{product_id}/reviews', [$rc, 'list'], $mwGlobal);
$router->post('/api/reviews', [$rc, 'create'], array_merge($mwGlobal, [AuthMiddleware::optional()]));

$cc = new ContactController();
$router->post('/api/contact', [$cc, 'create'], $mwGlobal);

$fc = new FaqController();
$router->get('/api/faqs', [$fc, 'list'], $mwGlobal);

$sfc = new ScentFamilyController();
$router->get('/api/families', [$sfc, 'list'], $mwGlobal);

$nlc = new \App\Controller\NewsletterController();
$router->post('/api/newsletter', [$nlc, 'subscribe'], $mwGlobal);
$slc = new \App\Controller\SearchLogController();
$router->post('/api/searches', [$slc, 'log'], array_merge($mwGlobal, [AuthMiddleware::optional()]));

$adm = new \App\Controller\AdminController();
$admMw = array_merge($mwGlobal, [AuthMiddleware::require(['admin'])]);
$router->get('/api/admin/stats', [$adm, 'stats'], $admMw);
$router->get('/api/admin/orders', [$adm, 'orders'], $admMw);
$router->get('/api/admin/contacts', [$adm, 'contacts'], $admMw);
$router->put('/api/admin/contacts/{id}', [$adm, 'markContact'], $admMw);
$router->get('/api/admin/users', [$adm, 'users'], $admMw);
$router->get('/api/admin/reviews', [$adm, 'reviews'], $admMw);
$router->put('/api/admin/reviews/{id}', [$adm, 'toggleReview'], $admMw);
$router->get('/api/admin/audit', [$adm, 'audit'], $admMw);

try {
    $req = Request::fromGlobals();
    $router->dispatch($req);
} catch (\Throwable $e) {
    Response::error('SERVER_ERROR', 'Error interno: ' . $e->getMessage(), 500);
}