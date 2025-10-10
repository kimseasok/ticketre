# syntax=docker/dockerfile:1.6

ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

FROM php:${PHP_VERSION}-fpm-alpine AS php-base
WORKDIR /var/www/html
RUN apk add --no-cache \
    bash \
    freetype-dev \
    git \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite-dev \
    supervisor \
    unzip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install bcmath intl pdo_mysql pdo_pgsql pdo_sqlite zip gd

FROM composer:2 AS composer-dev
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-ansi --no-interaction --no-progress --prefer-dist

FROM composer:2 AS composer-prod
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --classmap-authoritative --no-ansi --no-dev --no-interaction --no-progress --prefer-dist

FROM node:${NODE_VERSION}-alpine AS node-build
WORKDIR /var/www/html
COPY package.json package-lock.json* ./
RUN npm ci
COPY postcss.config.js tailwind.config.js vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php-base AS dependencies
COPY --from=composer-dev /usr/bin/composer /usr/bin/composer
COPY . ./
COPY --from=composer-dev /var/www/html/vendor ./vendor
COPY --from=node-build /var/www/html/public/build ./public/build

FROM dependencies AS tester
ENV APP_ENV=testing
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=:memory:
RUN set -eux; \
    cp .env.testing .env; \
    php artisan config:clear; \
    php artisan test --no-interaction --without-tty

FROM php-base AS runtime
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
COPY --from=composer-prod /usr/bin/composer /usr/bin/composer
COPY . ./
COPY --from=composer-prod /var/www/html/vendor ./vendor
COPY --from=node-build /var/www/html/public/build ./public/build
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN set -eux; \
    addgroup -S laravel && adduser -S laravel -G laravel; \
    chown -R laravel:laravel storage bootstrap/cache; \
    mkdir -p /var/log/supervisor; \
    touch /var/log/supervisor/supervisord.log
USER laravel
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
