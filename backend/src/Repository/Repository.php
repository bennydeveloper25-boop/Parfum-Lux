<?php
declare(strict_types=1);
namespace App\Repository;

use App\Core\Database;
use App\Entity;

abstract class BaseRepository {
    public \PDO $db;
    public function __construct() { $this->db = Database::pdo(); }
    public function begin(): void { $this->db->beginTransaction(); }
    public function commit(): void { $this->db->commit(); }
    public function rollback(): void { if ($this->db->inTransaction()) $this->db->rollBack(); }
}

class ProductRepository extends BaseRepository {
    public function findAll(int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare("SELECT p.*, sf.name as family_name FROM products p LEFT JOIN scent_families sf ON p.family_id = sf.id WHERE p.is_active = 1 ORDER BY p.is_featured DESC, p.id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    public function findFeatured(int $limit = 6): array {
        $stmt = $this->db->prepare("SELECT p.*, sf.name as family_name FROM products p LEFT JOIN scent_families sf ON p.family_id = sf.id WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT p.*, sf.name as family_name FROM products p LEFT JOIN scent_families sf ON p.family_id = sf.id WHERE p.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function getNotes(int $productId): array {
        $stmt = $this->db->prepare("SELECT n.id, n.name, n.type, n.description FROM notes n JOIN product_notes pn ON n.id = pn.note_id WHERE pn.product_id = ? ORDER BY FIELD(n.type,'top','heart','base'), n.name");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    public function getVariants(int $productId): array {
        $stmt = $this->db->prepare("SELECT * FROM variants WHERE product_id = ? AND is_active = 1 ORDER BY size_ml");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO products (sku,name,tagline,description,family_id,concentration,price_base,is_active,is_featured,image_emoji,image_url) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$data['sku'] ?? '',$data['name'] ?? '',$data['tagline'] ?? null,$data['description'] ?? null,$data['family_id'] ?? null,$data['concentration'] ?? 'Eau de Parfum',$data['price_base'] ?? 0,$data['is_active'] ?? 1,$data['is_featured'] ?? 0,$data['image_emoji'] ?? null,$data['image_url'] ?? null]);
        return (int)$this->db->lastInsertId();
    }
    public function update(int $id, array $data): bool {
        $allowed = ['sku','name','tagline','description','family_id','concentration','price_base','is_active','is_featured','image_emoji','image_url'];
        $fields = []; $vals = [];
        foreach ($data as $k => $v) { if (in_array($k, $allowed, true) && $v !== null && !is_array($v)) { $fields[] = "`$k` = ?"; $vals[] = $v; } }
        if (!$fields) return false;
        $vals[] = $id;
        $stmt = $this->db->prepare("UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($vals);
    }
    public function setNotes(int $productId, array $noteIds): void {
        $this->db->prepare("DELETE FROM product_notes WHERE product_id = ?")->execute([$productId]);
        if ($noteIds) {
            $ins = $this->db->prepare("INSERT IGNORE INTO product_notes (product_id,note_id) VALUES (?,?)");
            foreach ($noteIds as $nid) $ins->execute([$productId, $nid]);
        }
    }
    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    }
}

class VariantRepository extends BaseRepository {
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM variants WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO variants (product_id,size_ml,sku,price,stock,is_active) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$data['product_id'],$data['size_ml'],$data['sku'],$data['price'],$data['stock'],$data['is_active']]);
        return (int)$this->db->lastInsertId();
    }
    public function updateStock(int $id, int $delta): bool {
        return $this->db->prepare("UPDATE variants SET stock = stock + ? WHERE id = ?")->execute([$delta, $id]);
    }
}

class UserRepository extends BaseRepository {
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT u.*, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT u.*, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function create(string $name, string $email, string $hash, int $roleId): int {
        $stmt = $this->db->prepare("INSERT INTO users (name,email,password_hash,role_id) VALUES (?,?,?,?)");
        $stmt->execute([$name,$email,$hash,$roleId]);
        return (int)$this->db->lastInsertId();
    }
    public function updateName(int $id, string $name): bool {
        return $this->db->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $id]);
    }
}

class OrderRepository extends BaseRepository {
    public function create(array $order, array $items): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO orders (customer_id,user_id,subtotal,shipping,total,currency) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$order['customer_id'],$order['user_id'],$order['subtotal'],$order['shipping'],$order['total'],$order['currency']]);
            $id = (int)$this->db->lastInsertId();
            $ins = $this->db->prepare("INSERT INTO order_items (order_id,variant_id,product_name,size_ml,qty,unit_price) VALUES (?,?,?,?,?,?)");
            foreach ($items as $it) {
                $ins->execute([$id, $it['variant_id'], $it['product_name'], $it['size_ml'], $it['qty'], $it['unit_price']]);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $o = $stmt->fetch();
        if (!$o) return null;
        $items = $this->db->prepare("SELECT oi.*, v.sku FROM order_items oi LEFT JOIN variants v ON oi.variant_id = v.id WHERE oi.order_id = ?");
        $items->execute([$id]);
        $o['items'] = $items->fetchAll();
        return $o;
    }
    public function findByUser(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    public function updateStatus(int $id, string $status): bool {
        return $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
    }
}

class ReviewRepository extends BaseRepository {
    public function findByProduct(int $productId): array {
        $stmt = $this->db->prepare("SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO reviews (product_id,user_id,author_name,rating,title,comment) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$data['product_id'],$data['user_id'],$data['author_name'],$data['rating'],$data['title'],$data['comment']]);
        return (int)$this->db->lastInsertId();
    }
}

class ContactRepository extends BaseRepository {
    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO contacts (name,email,subject,message) VALUES (?,?,?,?)");
        $stmt->execute([$data['name'],$data['email'],$data['subject'],$data['message']]);
        return (int)$this->db->lastInsertId();
    }
}

class FaqRepository extends BaseRepository {
    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order");
        return $stmt->fetchAll();
    }
}

class ScentFamilyRepository extends BaseRepository {
    public function findAll(): array {
        return $this->db->query("SELECT * FROM scent_families ORDER BY name")->fetchAll();
    }
}