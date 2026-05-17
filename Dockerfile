FROM php:8.2-fpm
WORKDIR /var/www
RUN apt-get update && apt-get install -y git curl zip unzip libpng-dev libonig-dev libxml2-dev && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev -o
RUN php artisan config:cache && php artisan route:cache
CMD php artisan serve --host 0.0.0.0 --port $PORT
