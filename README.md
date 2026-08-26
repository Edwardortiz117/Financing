# Financing — Panel de Presupuesto

Aplicación Laravel + Blade + Tailwind con PostgreSQL en Docker.

Incluye:
- **Presupuesto planificado** (ingreso + sliders por subcategoría), guardado automático al cambiar
- **Presupuestos por mes** con selector e historial
- **Libro de gastos** (transacciones individuales con fecha)

## Requisitos

- Docker Desktop en ejecución (Docker Compose v2)
- PHP 8.4+ en la imagen (Laravel 13)
- (Opcional en el host) Node.js 22+ para reconstruir assets con Vite

## Arranque rápido

```bash
# 1. Variables de entorno
cp .env.example .env
php artisan key:generate

# 2. Construir assets frontend (necesario porque el código se monta como volumen)
npm install
npm run build

# 3. Levantar contenedores (Nginx + PHP-FPM + PostgreSQL)
docker compose up -d --build
```

La app queda en **http://localhost:8080**

Al iniciar, el contenedor `app` espera a PostgreSQL, ejecuta `migrate` y `db:seed`.

### Solo PostgreSQL (desarrollo local con `php artisan serve`)

```bash
docker compose up -d postgres
# En .env: DB_HOST=127.0.0.1
php artisan migrate --seed
npm run dev
php artisan serve
```

## Libro de gastos y facturas

En el panel puedes:
1. Subir una **factura** (JPG, PNG o PDF).
2. El sistema lee el documento (OCR / texto PDF) y sugiere el **total** en COP.
3. Revisas categoría, fecha y monto, y registras el gasto.
4. La factura queda guardada y disponible con el enlace **Ver factura**.

Requiere Tesseract y Poppler en el contenedor `app` (ya incluidos en el Dockerfile).


## API interna (JSON)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/` | Dashboard del mes actual |
| GET | `/budgets/{year}/{month}` | Dashboard de un mes |
| PUT | `/budgets/{year}/{month}` | Actualiza ingreso + allocations |
| POST | `/budgets/{year}/{month}/transactions` | Crea un gasto |
| DELETE | `/transactions/{id}` | Elimina un gasto |

## Servicios Docker

| Servicio | Puerto | Rol |
|----------|--------|-----|
| `nginx` | 8080 | HTTP |
| `app` | 9000 (interno) | PHP-FPM + Laravel |
| `postgres` | 5432 | PostgreSQL 16 |

Credenciales por defecto: usuario/clave/db `financing`.

## Reconstruir assets

```bash
npm run build
# o
docker compose run --rm node
```

## Estructura de datos

- `categories` / `subcategories` — catálogo de gastos
- `budget_months` — ingreso por año/mes
- `budget_allocations` — montos planificados (sliders)
- `transactions` — gastos reales con fecha

El HTML estático `presupuesto-dashboard.html` se conserva solo como referencia.
