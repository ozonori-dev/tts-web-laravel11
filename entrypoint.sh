#!/bin/sh

echo "🚀 Starting Laravel 11..."

# Copy .env if not exists
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Create sqlite database if not exists
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

# Generate APP_KEY if empty
if ! grep -q "APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

# Clear cache
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

# Storage link (ignore if exists)
php artisan storage:link || true

echo "✅ Laravel 11 ready!"

php artisan serve --host=0.0.0.0 --port=8000
