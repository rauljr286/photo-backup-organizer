FROM php:8.3-apache

# System dependencies for the PHP extensions we need (GD, Zip, PostgreSQL).
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd exif pdo_mysql pdo_pgsql zip \
    && a2enmod rewrite headers \
    && echo 'upload_max_filesize = 64M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 128M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_file_uploads = 200' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && rm -rf /var/lib/apt/lists/*

# Composer (slim image, ~100 MB).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application source (see .dockerignore).
COPY . .

# Production dependencies only. Compiled front-end assets in public/build are
# committed to the repo, so no Node.js is required here.
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

# Serve the Laravel public directory from Apache.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

EXPOSE 80

# Run migrations, link public/storage (avatars / public disk), then serve.
CMD ["sh", "-c", "php artisan migrate --force && php artisan storage:link && apache2-foreground"]