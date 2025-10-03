# syntax=docker/dockerfile:1.6

ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

FROM composer:2 AS composer
WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --no-autoloader --prefer-dist --no-interaction

FROM node:${NODE_VERSION}-alpine AS node
WORKDIR /var/www/html
COPY package.json package-lock.json* ./
RUN npm install --omit=dev || true

FROM php:${PHP_VERSION}-fpm-alpine AS php-build
WORKDIR /var/www/html
RUN apk add --no-cache git unzip icu-dev libzip-dev oniguruma-dev libpng-dev libjpeg-turbo-dev freetype-dev
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql bcmath intl zip gd
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader && \
    php artisan config:clear && php artisan route:clear && php artisan view:clear
RUN addgroup -S laravel && adduser -S laravel -G laravel
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache
USER laravel

FROM nginx:1.25-alpine AS nginx
WORKDIR /var/www/html
COPY --from=php-build /var/www/html/public /var/www/html/public
COPY --from=php-build /var/www/html/storage /var/www/html/storage
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

FROM php:${PHP_VERSION}-fpm-alpine AS app
WORKDIR /var/www/html
RUN apk add --no-cache git unzip icu-dev libzip-dev oniguruma-dev libpng-dev libjpeg-turbo-dev freetype-dev supervisor bash
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql bcmath intl zip gd
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=php-build /var/www/html /var/www/html
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN addgroup -S laravel && adduser -S laravel -G laravel
RUN chown -R laravel:laravel /var/www/html
USER laravel
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
