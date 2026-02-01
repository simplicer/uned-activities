# Backend Containerfile for UNED Activities Finder
FROM php:8.3-cli-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    postgresql-client \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY src/ src/
COPY apps/ apps/

# Set permissions
RUN chown -R nobody:nobody /app
USER nobody

# Expose port
EXPOSE 8080

# Run PHP built-in server (for production, use proper PHP-FPM setup)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "apps/HttpApi/public"]
