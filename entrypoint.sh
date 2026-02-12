#!/bin/sh

echo "🚀 Starting Laravel 11..."

# Ensure .env exists
if [ ! -f .env ]; then
    echo "📄 Creating .env file..."
    cp .env.example .env
fi

# Ensure sqlite database exists
if [ ! -f database/database.sqlite ]; then
    echo "🗄 Creating SQLite database..."
    mkdir -p database
    touch database/database.sqlite
fi

# Fix permissions
chmod -R 775 storage bootstrap/cache
chmod -R 777 database

# Generate APP_KEY if empty
if [ -z "$(grep ^APP_KEY= .env | cut -d '=' -f2)" ]; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate --force
fi

# Clear config & cache
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link || true

echo "✅ Laravel 11 ready!"

php artisan serve --host=0.0.0.0 --port=8000
