FROM php:8.3-fpm

# Extensions installing
RUN apt-get update && apt-get install -y libzip-dev zlib1g-dev openssl zip unzip
RUN docker-php-ext-install pdo pdo_mysql zip sockets 

# Redis 
RUN pecl update-channels \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.7.4 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies
COPY composer.json ./
RUN composer install --no-scripts

COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize

EXPOSE 8000
CMD php artisan serve --host=0.0.0.0
