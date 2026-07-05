#!/bin/sh
set -e

cd /var/www/kinerja

# Auto install composer dependencies kalau vendor/ belum ada
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Installing composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Auto generate APP_KEY kalau belum ada
if [ -f ".env" ] && ! grep -q "^APP_KEY=base64" .env; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate
fi

# Pastikan folder storage ada
mkdir -p storage/framework/{cache,sessions,views,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Symlink storage kalau belum ada
if [ -f "artisan" ] && [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# Fix permission
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

echo "✅ Laravel ready!"

# Jalankan command utama (php-fpm)
exec "$@"
