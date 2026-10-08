<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service;

class HealthController {
    public function get(Request $req): void { Response::ok(['status' => 'ok', 'service' => 'Parfum Lux API', 'time' => date('c')]); }
}

class AuthController {
    private Service\AuthService $svc;
    public function __construct() { $this->svc = new Service\AuthService(); }
    public function login(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['email' => 'required|email', 'password' => 'required|min:6']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $token = $this->svc->login($req->body['email'], $req->body['password']);
        if (!$token) {
            \App\Core\Database::audit('login_failed', 'user', null, ['email' => $req->body['email']]);
            Response::error('UNAUTHORIZED', 'Credenciales incorrectas', 401);
        }
        \App\Core\Database::audit('login', 'user', $token['id'], ['email' => $token['email']], $token['id']);
        Response::ok(['token' => \App\Core\Auth::issue($token), 'user' => $token]);
    }
    public function register(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['name' => 'required|min:2|max:120', 'email' => 'required|email', 'password' => 'required|min:6']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        try {
            $id = $this->svc->register($req->body['name'], $req->body['email'], $req->body['password']);
            \App\Core\Database::audit('register', 'user', $id, ['email' => $req->body['email'], 'name' => $req->body['name']], $id);
            $user = $this->svc->me($id);
            \App\Core\Response::created(['token' => \App\Core\Auth::issue(['id' => $id, 'email' => $req->body['email'], 'role' => 'cliente']), 'user' => $user]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') Response::error('CONFLICT', 'Email ya registrado', 409);
            throw $e;
        }
    }
    public function me(Request $req): void {
        if (!$req->user) Response::error('UNAUTHORIZED', 'No autenticado', 401);
        $full = $this->svc->me((int)$req->user['sub']);
        Response::ok($full ?: $req->user);
    }
    public function updateProfile(Request $req): void {
        if (!$req->user) Response::error('UNAUTHORIZED', 'No autenticado', 401);
        $v = \App\Core\Validator::validate($req->body, ['name' => 'required|min:2|max:120']);
        if (!$v['valid']) Response::error('VALIDATION', 'Nombre inválido', 422, $v['errors']);
        $repo = new \App\Repository\UserRepository();
        $repo->updateName((int)$req->user['sub'], $req->body['name']);
        \App\Core\Database::audit('profile_updated', 'user', (int)$req->user['sub'], ['name' => $req->body['name']], (int)$req->user['sub']);
        Response::ok($this->svc->me((int)$req->user['sub']));
    }
}

class ProductController {
    private Service\ProductService $svc;
    public function __construct() { $this->svc = new Service\ProductService(); }
    public function list(Request $req): void {
        $limit = min((int)($req->query['limit'] ?? 50), 100);
        $offset = max((int)($req->query['offset'] ?? 0), 0);
        Response::ok($this->svc->list($limit, $offset));
    }
    public function featured(Request $req): void {
        Response::ok($this->svc->featured());
    }
    public function get(Request $req, array $params): void {
        $p = $this->svc->get((int)$params['id']);
        if (!$p) Response::error('NOT_FOUND', 'Producto no encontrado', 404);
        Response::ok($p);
    }
    public function create(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['sku' => 'required|max:40', 'name' => 'required|max:120', 'price_base' => 'required|numeric']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $noteIds = is_array($req->body['note_ids'] ?? null) ? array_map('intval', $req->body['note_ids']) : [];
        $id = $this->svc->create($req->body, $noteIds);
        Response::created($this->svc->get($id));
    }
    public function update(Request $req, array $params): void {
        $noteIds = is_array($req->body['note_ids'] ?? null) ? array_map('intval', $req->body['note_ids']) : null;
        $this->svc->update((int)$params['id'], $req->body, $noteIds);
        Response::ok($this->svc->get((int)$params['id']));
    }
    public function delete(Request $req, array $params): void {
        $this->svc->delete((int)$params['id']);
        Response::ok(null);
    }
    public function createVariant(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['product_id' => 'required|int', 'size_ml' => 'required|int', 'sku' => 'required|max:60', 'price' => 'required|numeric']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $id = $this->svc->createVariant($req->body);
        Response::created(['id' => $id]);
    }
}

class OrderController {
    private Service\OrderService $svc;
    public function __construct() { $this->svc = new Service\OrderService(); }
    public function create(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['items' => 'required']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $items = $req->body['items'];
        if (!is_array($items) || $items === []) Response::error('VALIDATION', 'Items vacíos', 422);
        foreach ($items as $it) {
            if (!is_array($it) || !isset($it['variant_id'], $it['qty'], $it['unit_price'], $it['product_name'])) Response::error('VALIDATION', 'Item inválido (variant_id, qty, unit_price, product_name)', 422);
            if ((int)$it['qty'] < 1 || (float)$it['unit_price'] < 0) Response::error('VALIDATION', 'Cantidad o precio inválidos', 422);
        }
        $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $items));
        $shipping = $subtotal >= 3000 ? 0 : 150;
        $order = ['customer_id' => $req->body['customer_id'] ?? null, 'user_id' => $req->user['sub'] ?? null, 'subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping, 'currency' => 'NIO'];
        try {
            $id = $this->svc->create($order, $items);
            \App\Core\Database::audit('order_created', 'order', $id, ['total' => $order['total'], 'items' => count($items)], $order['user_id']);
            Response::created($this->svc->get($id));
        } catch (\RuntimeException $e) {
            Response::error('STOCK', $e->getMessage(), 400);
        }
    }
    public function get(Request $req, array $params): void {
        $o = $this->svc->get((int)$params['id']);
        if (!$o) Response::error('NOT_FOUND', 'Pedido no encontrado', 404);
        if ($req->user && $req->user['role'] !== 'admin' && ($o['user_id'] ?? 0) !== ($req->user['sub'] ?? 0)) Response::error('FORBIDDEN', 'No es tu pedido', 403);
        Response::ok($o);
    }
    public function list(Request $req): void {
        if (!$req->user) Response::error('UNAUTHORIZED', 'Requiere login', 401);
        Response::ok($this->svc->listByUser((int)$req->user['sub']));
    }
    public function updateStatus(Request $req, array $params): void {
        if (!in_array($req->body['status'] ?? '', ['pending','paid','preparing','shipped','delivered','cancelled'], true)) Response::error('VALIDATION', 'Estado inválido', 422);
        $this->svc->updateStatus((int)$params['id'], $req->body['status']);
        Response::ok($this->svc->get((int)$params['id']));
    }
}

