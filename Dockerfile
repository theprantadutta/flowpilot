# syntax=docker/dockerfile:1

# FlowPilot images, built from one file:
#   app  PHP-FPM with the application. The same image runs the queue workers,
#        the scheduler and migrations with a different command.
#   web  nginx serving the built assets and handing PHP requests to "app".
#
#   docker compose build   (see compose.yml)

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24
ARG NGINX_VERSION=1.29

FROM node:${NODE_VERSION}-alpine AS node

# ------------------------------------------------------------------------------
# PHP with the extensions FlowPilot uses: PostgreSQL, intl (money and number
# formatting), pcntl (so workers stop cleanly) and OPcache.
# ------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS php

RUN apk add --no-cache icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl \
    && { php -m | grep -qi '^Zend OPcache$' || docker-php-ext-install opcache; } \
    && apk del .build-deps \
    && rm -rf /tmp/* /usr/src/php*

WORKDIR /var/www/html

# ------------------------------------------------------------------------------
# Production Composer dependencies, then the application with an optimized
# autoloader.
# ------------------------------------------------------------------------------
FROM php AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-interaction

# ------------------------------------------------------------------------------
# Frontend build. Wayfinder generates the typed routes by booting the app, so
# this stage has PHP and the code as well as Node. The throwaway SQLite
# database only exists so the app can boot; it is not part of any image.
# ------------------------------------------------------------------------------
FROM vendor AS assets

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

ENV APP_NAME=FlowPilot \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/build.sqlite \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    VITE_APP_NAME=FlowPilot

RUN touch /tmp/build.sqlite \
    && php artisan migrate --force --no-interaction \
    && npm run build \
    && rm -f /tmp/build.sqlite

# ------------------------------------------------------------------------------
# app: the PHP image that runs in production.
# ------------------------------------------------------------------------------
FROM php AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-flowpilot.ini"
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-flowpilot.conf
# FPM already runs as www-data, so the pool's user/group lines only add warnings.
RUN sed -i -e 's/^user = /;user = /' -e 's/^group = /;group = /' /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /usr/local/bin/flowpilot-entrypoint

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && ln -sfn ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/flowpilot-entrypoint

USER www-data

EXPOSE 9000

ENTRYPOINT ["flowpilot-entrypoint"]
CMD ["php-fpm"]

# ------------------------------------------------------------------------------
# web: nginx in front of "app". Traefik terminates TLS and routes to port 8080.
# ------------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /var/www/html/public /var/www/html/public
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

# Uploaded logos and avatars come from the shared storage volume.
RUN ln -sfn ../storage/app/public /var/www/html/public/storage

EXPOSE 8080
