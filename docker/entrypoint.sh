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
if [ "$CONTAINER_ROLE" = "app" ] || [ "$CONTAINER_ROLE" = "queue" ]; then
    echo "Waiting for database connection..."
    RETRY_COUNT=0
    MAX_RETRIES=30
    
    # Add a longer delay to ensure MySQL is fully initialized with all grant tables processed
    echo "Giving MySQL time to fully initialize (30 seconds)..."
    sleep 30
    
    # Get our container IP address for explicit grants
    CONTAINER_IP=$(hostname -i | awk '{print $1}')
    echo "Container IP: $CONTAINER_IP"
    
    set +e
    until nc -z -v -w30 $DB_HOST 3306 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
        echo "Waiting for database connection..."
        sleep 5
        RETRY_COUNT=$((RETRY_COUNT+1))
    done
    
    if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
        echo "Error: Failed to connect to database after $MAX_RETRIES attempts!"
    else
        echo "Database is up and running!"
        
        # First try to create a specific user for our container IP
        echo "Creating a specific user for our container IP: $CONTAINER_IP"
        mysql -h $DB_HOST -u root -p$DB_PASSWORD --connect-timeout=30 -e "CREATE USER IF NOT EXISTS 'root'@'$CONTAINER_IP' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL ON *.* TO 'root'@'$CONTAINER_IP' WITH GRANT OPTION; FLUSH PRIVILEGES;" || echo "Failed to create specific user but continuing..."
        
        # Test MySQL connection with different user combinations
        echo "Testing MySQL connection as root@% user..."
        mysql -h $DB_HOST -u root -p$DB_PASSWORD --connect-timeout=10 -e "SELECT 'Connected as root@%';" || echo "Connection as root@% failed"
        
        echo "Testing MySQL connection as root@$CONTAINER_IP user..."
        mysql -h $DB_HOST -u root -p$DB_PASSWORD --connect-timeout=10 -e "SELECT 'Connected as root@$CONTAINER_IP';" || echo "Connection as root@$CONTAINER_IP failed"
        
        # Try connecting with different host specifications
        echo "Testing MySQL connection using 127.0.0.1..."
        mysql -h 127.0.0.1 -u root -p$DB_PASSWORD --connect-timeout=10 -e "SELECT 'Connected via 127.0.0.1';" || echo "Connection via 127.0.0.1 failed"
        
        # Try connecting using explicit networking options
        echo "Testing MySQL connection with explicit protocol..."
        mysql -h $DB_HOST -u root -p$DB_PASSWORD --protocol=TCP --connect-timeout=10 -e "SELECT 'Connected with TCP protocol';" || echo "Connection with TCP protocol failed"
        
        # Try to fix .env file database settings
        if [ -f "/var/www/html/.env" ]; then
            echo "Updating .env file with correct database connection settings"
            sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/g" /var/www/html/.env
            sed -i "s/DB_USERNAME=.*/DB_USERNAME=root/g" /var/www/html/.env
            sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/g" /var/www/html/.env
        fi
    fi
    set -e
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