class ReviewController {
    private Service\ReviewService $svc;
    public function __construct() { $this->svc = new Service\ReviewService(); }
    public function list(Request $req, array $params): void { Response::ok($this->svc->list((int)$params['product_id'])); }
    public function create(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['product_id' => 'required|int', 'rating' => 'required|int', 'author_name' => 'required|min:2|max:120']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $id = $this->svc->create(['product_id' => (int)$req->body['product_id'], 'user_id' => $req->user['sub'] ?? null, 'author_name' => $req->body['author_name'], 'rating' => (int)$req->body['rating'], 'title' => $req->body['title'] ?? null, 'comment' => $req->body['comment'] ?? null]);
        \App\Core\Database::audit('review_created', 'review', $id, ['product_id' => (int)$req->body['product_id'], 'rating' => (int)$req->body['rating']], $req->user['sub'] ?? null);
        Response::created(['id' => $id]);
    }
}

class ContactController {
    private Service\ContactService $svc;
    public function __construct() { $this->svc = new Service\ContactService(); }
    public function create(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['name' => 'required|max:120', 'email' => 'required|email', 'message' => 'required']);
        if (!$v['valid']) Response::error('VALIDATION', 'Datos inválidos', 422, $v['errors']);
        $id = $this->svc->create($req->body);
        \App\Core\Database::audit('contact_created', 'contact', $id, ['email' => $req->body['email'], 'subject' => $req->body['subject'] ?? null]);
        Response::created(['id' => $id]);
    }
}

class NewsletterController {
    public function subscribe(Request $req): void {
        $v = \App\Core\Validator::validate($req->body, ['email' => 'required|email']);
        if (!$v['valid']) Response::error('VALIDATION', 'Email no válido', 422, $v['errors']);
        $db = \App\Core\Database::pdo();
        try {
            $st = $db->prepare("INSERT INTO newsletter_subscribers (email, name) VALUES (?,?) ON DUPLICATE KEY UPDATE email=email");
            $st->execute([$req->body['email'], $req->body['name'] ?? null]);
            $id = (int)$db->lastInsertId();
            \App\Core\Database::audit('newsletter_subscribe', 'newsletter', $id ?: null, ['email' => $req->body['email']]);
            Response::created(['subscribed' => true]);
        } catch (\Throwable $e) {
            Response::error('SERVER_ERROR', 'No se pudo suscribir', 500);
        }
    }
}

class SearchLogController {
    public function log(Request $req): void {
        $q = trim((string)($req->body['q'] ?? ''));
        if ($q === '') Response::error('VALIDATION', 'Búsqueda vacía', 422);
        \App\Core\Database::audit('search', 'search', null, ['q' => substr($q, 0, 120)], $req->user['sub'] ?? null);
        Response::ok(['logged' => true]);
    }
}

class FaqController {
    private Service\FaqService $svc;
    public function __construct() { $this->svc = new Service\FaqService(); }
    public function list(Request $req): void { Response::ok($this->svc->list()); }
}

class ScentFamilyController {
    private Service\ScentFamilyService $svc;
    public function __construct() { $this->svc = new Service\ScentFamilyService(); }
    public function list(Request $req): void { Response::ok($this->svc->list()); }
}