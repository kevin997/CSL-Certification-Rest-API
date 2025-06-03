#!/bin/sh
set -e

# Create log directories
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor
touch /var/log/nginx/access.log /var/log/nginx/error.log
touch /var/log/supervisor/supervisord.log

# Create Laravel storage directories
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Create log files
touch /var/www/html/storage/logs/laravel.log

# Set proper permissions for all storage and bootstrap directories
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap
chmod -R 775 /var/www/html/bootstrap

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
