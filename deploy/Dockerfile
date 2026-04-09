FROM php:8.3-fpm-alpine

# ─── Dependências do sistema ─────────────────────────────────────────────────
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    vim \
    su-exec \
    docker-cli

# ─── Extensões PHP ───────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd \
    zip \
    opcache

# ─── Composer ────────────────────────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── Instalar dependências PHP no build (antes do bind mount) ────────────────
COPY composer.json composer.lock ./

RUN composer install \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist

# ─── Copiar o restante do código (usado em produção; em dev é sobrescrito) ───
COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/app/public \
              storage/framework/cache/data \
              storage/framework/sessions \
              storage/framework/testing \
              storage/framework/views \
              storage/logs \
              bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
