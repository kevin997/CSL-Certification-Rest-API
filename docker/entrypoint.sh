#!/bin/sh
# Don't exit immediately on error to allow proper error logging
set +e

# Create log directories
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor
touch /var/log/nginx/access.log /var/log/nginx/error.log
touch /var/log/supervisor/supervisord.log

# Create Laravel storage directories with proper permissions
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Create log files and ensure they're writable
touch /var/www/html/storage/logs/laravel.log

# Set proper permissions for all storage and bootstrap directories
# Use chmod 777 for mounted volumes to ensure write access regardless of user
chown -R www-data:www-data /var/www/html/storage
chmod -R 777 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/bootstrap/cache

# Double-check specific critical files
touch /var/www/html/storage/logs/laravel.log
chmod 777 /var/www/html/storage/logs/laravel.log

# Wait for database to be ready
echo "Waiting for database connection..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
  if nc -z db 3306; then
    echo "Database connection established"
    break
  fi
  echo "Waiting for database connection... ($RETRY_COUNT/$MAX_RETRIES)"
  RETRY_COUNT=$((RETRY_COUNT+1))
  sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
  echo "WARNING: Could not connect to database after $MAX_RETRIES attempts, but continuing startup"
fi

# Run database migrations if needed
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force || echo "WARNING: Database migrations failed but continuing startup"
fi

# Clear and rebuild cache for optimal performance
echo "Optimizing application..."
php artisan optimize:clear || echo "WARNING: Cache clear failed but continuing startup"
php artisan optimize || echo "WARNING: Cache optimization failed but continuing startup"

# Create a simple health check endpoint
echo "Creating health check endpoint..."
mkdir -p /var/www/html/public/health
echo '{"status":"ok","timestamp":"'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'> /var/www/html/public/health/index.json
echo '<?php echo json_encode(["status" => "ok", "timestamp" => date("c")]);' > /var/www/html/public/health/index.php

# Create storage symlink
php artisan storage:link

# Create supervisor logs directory
mkdir -p /var/log/supervisor

# Determine container role
if [ "$CONTAINER_ROLE" = "queue" ]; then
  echo "Starting queue worker..."
  exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
  # Start Nginx and PHP-FPM
  echo "Starting Nginx and PHP-FPM..."
  php-fpm -D
  exec nginx -g "daemon off;"
fi
