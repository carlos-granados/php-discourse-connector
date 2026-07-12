FROM dunglas/frankenphp:1-php8.5 AS base

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV SERVER_NAME=:8080
WORKDIR /app

FROM base AS dev

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
ENV APP_ENV=dev
