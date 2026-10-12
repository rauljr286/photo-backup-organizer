FROM php:8.3-apache

# System dependencies for the PHP extensions we need (GD, Zip).
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        git \
        curl \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd exif pdo_mysql zip \
    && a2enmod rewrite headers \
    && echo 'upload_max_filesize = 64M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 128M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_file_uploads = 200' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 LTS to compile the front-end assets with Vite 7, which requires
# Node 20.19+ or 22.12+ (Node 20 is end-of-life). public/build is git-ignored,
# so the assets must be built here at image build time.
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer (slim image, ~100 MB).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Production dependencies only (see .dockerignore). --no-scripts is required:
# Laravel's "post-autoload-dump" hook runs `php artisan package:discover`, but
# artisan does not exist yet at this point (only composer.json / composer.lock
# have been copied). Packages are discovered further down, after the full
# source tree is in place.
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-scripts

# Install the front-end dependencies, copy the source, then build the assets.
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# With the source now present, regenerate the optimized production autoloader
# and let Laravel discover its packages (writes bootstrap/cache/packages.php,
# used at runtime so Composer is never needed on the server).
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Remove the environment file (values are injected at runtime) and make the
# storage and bootstrap/cache directories writable by the web server.
RUN rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

# Serve the Laravel public directory from Apache and allow .htaccess overrides.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Start-up script (see docker/entrypoint.sh): listens on Railway's PORT,
# prepares the storage tree, runs migrations, links public/storage, serves.
# The `sed` normalises any CRLF line endings so the shebang always works.
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
