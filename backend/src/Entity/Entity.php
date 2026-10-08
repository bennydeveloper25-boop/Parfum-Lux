<?php
declare(strict_types=1);
namespace App\Entity;

abstract class Entity {
    public int $id = 0;
    public string $created_at = '';
    public string $updated_at = '';
}

class User extends Entity {
    public string $name = '';
    public string $email = '';
    public string $password_hash = '';
    public int $role_id = 2;
    public int $is_active = 1;
}

class Role extends Entity {
    public string $name = '';
    public ?string $description = null;
}

class ScentFamily extends Entity {
    public string $name = '';
    public ?string $description = null;
}

class Note extends Entity {
    public string $name = '';
    public string $type = 'heart';
    public ?string $description = null;
}

class Product extends Entity {
    public string $sku = '';
    public string $name = '';
    public ?string $tagline = null;
    public ?string $description = null;
    public ?int $family_id = null;
    public string $concentration = 'Eau de Parfum';
    public float $price_base = 0;
    public int $is_active = 1;
    public int $is_featured = 0;
    public string $image_emoji = '🌙';
    /** @var int[] */
    public array $note_ids = [];
    /** @var array<int,array{id:int,size_ml:int,sku:string,price:float,stock:int,is_active:int}> */
    public array $variants = [];
}

class Variant extends Entity {
    public int $product_id = 0;
    public int $size_ml = 50;
    public string $sku = '';
    public float $price = 0;
    public int $stock = 0;
    public int $is_active = 1;
}

class Order extends Entity {
    public ?int $customer_id = null;
    public ?int $user_id = null;
    public string $status = 'pending';
    public float $subtotal = 0;
    public float $shipping = 0;
    public float $total = 0;
    public string $currency = 'NIO';
    /** @var array<int,array{product_name:string,size_ml:int,qty:int,unit_price:float}> */
    public array $items = [];
}

class Review extends Entity {
    public int $product_id = 0;
    public ?int $user_id = null;
    public string $author_name = '';
    public int $rating = 5;
    public ?string $title = null;
    public ?string $comment = null;
    public int $is_approved = 1;
}