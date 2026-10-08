# Guía Parte 3/3 — Trazas, API, recetas, errores y plan 14 días

## 9. Cómo viaja una petición (3 trazas)

A) GET /api/products?limit=2: navegador/fetch -> php -S router.php (no es fichero -> index.php) -> CORS global -> Router matchea GET /api/products -> middlewares Logging+RateLimit -> ProductController::list (limit min(2,100)) -> ProductService::list -> ProductRepository::findAll (JOIN families, LIMIT/OFFSET) -> PDO MySQL -> array -> Response::ok {success,data} -> JSON -> app.js pinta cards con <img>.

B) POST /api/auth/login {email,pass}: preflight OPTIONS -> 204 (sin esto CORS bloquea). POST -> AuthController valida email/pass -> AuthService::login (findByEmail JOIN roles + password_verify + is_active) -> si mal 401; si bien Auth::issue JWT -> {token,user}. Frontend lo guardaría en memoria/localStorage y lo manda como `Authorization: Bearer`.

C) POST /api/orders {items:[{variant_id,qty,unit_price}]}: optional auth -> OrderController calcula subtotal/shipping(C$3000?0:150)/total NIO -> OrderService::create (chequea stock por variante, transacción inserta order+items, descuenta stock) -> si stock corto 400 STOCK; si bien 201 con pedido+items. Front: addToCart junta carrito localStorage y al checkout hace este POST.

## 10. API (base http://localhost:8000/api, JSON, Bearer)

- GET /health -> {status ok}. GET /products?limit&offset. GET /products/featured. GET /products/{id} (+notes+variants). POST/PUT/DELETE /products (admin). POST /variants (admin). POST /orders (optional; calcula NIO), GET /orders/{id} (dueño/admin), GET /orders (míos), PUT /orders/{id} (admin status). GET /products/{pid}/reviews. POST /reviews (optional). POST /contact. GET /faqs. GET /families. POST /auth/login|register, GET /auth/me.
- Errores: VALIDATION 422+details, UNAUTHORIZED 401, FORBIDDEN 403, NOT_FOUND 404, CONFLICT 409 email, STOCK 400, RATE_LIMITED 429, SERVER_ERROR 500. Rate 120/min/IP. X-Request-Id en logs.
- curl: login, `curl /products?limit=6`, crear pedido guest y con `Authorization: Bearer TOKEN`.

## 11. Recetas

Nuevo campo de producto: schema ALTER + Entity + Repository create/update + DTO + Controller validación + seed + frontend card/modal. Nuevo endpoint: Entity? + Repository método + Service + Controller método + ruta en index.php con middleware + probar curl + pintar en app.js. Nueva página: sección en index.html + estilos + si pide API, fetch. Cambiar precio/envío: seed + Controller Order. Cambiar foto: migration UPDATE image_url.

## 12. Errores que ya tuvimos (léelos así)

- ERR_CONNECTION_REFUSED :8000 = servidor apagado -> fix/start-api.bat.
- Class HealthController not found = autoloader cargaba Dto.php para todo -> mapa por prefijo.
- DB_CONNECTION could not find driver + php.ini (none) = crear ini desde development + extension=pdo_mysql.
- DB_CONNECTION Access denied = DB_PASS mal. Unknown database = crear/importar.
- Stmt Unknown column image_url = seed antes que migración -> orden schema->mig->seed.
- Lunas en vez de fotos = JS viejo en caché -> `?v=` + Ctrl+F5/incógnito (API ya daba image_url bien).
- Logo invisible/encimado = círculo oscuro sobre PNG negro + letter-spacing -> recuadro claro + nowrap + nav oculta 900px.
- CORS No Allow-Origin = OPTIONS daba 404 sin cabeceras -> CORS global + 204 en index.php.
- mysqli already loaded = ini duplicado, inofensivo.
- 404 Logo1.png = assets no copiado -> db-setup/fix lo copian; server root debe ser frontend/.

## 13. Glosario + plan 14 días

API, REST, JSON, CORS, preflight OPTIONS, JWT (header.payload.firma), hash bcrypt (password_hash/verify, nunca reversible), PDO + prepared (anti-inyección), Singleton, DTO, Entity/Model, Repository, Service/UseCase, Controller, Middleware, Validator, Serializer/Deserializer, Rate limiting/throttling, Observabilidad/logs, utf8mb4/collation, FK CASCADE/SET NULL, UNIQUE 409, Transacción commit/rollback, localStorage, Service Worker, responsive.

Días 1-2 entorno+comandos+arrancar las 3 piezas. 3-4 SQL+Workbench (SELECT/JOIN/INSERT/UPDATE). 5-6 PHP básico + Request/Response/Validator. 7 Router+Middleware+Auth JWT (login con curl). 8 Repository+Service+Controller (traza products). 9 Orders (transacción+stock). 10 HTML semántico + CSS paleta + responsive. 11 JS fetch/filtros/modal/carrito. 12 Añade un campo y un endpoint tú solo. 13 Rompe y arregla cada error del §12 a propósito. 14 Explica en voz alta una traza completa sin mirar.
