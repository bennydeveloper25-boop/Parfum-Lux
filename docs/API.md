# Parfum Lux API — Documentación

Base URL: `http://localhost:8000/api`
Content-Type: `application/json`
Auth: Bearer JWT (header `Authorization: Bearer <token>`)
CORS: habilitado para `FRONTEND_ORIGIN` (configurable en `.env`)

## Convención de respuesta
```json
{ "success": true, "data": ..., "meta": {} }
```
Error:
```json
{ "success": false, "error": { "code": "ERROR_CODE", "message": "...", "details": {} } }
```

## Rate Limiting
120 req/min por IP (configurable `RATE_LIMIT_MAX` / `RATE_LIMIT_WINDOW`). Exceder → `429 RATE_LIMITED`.

## Request ID
Cada respuesta incluye cabecera `X-Request-Id` para trazabilidad.

---

### Health
`GET /api/health` → `{ status: "ok", service: "Parfum Lux API", time: "..." }`

---

### Auth
| Método | Ruta | Auth | Body | Descripción |
|---|---|---|---|---|
| POST | `/api/auth/login` | — | `{email, password}` | Devuelve `{ token, user }` |
| POST | `/api/auth/register` | — | `{name, email, password}` | Crea cliente, devuelve token |
| GET | `/api/auth/me` | ✓ | — | Usuario actual (completo) |
| PUT | `/api/auth/profile` | ✓ | `{name}` | Cambiar mi nombre |

Roles: `admin`, `cliente`.

---

### Productos
| Método | Ruta | Auth | Query / Body |
|---|---|---|---|
| GET | `/api/products` | — | `limit` (≤100), `offset` |
| GET | `/api/products/featured` | — | — |
| GET | `/api/products/{id}` | — | — |
| POST | `/api/products` | admin | `{sku,name,tagline?,description?,family_id?,concentration?,price_base,is_active?,is_featured?,image_emoji?,note_ids?:int[]}` |
| PUT | `/api/products/{id}` | admin | campos opcionales + `note_ids?` |
| DELETE | `/api/products/{id}` | admin | — |

Respuesta producto (detail):
```json
{
  "id":1,"sku":"PL-LUMIERE-EDP","name":"Lumière","tagline":"La luz hecha perfume",
  "description":"...","family_id":1,"family_name":"Luz Floral",
  "concentration":"Eau de Parfum","price_base":145,"is_featured":1,
  "image_emoji":"🌅",
  "notes":[{"id":4,"name":"Rosa de Damasco","type":"heart","description":"..."}],
  "variants":[{"id":1,"size_ml":30,"sku":"PL-LUM-30","price":98,"stock":60}, ...]
}
```

---

### Variantes
| Método | Ruta | Auth | Body |
|---|---|---|---|
| POST | `/api/variants` | admin | `{product_id,size_ml,sku,price,stock?,is_active?}` |

---

### Pedidos
| Método | Ruta | Auth | Body / Params |
|---|---|---|---|
| POST | `/api/orders` | opcional | `{customer_id?, items:[{variant_id,product_name,size_ml,qty,unit_price}]}` |
| GET | `/api/orders/{id}` | opcional (dueño/admin) | — |
| GET | `/api/orders` | ✓ (cliente) | — (mis pedidos) |
| PUT | `/api/orders/{id}` | admin | `{status: pending|paid|preparing|shipped|delivered|cancelled}` |

Cálculo automático: `subtotal = Σ unit_price*qty`, `shipping = 0 si subtotal≥80 else 4.90`, `total = subtotal+shipping`. Descuenta stock al crear.

---

### Reseñas
| Método | Ruta | Auth | Body |
|---|---|---|---|
| GET | `/api/products/{product_id}/reviews` | — | — |
| POST | `/api/reviews` | opcional | `{product_id,rating(1-5),author_name,title?,comment?}` |

---

### Contacto
`POST /api/contact` — `{name,email,subject?,message}` (queda en `contacts` + `audit_log`).

### Newsletter y búsquedas (todo queda en BD)
| Método | Ruta | Guarda en |
|---|---|---|
| POST | `/api/newsletter` `{email,name?}` | `newsletter_subscribers` + `audit_log` |
| POST | `/api/searches` `{q}` | `audit_log` entidad `search` |

---

### FAQs
`GET /api/faqs` — lista pública.

---

### Familias olfativas
`GET /api/families` — lista pública.

---

### Administración (rol admin, Bearer requerido)
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/admin/stats` | Contadores, ingresos C$, stock bajo, recientes, 7 días |
| GET | `/api/admin/orders?status=&limit=&offset=` | Todos los pedidos con líneas |
| GET | `/api/admin/contacts` | Mensajes recibidos |
| PUT | `/api/admin/contacts/{id}` | Marcar leído |
| GET | `/api/admin/users` | Usuarios + rol |
| GET | `/api/admin/reviews` | Todas las reseñas |
| PUT | `/api/admin/reviews/{id}` | Alternar visible/oculto |
| GET | `/api/admin/audit` | Auditoría |

Panel web: `http://localhost:5500/admin/` (login `admin@parfumlux.com / Admin123*`).

---

### Códigos de error comunes
| Código | HTTP | Causa |
|---|---|---|
| `VALIDATION` | 422 | Body inválido (ver `details`) |
| `UNAUTHORIZED` | 401 | Token faltante/expirado |
| `FORBIDDEN` | 403 | Rol insuficiente |
| `NOT_FOUND` | 404 | Recurso no existe |
| `CONFLICT` | 409 | Email duplicado |
| `STOCK` | 400 | Stock insuficiente |
| `RATE_LIMITED` | 429 | Límite superado |
| `SERVER_ERROR` | 500 | Excepción no controlada |

---

### Ejemplos curl
```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@parfumlux.com","password":"Admin123*"}'

# Listar productos
curl http://localhost:8000/api/products?limit=6

# Crear pedido (guest)
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"items":[{"variant_id":2,"product_name":"Lumière","size_ml":50,"qty":1,"unit_price":145}]}'

# Pedido autenticado
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"items":[{"variant_id":2,"product_name":"Lumière","size_ml":50,"qty":1,"unit_price":145}]}'
```

---

### Regenerar hashes de contraseña (seed)
```bash
php -r "echo password_hash('Admin123*', PASSWORD_DEFAULT), PHP_EOL;"
php -r "echo password_hash('Cliente123*', PASSWORD_DEFAULT), PHP_EOL;"
```
Actualiza en MySQL:
```sql
UPDATE users SET password_hash='<hash_admin>' WHERE email='admin@parfumlux.com';
UPDATE users SET password_hash='<hash_cliente>' WHERE email='cliente@parfumlux.com';
```