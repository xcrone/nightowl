# Builds the api/ Laravel app (used by both the `agent` and `api` compose
# services — one runs `nightowl:agent`, the other serves the JSON API).
#
# Build context must be the repo root (not ./api): api/ consumes agent/ via
# a local Composer path repository with a symlink. `composer install` runs
# INSIDE this build, after copying both agent/ and api/ source, so the
# symlink it creates points at an absolute path that actually exists in the
# image (a symlink baked in from a host `composer install` would point at
# the host's filesystem path and be dangling here).
FROM composer:2 AS composer

FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev sqlite-dev icu-dev icu-data-full $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pdo_sqlite pcntl posix intl \
    && apk del $PHPIZE_DEPS

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /build
COPY agent/ ./agent
COPY api/ ./api

WORKDIR /build/api
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN php artisan config:clear

EXPOSE 2407 2409 8000
EXPOSE 2408/udp

CMD ["php", "artisan", "nightowl:agent", "--host=0.0.0.0"]
