#!/bin/sh
# Don't exit immediately on error to allow proper error logging
set +e

# Export temporary directory environment variables to ensure spatie packages use Laravel storage
export TMPDIR=/var/www/html/storage/app/backup-temp
export TEMP=/var/www/html/storage/app/backup-temp
export TMP=/var/www/html/storage/app/backup-temp

# Create log directories
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor
touch /var/log/nginx/access.log /var/log/nginx/error.log
touch /var/log/supervisor/supervisord.log

# Create Laravel storage directories with proper permissions
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Create backup directories for Laravel backup package
mkdir -p /var/www/html/storage/app/private
mkdir -p /var/www/html/storage/app/private/CSL-Certification-Rest-API
mkdir -p /var/www/html/storage/app/backup-temp

# Create all essential log files and ensure they're writable
touch /var/www/html/storage/logs/laravel.log
touch /var/www/html/storage/logs/queue.log
touch /var/www/html/storage/logs/scheduler.log
touch /var/www/html/storage/logs/rds-backup.log
touch /var/www/html/storage/logs/nightwatch.log
touch /var/www/html/storage/logs/order-regularizer.log

# Debug: Verify directory structure exists
echo "=== ENTRYPOINT DEBUG: Verifying storage structure ==="
ls -la /var/www/html/storage/ || echo "ERROR: /var/www/html/storage/ does not exist!"
ls -la /var/www/html/storage/logs/ || echo "ERROR: /var/www/html/storage/logs/ does not exist!"
ls -la /var/www/html/storage/framework/ || echo "ERROR: /var/www/html/storage/framework/ does not exist!"
echo "=== END ENTRYPOINT DEBUG ==="

# Set proper permissions for all storage and bootstrap directories
# Use chmod 777 for mounted volumes to ensure write access regardless of user
chown -R www-data:www-data /var/www/html/storage
chmod -R 777 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/bootstrap/cache

# Ensure backup directories have proper permissions
chown -R www-data:www-data /var/www/html/storage/app/private
chmod -R 777 /var/www/html/storage/app/private
chown -R www-data:www-data /var/www/html/storage/app/backup-temp
chmod -R 777 /var/www/html/storage/app/backup-temp

# Ensure system temp directory is writable by www-data (fallback)
chmod 777 /tmp
chown www-data:www-data /tmp || true

# Double-check specific critical files
touch /var/www/html/storage/logs/laravel.log
chmod 777 /var/www/html/storage/logs/laravel.log

# Check AWS RDS connection
if [ "$CONTAINER_ROLE" = "app" ] || [ "$CONTAINER_ROLE" = "queue" ] || [ "$CONTAINER_ROLE" = "scheduler" ] || [ "$CONTAINER_ROLE" = "nightwatch" ]; then
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

# Run migrations and seed database
if [ "$CONTAINER_ROLE" = "app" ] || [ "$CONTAINER_ROLE" = "queue" ] || [ "$CONTAINER_ROLE" = "scheduler" ] || [ "$CONTAINER_ROLE" = "nightwatch" ]; then
    # Run migrations with error handling
    echo "Running database migrations..."
    if php artisan migrate --force; then
        echo "Migrations completed successfully."
        
        # Seed the database
        echo "Seeding database..."
        if php artisan db:seed --force; then
            echo "Database seeded successfully."
        else
            echo "Warning: Database seeding failed. Some data may be missing."
        fi
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
elif [ "$CONTAINER_ROLE" = "scheduler" ]; then
  echo "Starting scheduler service..."
  # Start cron daemon (Ubuntu style)
  service cron start
  echo "Cron daemon started"
  # Copy scheduler supervisor config
  cp /var/www/html/docker/supervisor/scheduler.conf /etc/supervisor/conf.d/
  exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
elif [ "$CONTAINER_ROLE" = "nightwatch" ]; then
  echo "Starting Nightwatch agent with supervisor..."
  # Copy nightwatch supervisor config
  cp /var/www/html/docker/supervisor/nightwatch.conf /etc/supervisor/conf.d/
  exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
elif [ "$CONTAINER_ROLE" = "reverb" ]; then
  echo "Starting Laravel Reverb WebSocket server..."
  exec php artisan reverb:start --host=$REVERB_HOST --port=$REVERB_PORT
else
  # Start Nginx and PHP-FPM
  echo "Starting Nginx and PHP-FPM..."
  php-fpm -D
  exec nginx -g "daemon off;"
fi
