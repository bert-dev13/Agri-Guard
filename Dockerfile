FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    libonig-dev libxml2-dev libpq-dev \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring zip exif pcntl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN npm install && npm run build

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD php artisan optimize:clear && \
    php artisan storage:link || true && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
