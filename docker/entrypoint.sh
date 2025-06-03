#!/bin/sh
set -e

# Wait for database to be ready
echo "Waiting for database connection..."
while ! nc -z db 3306; do
  sleep 1
done
echo "Database connection established"

# Run database migrations if needed
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force
fi

# Clear and rebuild cache for optimal performance
echo "Optimizing application..."
php artisan optimize:clear
php artisan optimize

# Create storage directory structure if it doesn't exist
echo "Setting up storage directories..."
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
chown -R www-data:www-data /var/www/html/storage

# Create nginx logs directory
mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log

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
