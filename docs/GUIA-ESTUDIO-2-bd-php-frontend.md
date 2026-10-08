# Guía Parte 2/3 — BD + PHP + Frontend archivo por archivo

## 6. MySQL desde cero + schema + seed

SQL mínimo:
```sql
CREATE DATABASE parfumlux CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE parfumlux;
INSERT INTO products (sku,name,price_base) VALUES ('PL-X','Demo',100);
SELECT p.*, sf.name AS family_name FROM products p LEFT JOIN scent_families sf ON p.family_id=sf.id WHERE p.is_active=1;
UPDATE users SET password_hash='...' WHERE email='admin@parfumlux.com';
DELETE FROM products WHERE id=99;
```
Tipos: INT UNSIGNED, VARCHAR(n), TEXT, DECIMAL(10,2) (dinero exacto), ENUM, TIMESTAMP, JSON. Claves: PRIMARY KEY, UNIQUE (email, sku), FOREIGN KEY CASCADE/SET NULL, CHECK rating 1-5. utf8mb4 = tildes bien.

17 tablas: roles, permissions, role_permissions (N:N), users (->roles), scent_families, notes (top|heart|base), products (->families, image_emoji NULL, image_url 500), product_notes (N:N), variants (->products), customers (->users), orders (customer?,user?,status,subtotal,shipping,total,NIO), order_items (->orders CASCADE), payments, reviews (CHECK 1-5), contacts, faqs, audit_log (JSON meta).

seed: roles admin/cliente + 6 permisos (admin todos, cliente read+write), 2 usuarios (hashes se regeneran), 5 familias (Luz Floral, Noche Oriental, Aire Cítrico, Bosque Profundo, Piel Almizcle), 10 notas, 6 perfumes NIO (Lumière 5350, Nuit 7750, Rosée Noir 5830, Aube 3620, Forêt 6820, Peau 4870) con image_url Unsplash, product_notes por nombre, variantes 30/50/100 (ej. LUM 3620/5350/7750), 5 FAQs (envío C$3000, BAC/Lafise, Masaya), 3 reseñas. migration_002: ADD image_url si falta (vía information_schema + PREPARE) + 6 UPDATEs. Orden correcto: schema -> migración -> seed.

## 7. PHP desde cero (mapeado)

`<?php declare(strict_types=1);` + `$var` + `echo`. Arrays `[]`, `['a'=>1]`. `if/foreach/function`. `$_GET $_POST $_SERVER`. `try/catch(Throwable)`. Clases: `namespace App\Core; class X { public function m(): array }`, `new X()`, `->`, `self::`, `static`. `require` + `spl_autoload_register` (qué hace index.php). PDO: `prepare("... ? ...")->execute([$v])` + `fetch/fetchAll` + `beginTransaction/commit/rollBack` (ver Repository/Order). `password_hash/password_verify` (Auth). `json_encode/decode`, `base64_encode`, `hash_hmac`, `hash_equals` (JWT). `header()`, `http_response_code()`, `exit` (Response/CORS). `preg_replace/explode/str_starts_with` (env, router). `file_get_contents('php://input')`, `getallheaders()`, `is_file/copy/mkdir` (db-setup, fix). `time(), date('c'), bin2hex(random_bytes(8))` (ids).

Lee en este orden: config/database.php (array+función) -> Core/Response (función+exit) -> Core/Validator (loops) -> Core/Logger (ficheros) -> Core/Request (superglobales+JSON) -> Core/Router (regex) -> Core/Auth (hash) -> Core/Database (PDO) -> Middleware (uno) -> Repository (un método) -> Service -> Controller.

## 8. Frontend archivo por archivo

**index.html**: `<!DOCTYPE html><html lang=es><head>` meta charset/viewport, title, description, theme-color #1A1D28, Google Fonts (Cormorant Garamond display + Jost UI), `css/styles.css?v=...`, favicon `assets/Logo1.png`. Header `.top`: brand (img Logo1 + Parfum Lux) + nav (Destacados/Colección/Maison/Contacto) + lupa/bolsa(badge)/cuenta. Main: hero (foto real Unsplash + glow + eyebrow Masaya + H1 + sub C$ + lede + 2 CTAs + perks envío C$3000/muestras/contra entrega), `#featured` (grid JS), `#products` (filtros familia/orden + grid JS), `#about` (filosofía Lux=luz/lujo + facts), `#contact` (form name/email/subject/message + note). Footer `.footer` (#1A1D28): brand Logo2, 3 navs, bottom `© 2026 Masaya + Creado por BennyDeveloper`. Modal (backdrop+close+body JS), toasts, `<script type=module src=js/app.js?v=...>`. Sin emojis en HTML.

**css/styles.css**: `:root` paleta `--nuit:#1A1D28 --terre:#29251B --gris:#5E6069 --sable:#7E7867 --lavande:#6D7AA8 --blanc --doré`, fuentes `--display/--sans`, radius/transitions. Reset, body gradiente, `.cap`, `.sr`. Header sticky blur. `.brand` nowrap + logo 42px cover (antes círculo oscuro que lo invisibilizaba). Hero grid 2col, `.hero-photo` tarjeta rotada con img 520px cover + badge C$, `.hero-glow`. Botones primary (gradiente nuit->terre) y ghost. Sections, toolbar selects, `.product-card` (img cover + hover scale, flag Destacado, family/name/tagline/conc/price C$), about, contact inputs focus lavanda, footer grid, modal animaciones, toasts, loader. Responsive: 1000px 1col, 900px nav oculta + header 64px (esto arregló tu solape PARFUM/DESTACADOS), 700px modal 1col.

**js/app.js**: `API_BASE=http://localhost:8000/api`. `$`,`$$`, `toast()`, `formatPrice` es-NI NIO (C$), `api(path,opts)` fetch JSON + throw mensaje. Estado products/families/cart(localStorage pl_cart)/selectedVariant. `init`: badge+families+products+featured+products+events. `loadFamilies` llena select. `loadProducts` GET /products?limit=100. `renderFeatured` (is_featured 3), `renderProducts` (filtro familia + sort). `cardHTML` usa `<img image_url>` + flag + conc + precio C$ (cero emojis; fallback iniciales). `openModal` GET /products/id -> `renderModal` (img, familia, notas top/heart/base tags, variantes botones, precio, añadir/seguir). Variantes cambian precio. `addToCart` junta por variant_id, `saveCart`, `updateBadge` pop. Contact POST /contact. Anclas smooth + Escape cierra modal + SW register. Versión `?v=` para tumbar caché (tus lunas eran JS viejo).

**sw.js**: install cachea shell, activate limpia, fetch: si cross-origin o /api/ pasa red; si no, cache-first + actualiza. No rompe API.
