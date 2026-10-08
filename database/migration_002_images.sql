-- Parfum Lux · Migración 002: imágenes reales (sin emojis) + moneda NIO
-- Ejecutar después de schema.sql / seed.sql. Idempotente.
USE `parfumlux`;

-- Columna image_url si no existe (MySQL 8 sin ADD COLUMN IF NOT EXISTS: chequear vía information_schema con procedure inline)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='parfumlux' AND table_name='products' AND column_name='image_url');
SET @ddl := IF(@col_exists=0, 'ALTER TABLE `products` ADD COLUMN `image_url` VARCHAR(500) NULL DEFAULT NULL AFTER `image_emoji`', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1541643600914-78b084683601?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 1;
UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 2;
UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1594035910387-fea47794261f?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 3;
UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1615634260167-c8cdede054de?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 4;
UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 5;
UPDATE `products` SET `image_url` = 'https://images.unsplash.com/photo-1587017539504-67cfbddac569?q=80&w=800&auto=format&fit=crop', `image_emoji` = NULL WHERE `id` = 6;
