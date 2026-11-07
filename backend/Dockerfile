# Obraz aplikacji: FrankenPHP (Caddy + PHP 8.3) w jednym kontenerze.
# Etap "base" służy też do uruchamiania composera podczas pracy lokalnej.
FROM dunglas/frankenphp:1-php8.3 AS base

RUN install-php-extensions pdo_pgsql intl opcache zip

RUN setcap -r /usr/local/bin/frankenphp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=8080
WORKDIR /app

COPY docker/Caddyfile /etc/frankenphp/Caddyfile

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s \
    CMD curl -fsS "http://127.0.0.1:${PORT}/api/health" || exit 1

# ---------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev \
    APP_DEBUG=1

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php-dev.ini $PHP_INI_DIR/conf.d/zz-app.ini

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Najpierw same zależności – warstwa cache'owana między zmianami kodu
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN rm -f .env.local .env.*.local \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && php bin/console cache:clear \
    && php bin/console cache:warmup \
    && chmod +x docker/entrypoint.sh

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
