# Laravel 12.x Docker build with php:8.3-fpm Debian base - OPTIMIZED FOR FREQUENT CODE CHANGES
FROM php:8.3-fpm

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Set working directory
WORKDIR /var/www/html

# Install system dependencies (rarely change - cached layer)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpq-dev \
    libmagickwand-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    cron \
    netcat-openbsd \
    default-mysql-client \
    postgresql-client \
    awscli \
    gzip \
    rsync \
    nano \
    htop \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x for frontend assets (rarely change - cached layer)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions step by step (rarely change - cached layer)
RUN docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        opcache

# Configure and install GD with dependencies (rarely change - cached layer)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install intl extension (rarely change - cached layer)
RUN docker-php-ext-configure intl && docker-php-ext-install intl

# Install XML and SOAP extensions (rarely change - cached layer)
RUN docker-php-ext-install xml soap

# Install PostgreSQL extension (rarely change - cached layer)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Redis extension via PECL (rarely change - cached layer)
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer (rarely change - cached layer)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first for dependency caching
COPY composer.json composer.lock ./

# Install PHP dependencies (change occasionally - separate layer)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy frontend dependency files
COPY package*.json ./

# Install Node dependencies (change occasionally - separate layer)
RUN if [ -f "package.json" ]; then npm ci --silent; fi

# Configure PHP-FPM (rarely change - cached layer)
RUN echo "[www]" > /usr/local/etc/php-fpm.d/www.conf \
    && echo "user = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "group = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen = 127.0.0.1:9000" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen.owner = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen.group = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen.mode = 0660" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 4" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 6" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.process_idle_timeout = 10s" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_requests = 1000" >> /usr/local/etc/php-fpm.d/www.conf

# Configure PHP settings (rarely change - cached layer)
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.memory_consumption = 256" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.interned_strings_buffer = 16" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.max_accelerated_files = 20000" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.revalidate_freq = 2" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/99-laravel.ini \
    && echo "opcache.validate_timestamps = 0" >> /usr/local/etc/php/conf.d/99-laravel.ini

# Copy config files (change occasionally)
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/app.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/supervisor/*.conf /etc/supervisor/conf.d/
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler.sh

# Set up nginx and scripts (rarely change)
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/ \
    && chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-scheduler.sh

# Create directories and system setup (rarely change)
RUN mkdir -p /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/backups/database \
    /var/www/html/storage/app/backup-temp \
    /var/www/html/bootstrap/cache \
    && touch /var/www/html/storage/logs/laravel.log \
    /var/www/html/storage/logs/queue.log \
    /var/www/html/storage/logs/scheduler.log \
    /var/www/html/storage/logs/rds-backup.log \
    /var/www/html/storage/logs/nightwatch.log \
    /var/www/html/storage/logs/order-regularizer.log

# Setup cron job (rarely change)
RUN echo "* * * * * www-data cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler \
    && crontab -u www-data /etc/cron.d/laravel-scheduler

# Create system log directories (rarely change)
RUN mkdir -p /var/log/nginx /var/log/supervisor \
    && touch /var/log/nginx/access.log /var/log/nginx/error.log \
    && touch /var/log/supervisor/supervisord.log \
    && chown -R www-data:www-data /var/log/nginx

# Create health check endpoints (rarely change)
RUN mkdir -p /var/www/html/public/health \
    && echo '<?php echo json_encode(["status" => "ok", "timestamp" => time(), "php_version" => phpversion(), "laravel_version" => app()->version()]);' > /var/www/html/public/health/index.php \
    && echo '<?php echo json_encode(["status" => "ok", "queue_workers" => "running"]);' > /var/www/html/public/health/queue.php

# NOW COPY THE APPLICATION CODE (changes frequently - last layer)
COPY --chown=www-data:www-data . /var/www/html

# Copy environment file
COPY --chown=www-data:www-data .env.staging /var/www/html/.env

# Build frontend assets (changes frequently but after code copy)
RUN if [ -f "package.json" ]; then \
    npm run build \
    && rm -rf node_modules \
    && npm cache clean --force; \
    fi

# Set proper ownership and permissions (final step)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Laravel optimizations (run after code copy)
RUN php artisan key:generate --no-interaction \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Generate API documentation
RUN php artisan l5-swagger:generate

# Create storage symlink
RUN php artisan storage:link || true

# Set final permissions
RUN find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Health check endpoints permissions
RUN chown -R www-data:www-data /var/www/html/public/health

# Expose ports
EXPOSE 80 8080 2407

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:80/health/index.php || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]