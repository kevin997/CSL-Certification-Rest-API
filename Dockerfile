# Laravel 12.x Docker build for Ubuntu with RDS backup support
FROM ubuntu:22.04

# Prevent interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Set working directory
WORKDIR /var/www/html

# Install software-properties-common first for add-apt-repository
RUN apt-get update && apt-get install -y \
    software-properties-common \
    ca-certificates \
    lsb-release \
    gnupg \
    curl \
    wget \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Add PHP 8.2 repository
RUN add-apt-repository -y ppa:ondrej/php && apt-get update

# Install PHP 8.2 and all required extensions for Laravel 12.x
RUN apt-get install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-common \
    php8.2-mysql \
    php8.2-pgsql \
    php8.2-zip \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-exif \
    php8.2-pcntl \
    php8.2-opcache \
    php8.2-redis \
    php8.2-imagick \
    php8.2-soap \
    php8.2-imap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install system tools for production and RDS backup
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    unzip \
    zip \
    cron \
    netcat-openbsd \
    mysql-client \
    postgresql-client \
    awscli \
    gzip \
    rsync \
    nano \
    htop \
    procps \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x for frontend assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP-FPM for better performance
RUN mkdir -p /run/php \
    && sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/;listen.owner = www-data/listen.owner = www-data/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/;listen.group = www-data/listen.group = www-data/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/;listen.mode = 0660/listen.mode = 0660/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/pm.max_children = 5/pm.max_children = 20/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/pm.start_servers = 2/pm.start_servers = 4/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 2/' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 6/' /etc/php/8.2/fpm/pool.d/www.conf

# Configure PHP settings optimized for Laravel 12.x
RUN echo "memory_limit = 512M" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "upload_max_filesize = 100M" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "post_max_size = 100M" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "max_execution_time = 300" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "max_input_vars = 5000" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "date.timezone = UTC" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.enable = 1" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.memory_consumption = 256" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.interned_strings_buffer = 16" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.max_accelerated_files = 20000" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.revalidate_freq = 2" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.fast_shutdown = 1" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini \
    && echo "opcache.validate_timestamps = 0" >> /etc/php/8.2/fpm/conf.d/99-laravel.ini

# Copy CLI configuration too
RUN cp /etc/php/8.2/fpm/conf.d/99-laravel.ini /etc/php/8.2/cli/conf.d/99-laravel.ini

# Copy application files
COPY . /var/www/html

# Copy environment file
COPY .env.staging /var/www/html/.env

# Create Laravel storage directories with proper permissions
RUN mkdir -p /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/backups/database \
    /var/www/html/bootstrap/cache \
    && touch /var/www/html/storage/logs/laravel.log \
    /var/www/html/storage/logs/queue.log \
    /var/www/html/storage/logs/scheduler.log \
    /var/www/html/storage/logs/rds-backup.log \
    /var/www/html/storage/logs/nightwatch.log \
    /var/www/html/storage/logs/order-regularizer.log

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set proper ownership after copying files
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Build frontend assets if package.json exists
RUN if [ -f "package.json" ]; then \
    npm ci --only=production --silent \
    && npm run build \
    && rm -rf node_modules \
    && npm cache clean --force; \
    fi

# Generate application key and optimize Laravel
RUN php artisan key:generate --no-interaction \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Generate API documentation (L5-Swagger)
RUN php artisan l5-swagger:generate

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/app.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Copy supervisor configurations
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/supervisor/*.conf /etc/supervisor/conf.d/

# Copy and setup scripts
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-scheduler.sh

# Setup cron job for Laravel scheduler (Ubuntu style)
RUN echo "* * * * * www-data cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler \
    && crontab -u www-data /etc/cron.d/laravel-scheduler

# Create system log directories
RUN mkdir -p /var/log/nginx /var/log/supervisor \
    && touch /var/log/nginx/access.log /var/log/nginx/error.log \
    && touch /var/log/supervisor/supervisord.log \
    && chown -R www-data:www-data /var/log/nginx

# Create health check endpoints
RUN mkdir -p /var/www/html/public/health \
    && echo '<?php echo json_encode(["status" => "ok", "timestamp" => time(), "php_version" => phpversion(), "laravel_version" => app()->version()]);' > /var/www/html/public/health/index.php \
    && echo '<?php echo json_encode(["status" => "ok", "queue_workers" => "running"]);' > /var/www/html/public/health/queue.php \
    && chown -R www-data:www-data /var/www/html/public/health

# Create storage symlink
RUN php artisan storage:link

# Set final permissions
RUN find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose ports for different services
EXPOSE 80 8080 2407

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:80/health/index.php || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]