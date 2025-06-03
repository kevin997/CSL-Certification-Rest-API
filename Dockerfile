FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install essential dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    netcat-openbsd \
    nginx \
    supervisor \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Copy environment file
COPY .env.staging /var/www/html/.env

# Set permissions - use 777 for mounted volumes to ensure they're writable
RUN mkdir -p /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    && touch /var/www/html/storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/bootstrap/cache

# Install dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Build frontend assets if needed
RUN if [ -f "package.json" ]; then \
    apk add --no-cache nodejs npm \
    && npm ci \
    && npm run build \
    && apk del nodejs npm; \
    fi

# Generate application key
RUN php artisan key:generate

# Copy nginx configuration
RUN mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
COPY docker/nginx/conf.d/app.conf /etc/nginx/sites-available/default
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/
RUN mkdir -p /etc/nginx/conf.d

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Setup cron job for Laravel scheduler
RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" | crontab -

# Expose port 80
EXPOSE 80

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
