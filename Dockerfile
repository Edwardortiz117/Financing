# ---- Frontend assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- PHP application ----
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev \
    tesseract-ocr tesseract-ocr-spa tesseract-ocr-eng \
    poppler-utils imagemagick \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-scripts --no-autoloader --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php-fpm/zz-env.conf /usr/local/etc/php-fpm.d/zz-env.conf
COPY docker/php-fpm/zz-temp.conf /usr/local/etc/php/conf.d/zz-temp.ini
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/etc/php-fpm.d/zz-env.conf \
    && sed -i 's/\r$//' /usr/local/etc/php/conf.d/zz-temp.ini \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
