FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN cp .env.example .env || echo "APP_KEY=" > .env
RUN composer install --no-dev --optimize-autoloader
RUN php artisan key:generate
RUN php artisan storage:link || true

RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Railway بيستخدم PORT environment variable تلقائياً
ENV PORT=8080
EXPOSE 8080

CMD php artisan serve --host=0.0.0.0 --port=$PORT
