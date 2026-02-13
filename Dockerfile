# ===============================
# PHP 8.4 + Laravel 11 Production
# ===============================

FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zip curl sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

# Permission
RUN chmod -R 777 storage bootstrap/cache

# Expose port
EXPOSE 8000

# Start script
CMD ["sh", "entrypoint.sh"]
