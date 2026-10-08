<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

final class AdminController {
    private \PDO $db;
    public function __construct() { $this->db = Database::pdo(); }

    public function stats(Request $req): void {
        $products = (int)$this->db->query("SELECT COUNT(*) c FROM products")->fetch()['c'];
        $variants = (int)$this->db->query("SELECT COUNT(*) c FROM variants")->fetch()['c'];
        $stock = (int)$this->db->query("SELECT COALESCE(SUM(stock),0) c FROM variants")->fetch()['c'];
        $orders = (int)$this->db->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
        $revenue = (float)$this->db->query("SELECT COALESCE(SUM(total),0) c FROM orders WHERE status NOT IN ('cancelled')")->fetch()['c'];
        $customers = (int)$this->db->query("SELECT COUNT(*) c FROM customers")->fetch()['c'];
        $users = (int)$this->db->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
        $reviews = (int)$this->db->query("SELECT COUNT(*) c FROM reviews")->fetch()['c'];
        $contacts = (int)$this->db->query("SELECT COUNT(*) c FROM contacts WHERE is_read=0")->fetch()['c'];
        $byStatus = $this->db->query("SELECT status, COUNT(*) c, COALESCE(SUM(total),0) t FROM orders GROUP BY status")->fetchAll();
        $lowStock = $this->db->query("SELECT v.*, p.name AS product_name FROM variants v JOIN products p ON v.product_id=p.id WHERE v.stock < 15 ORDER BY v.stock ASC LIMIT 10")->fetchAll();
        $recent = $this->db->query("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS items FROM orders o ORDER BY o.created_at DESC LIMIT 8")->fetchAll();
        $daily = $this->db->query("SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(total),0) t FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
        Response::ok(compact('products','variants','stock','orders','revenue','customers','users','reviews','contacts','byStatus','lowStock','recent','daily'));
    }

    public function orders(Request $req): void {
        $status = $req->query['status'] ?? '';
        $limit = min((int)($req->query['limit'] ?? 50), 100);
        $offset = max((int)($req->query['offset'] ?? 0), 0);
        if ($status !== '' && in_array($status, ['pending','paid','preparing','shipped','delivered','cancelled'], true)) {
            $st = $this->db->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS items FROM orders o WHERE o.status=? ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
            $st->execute([$status]);
        } else {
            $st = $this->db->query("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS items FROM orders o ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
        }
        $orders = $st->fetchAll();
        foreach ($orders as &$o) {
            $it = $this->db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $it->execute([$o['id']]);
            $o['lines'] = $it->fetchAll();
        }
        Response::ok($orders);
    }

    public function contacts(Request $req): void {
        $rows = $this->db->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 100")->fetchAll();
        Response::ok($rows);
    }

    public function markContact(Request $req, array $params): void {
        $this->db->prepare("UPDATE contacts SET is_read=1 WHERE id=?")->execute([(int)$params['id']]);
        Response::ok(['id' => (int)$params['id'], 'is_read' => 1]);
    }

    public function users(Request $req): void {
        $rows = $this->db->query("SELECT u.id,u.name,u.email,r.name AS role,u.is_active,u.created_at FROM users u JOIN roles r ON u.role_id=r.id ORDER BY u.created_at DESC LIMIT 100")->fetchAll();
        Response::ok($rows);
    }

    public function reviews(Request $req): void {
        $rows = $this->db->query("SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON r.product_id=p.id ORDER BY r.created_at DESC LIMIT 100")->fetchAll();
        Response::ok($rows);
    }

    public function toggleReview(Request $req, array $params): void {
        $id = (int)$params['id'];
        $cur = $this->db->prepare("SELECT is_approved FROM reviews WHERE id=?");
        $cur->execute([$id]);
        $row = $cur->fetch();
        if (!$row) Response::error('NOT_FOUND', 'Reseña no encontrada', 404);
        $new = $row['is_approved'] ? 0 : 1;
        $this->db->prepare("UPDATE reviews SET is_approved=? WHERE id=?")->execute([$new, $id]);
        Response::ok(['id' => $id, 'is_approved' => $new]);
    }

    public function audit(Request $req): void {
        $rows = $this->db->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100")->fetchAll();
        Response::ok($rows);
    }
}
