# backend/Dockerfile
FROM php:8.3-fpm-alpine
 
# Instala dependências do sistema
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    vim
 
# Instala extensões PHP necessárias
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd \
    zip \
    opcache
 
# Instala o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
 
# Define o diretório de trabalho
WORKDIR /var/www/html
 
# Permissões corretas para o storage do Laravel
RUN chown -R www-data:www-data /var/www/html
 
# Expõe a porta do PHP-FPM
EXPOSE 9000
 
CMD ["php-fpm"]


