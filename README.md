# Parfum Lux

Tienda web de perfumería con interfaz adaptable y API propia. Proyecto full stack creado con HTML, CSS, JavaScript, PHP y MySQL.

## Componentes

- Catálogo y panel de administración en el frontend
- API PHP organizada en controladores, servicios, repositorios y entidades
- Autenticación mediante JWT, validación de entradas y limitación de solicitudes
- Esquema de base de datos MySQL en `database/schema.sql`

## Requisitos

- PHP 8+
- MySQL 8+
- Extensiones PHP `pdo_mysql`, `mbstring` y `openssl`

## Inicio local

1. Ejecuta `database/schema.sql` en una base de datos MySQL vacía.
2. Copia `backend/.env.example` como `backend/.env` y configura credenciales propias.
3. Inicia la API desde `backend` con `php -S localhost:8000 -t public`.
4. Sirve los archivos de `frontend` desde un servidor local.

La plantilla no incluye cuentas ni contraseñas predeterminadas. No uses datos de demostración como credenciales reales.
