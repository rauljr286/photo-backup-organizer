#!/bin/sh
set -e

# Railway injects a PORT variable that the container must listen on. Fall back
# to 80 when it is unset (for example with a plain local `docker run`).
PORT="${PORT:-80}"

# Point Apache at that port: ports.conf and the default virtual host both ship
# hard-coded to 80.
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/^<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# A Railway Volume is mounted at runtime and comes up owned by root, so the
# storage tree (including the volume) and the cache directory must exist and be
# writable before PHP touches them. This is done on every start, not at build
# time, precisely because the volume replaces the image's storage contents.
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Keep the schema in sync with the deployed code.
php artisan migrate --force

# Run the scheduler in the background; Apache stays the foreground process.
php artisan schedule:work > /dev/null 2>&1 &

exec apache2-foreground
