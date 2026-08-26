#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
i=0
until php -r "new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "PostgreSQL did not become ready in time."
    exit 1
  fi
  sleep 1
done
echo "PostgreSQL is ready."

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --prefer-dist --no-interaction
fi

mkdir -p storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/temp \
  storage/framework/testing \
  storage/logs \
  bootstrap/cache

php artisan config:clear --no-interaction 2>/dev/null || true

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force --no-interaction 2>/dev/null || true
fi

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan storage:link --force --no-interaction 2>/dev/null || true

# PHP-FPM corre como www-data: storage y cache deben ser escribibles
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

echo "Starting PHP-FPM..."
exec "$@"
