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

# Determine if we should run as queue worker or web server
if [ "$CONTAINER_ROLE" = "queue" ]; then
  echo "Starting queue worker..."
  exec supervisord -c /etc/supervisor/supervisord.conf
else
  # Start PHP-FPM
  echo "Starting PHP-FPM..."
  exec php-fpm
fi
