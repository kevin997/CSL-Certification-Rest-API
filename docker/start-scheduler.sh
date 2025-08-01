#!/bin/sh

# Create backup directories if they don't exist
mkdir -p /var/www/html/storage/app/backups/database

# Install mysqldump if not already installed
if ! command -v mysqldump &> /dev/null; then
    echo "Installing mysqldump..."
    apt-get update && apt-get install -y mysql-client
fi

# Install AWS CLI if not already installed (for potential S3 backup storage)
if ! command -v aws &> /dev/null; then
    echo "Installing AWS CLI..."
    apt-get update && apt-get install -y awscli
fi

# Copy supervisor configuration files
cp /var/www/html/docker/supervisor/rds-backup.conf /etc/supervisor/conf.d/

# Reload supervisor configuration
supervisorctl reread
supervisorctl update

# Start supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
