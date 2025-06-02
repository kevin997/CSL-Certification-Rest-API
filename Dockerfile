# Multi-stage build for Laravel application

# Stage 1: Build frontend assets
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Composer dependencies
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
# Copy all necessary files for composer install
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY routes/ routes/
COPY resources/ resources/
# Install dependencies with more verbose output to diagnose issues
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --verbose

# Stage 3: Final PHP image
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install PHP extensions and dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    netcat-openbsd \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Copy built assets from node stage
COPY --from=node-builder --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Copy composer dependencies
COPY --from=composer-builder --chown=www-data:www-data /app/vendor /var/www/html/vendor

# Generate optimized autoloader
COPY --from=composer-builder /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --optimize

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to non-root user
USER www-data

# Create directory for supervisor configs
RUN mkdir -p /etc/supervisor/conf.d

# Copy supervisor configuration
COPY --chown=root:root docker/supervisor/queue.conf /etc/supervisor/conf.d/

# Copy entrypoint script
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Make sure the entrypoint script is executable
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 9000
EXPOSE 9000

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
