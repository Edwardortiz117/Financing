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

## Uso

1. Abre el panel: el mes actual se crea con categorías semilla (valores del dashboard original).
2. Cambia el **ingreso** o los **sliders**: se guardan en la base (~400 ms de debounce).
3. Usa el **selector de mes** (o flechas) para navegar historial; un mes nuevo se crea solo al abrirlo.
4. En **Libro de gastos**, registra montos con fecha; se asocian al mes de esa fecha.

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
