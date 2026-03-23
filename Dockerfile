FROM php:8.2-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
    git \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    mariadb-client \
    postgresql-dev \
    unzip \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install \
    bcmath \
    gd \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json vite.config.js ./

RUN npm ci

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

RUN npm run build

FROM php-base AS app

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build
COPY --chmod=755 docker/app/entrypoint.sh /usr/local/bin/app-entrypoint

RUN test -f public/build/manifest.json \
    && mkdir -p \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:alpine AS web

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public ./public
COPY --from=frontend /var/www/html/public/build ./public/build

RUN test -f public/build/manifest.json

EXPOSE 80
