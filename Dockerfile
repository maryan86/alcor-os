# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.5
ARG COMPOSER_VERSION=2.8

FROM composer:${COMPOSER_VERSION} AS composer

# --- base: PHP CLI runtime shared by every stage ---
FROM php:${PHP_VERSION}-cli-alpine AS base
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql
WORKDIR /app
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && apk del .build-deps \
    && addgroup -S app && adduser -S -G app app

# --- build: production dependencies, optimized autoloader ---
FROM base AS build
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

# --- dev: dev dependencies + composer; docker compose bind-mounts the source over /app ---
FROM build AS dev
ENV APP_ENV=local \
    APP_DEBUG=true
RUN composer install --prefer-dist --no-interaction --no-progress \
    && chown -R app:app /app
USER app
ENTRYPOINT ["/app/docker/entrypoint.sh"]
# Idle dev container: work happens through `docker compose exec app ...`.
CMD ["sleep", "infinity"]

# --- runtime: thin production-like image without composer and dev tooling (`docker build --target runtime .`) ---
FROM base AS runtime
COPY --from=build --chown=app:app /app /app
USER app
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["php", "artisan", "payroll:demo"]
