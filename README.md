# API de Gestión de Pedidos

API REST para gestionar usuarios, productos, pedidos y líneas de pedido.

## Stack

- Laravel 13
- PHP 8.3+
- Laravel Sanctum
- SQLite

## Requisitos

- PHP 8.3 o superior con `pdo_sqlite`
- Composer 2

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

En Windows PowerShell, sustituye el segundo comando por:

```powershell
Copy-Item .env.example .env
```

El proyecto usa SQLite y no necesita Docker ni servicios externos.

## Ejecución

```bash
php artisan serve
```

La API estará disponible en `http://localhost:8000/api`.

## Datos de prueba

El seeder crea usuarios y productos de ejemplo:

| Email | Contraseña | Uso |
|---|---|---|
| `demo@mobius.test` | `password` | Usuario principal |
| `sd@mobius.test` | `password` | Comprobar acceso no autorizado |

## Autenticación

Los endpoints protegidos requieren:

```http
Authorization: Bearer <token>
```

El token se obtiene mediante `/api/register` o `/api/login`.

## Endpoints

| Método | Endpoint | Auth | Descripción |
|---|---|:---:|---|
| `POST` | `/api/register` | No | Registrar usuario y obtener token |
| `POST` | `/api/login` | No | Iniciar sesión y obtener token |
| `POST` | `/api/logout` | Sí | Revocar el token actual |
| `GET` | `/api/products` | No | Listar productos |
| `POST` | `/api/orders` | Sí | Crear un pedido |
| `GET` | `/api/orders` | Sí | Listar pedidos del usuario autenticado |
| `GET` | `/api/orders/{id}` | Sí | Ver un pedido con sus líneas y productos |
| `PUT` | `/api/orders/{id}/cancel` | Sí | Cancelar un pedido pendiente |

Las rutas de detalle y cancelación comprueban que el pedido pertenece al usuario autenticado.

## Ejemplos de peticiones

### Registrar usuario

```json
{
  "name": "Sergio",
  "email": "sergio@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!"
}
```

### Iniciar sesión

```json
{
  "email": "demo@mobius.test",
  "password": "password"
}
```

### Crear pedido

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

`unit_price`, `subtotal` y `total` se calculan en el servidor a partir del precio actual del producto.

## Reglas principales

- El stock no puede ser insuficiente para crear un pedido.
- El stock se descuenta al crear el pedido y se restaura al cancelarlo.
- Solo se pueden cancelar pedidos `pending`.
- Los pedidos de otros usuarios devuelven `403`.
- Los errores de validación y negocio devuelven `422`.
- Las respuestas no exponen contraseñas ni otros datos sensibles.

## Tests

```bash
php artisan test
```

## Postman

Importa la colección [Mobius-Orders.postman_collection.json](postman/Mobius-Orders.postman_collection.json).

Ejecuta las peticiones en este orden:

1. Register
2. Login
3. List products
4. Create order
5. List my orders
6. Get order detail
7. Cancel order
8. Logout

La colección guarda automáticamente el token, el producto y el pedido creados durante la prueba.
