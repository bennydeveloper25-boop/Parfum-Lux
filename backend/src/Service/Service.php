<?php
declare(strict_types=1);
namespace App\Service;

use App\Repository;

class ProductService {
    private Repository\ProductRepository $repo;
    private Repository\VariantRepository $vRepo;
    public function __construct() {
        $this->repo = new Repository\ProductRepository();
        $this->vRepo = new Repository\VariantRepository();
    }
    public function list(int $limit = 50, int $offset = 0): array { return $this->repo->findAll($limit, $offset); }
    public function featured(int $limit = 6): array { return $this->repo->findFeatured($limit); }
    public function get(int $id): ?array {
        $p = $this->repo->findById($id);
        if (!$p) return null;
        $p['notes'] = $this->repo->getNotes($id);
        $p['variants'] = $this->repo->getVariants($id);
        return $p;
    }
    public function create(array $data, array $noteIds): int {
        $this->repo->db->beginTransaction();
        try {
            $id = $this->repo->create($data);
            $this->repo->setNotes($id, $noteIds);
            $this->repo->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->repo->db->rollBack(); throw $e; }
    }
    public function update(int $id, array $data, ?array $noteIds = null): bool {
        $this->repo->db->beginTransaction();
        try {
            $ok = $this->repo->update($id, $data);
            if ($noteIds !== null) $this->repo->setNotes($id, $noteIds);
            $this->repo->db->commit();
            return $ok;
        } catch (\Throwable $e) { $this->repo->db->rollBack(); throw $e; }
    }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function createVariant(array $data): int { return $this->vRepo->create($data); }
}

class AuthService {
    private Repository\UserRepository $repo;
    public function __construct() { $this->repo = new Repository\UserRepository(); }
    public function login(string $email, string $password): ?array {
        $user = $this->repo->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        if (!$user['is_active']) return null;
        return ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
    }
    public function register(string $name, string $email, string $password, int $roleId = 2): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->repo->create($name, $email, $hash, $roleId);
    }
    public function me(int $id): ?array { return $this->repo->findById($id); }
}

class OrderService {
    private Repository\OrderRepository $repo;
    private Repository\VariantRepository $vRepo;
    public function __construct() { $this->repo = new Repository\OrderRepository(); $this->vRepo = new Repository\VariantRepository(); }
    public function create(array $order, array $items): int {
        // Validate stock
        foreach ($items as $it) {
            $v = $this->vRepo->findById($it['variant_id']);
            if (!$v || $v['stock'] < $it['qty']) throw new \RuntimeException('Stock insuficiente para ' . $it['product_name']);
        }
        $id = $this->repo->create($order, $items);
        // Decrement stock
        foreach ($items as $it) $this->vRepo->updateStock($it['variant_id'], -$it['qty']);
        return $id;
    }
    public function get(int $id): ?array { return $this->repo->findById($id); }
    public function listByUser(int $userId): array { return $this->repo->findByUser($userId); }
    public function updateStatus(int $id, string $status): bool { return $this->repo->updateStatus($id, $status); }
}

class ReviewService {
    private Repository\ReviewRepository $repo;
    public function __construct() { $this->repo = new Repository\ReviewRepository(); }
    public function list(int $productId): array { return $this->repo->findByProduct($productId); }
    public function create(array $data): int { return $this->repo->create($data); }
}

class ContactService {
    private Repository\ContactRepository $repo;
    public function __construct() { $this->repo = new Repository\ContactRepository(); }
    public function create(array $data): int { return $this->repo->create($data); }
}

class FaqService {
    private Repository\FaqRepository $repo;
    public function __construct() { $this->repo = new Repository\FaqRepository(); }
    public function list(): array { return $this->repo->findAll(); }
}

class ScentFamilyService {
    private Repository\ScentFamilyRepository $repo;
    public function __construct() { $this->repo = new Repository\ScentFamilyRepository(); }
    public function list(): array { return $this->repo->findAll(); }
}