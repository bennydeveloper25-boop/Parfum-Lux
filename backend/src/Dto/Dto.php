<?php
declare(strict_types=1);
namespace App\Dto;

abstract class BaseDto {
    public static function fromArray(array $data): static {
        $obj = new static();
        foreach ($data as $k => $v) if (property_exists($obj, $k)) $obj->$k = $v;
        return $obj;
    }
    public function toArray(): array { return get_object_vars($this); }
}

class AuthLoginDto extends BaseDto { public string $email = ''; public string $password = ''; }
class AuthRegisterDto extends BaseDto { public string $name = ''; public string $email = ''; public string $password = ''; }
class ProductCreateDto extends BaseDto { public string $sku = ''; public string $name = ''; public ?string $tagline = null; public ?string $description = null; public ?int $family_id = null; public string $concentration = 'Eau de Parfum'; public float $price_base = 0; public bool $is_active = true; public bool $is_featured = false; public string $image_emoji = '🌙'; }
class ProductUpdateDto extends BaseDto { public ?string $name = null; public ?string $tagline = null; public ?string $description = null; public ?int $family_id = null; public ?string $concentration = null; public ?float $price_base = null; public ?bool $is_active = null; public ?bool $is_featured = null; public ?string $image_emoji = null; }
class VariantCreateDto extends BaseDto { public int $product_id = 0; public int $size_ml = 50; public string $sku = ''; public float $price = 0; public int $stock = 50; public bool $is_active = true; }
class OrderCreateDto extends BaseDto { public ?int $customer_id = null; public array $items = []; }
class ReviewCreateDto extends BaseDto { public int $product_id = 0; public int $rating = 5; public ?string $title = null; public ?string $comment = null; }
class ContactCreateDto extends BaseDto { public string $name = ''; public string $email = ''; public ?string $subject = null; public string $message = ''; }