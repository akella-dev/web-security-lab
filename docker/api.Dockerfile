FROM composer:2.9 AS dependencies
WORKDIR /app
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    --ignore-platform-req=ext-pdo_pgsql

FROM php:8.5-cli-alpine AS runtime
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del .build-deps \
    && addgroup -g 10001 -S academy \
    && adduser -u 10001 -S academy -G academy
WORKDIR /app
COPY --from=dependencies --chown=academy:academy /app/vendor ./vendor
COPY --chown=academy:academy api/ ./
USER academy
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
  CMD php -r '$body = @file_get_contents("http://127.0.0.1:8080/healthz"); exit($body === false ? 1 : 0);'
CMD ["php", "-S", "0.0.0.0:8080", "public/index.php"]
