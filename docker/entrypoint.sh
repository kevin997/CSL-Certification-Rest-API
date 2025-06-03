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

# Check AWS RDS connection
if [ "$CONTAINER_ROLE" = "app" ] || [ "$CONTAINER_ROLE" = "queue" ]; then
    echo "Checking AWS RDS connection..."
    RETRY_COUNT=0
    MAX_RETRIES=15
    
    # Get container IP address for logging purposes
    CONTAINER_IP=$(hostname -i | awk '{print $1}')
    echo "Container IP: $CONTAINER_IP"
    
    set +e
    until nc -z -v -w10 $DB_HOST 3306 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
        echo "Waiting for AWS RDS connection... attempt $((RETRY_COUNT+1))/$MAX_RETRIES"
        sleep 3
        RETRY_COUNT=$((RETRY_COUNT+1))
    done
    
    if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
        echo "Error: Failed to connect to AWS RDS after $MAX_RETRIES attempts!"
        echo "Please verify AWS RDS endpoint, security groups, and credentials."
    else
        echo "AWS RDS connection established!"
        
        # Test MySQL connection with app user
        echo "Testing MySQL connection as certi_user..."
        mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD --connect-timeout=10 -e "SELECT 'Connected to AWS RDS successfully!';" || \
        echo "Connection to AWS RDS failed. Check credentials and network access."
    fi
fi

# Check if database is empty and needs initialization
if [ "$CONTAINER_ROLE" = "app" ] || [ "$CONTAINER_ROLE" = "queue" ]; then
    echo "Checking if database needs initialization..."
    TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT COUNT(TABLE_NAME) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}';" -sN)
    
    if [ "$TABLE_COUNT" = "0" ] || [ -z "$TABLE_COUNT" ]; then
        echo "Database is empty. Initializing with SQL dump..."
        
        # Check if SQL dump exists
        if [ -f "/var/www/html/docker/cfpcwjwg_certification_api_db.sql" ]; then
            echo "Importing database from SQL dump..."
            mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < /var/www/html/docker/cfpcwjwg_certification_api_db.sql
            
            if [ $? -eq 0 ]; then
                echo "Database initialized successfully from SQL dump."
            else
                echo "Error: Failed to import SQL dump!"
            fi
        else
            echo "Warning: SQL dump file not found at /var/www/html/docker/cfpcwjwg_certification_api_db.sql"
        fi
    else
        echo "Database already contains $TABLE_COUNT tables. Skipping initialization."
    fi
    
    # Run migrations with error handling (will be safe now with our MigrationHelper checks)
    echo "Running database migrations..."
    if php artisan migrate --force; then
        echo "Migrations completed successfully."
    else
        echo "Warning: Migrations failed. Application may not function correctly."
    fi
fi

# Clear and optimize for better performance
echo "Clearing and optimizing cache..."
if php artisan optimize:clear && php artisan optimize; then
    echo "Cache cleared and optimized successfully."
else
    echo "Warning: Cache optimization failed."
fi

# Create a static health check endpoint for Docker healthchecks
mkdir -p /var/www/html/public/health
echo '<?php echo json_encode(["status" => "ok", "timestamp" => time()]);' > /var/www/html/public/health/index.php

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
