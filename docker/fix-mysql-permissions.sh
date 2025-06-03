#!/bin/bash
# Script to fix MySQL permissions when containers are running
# This can be executed from the host to create the necessary users for any container IP

# Get container IP addresses
APP_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' csl-certification-rest-api-app)
QUEUE_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' csl-certification-rest-api-queue)

echo "Found container IPs:"
echo "App container IP: $APP_IP"
echo "Queue container IP: $QUEUE_IP"

# Connect to MySQL and fix permissions
echo "Creating MySQL users for container IPs..."
docker exec csl-certification-rest-api-db mysql -uroot -p"#&H3k-ID0V" -e "
-- Create root users with specific container IPs
CREATE USER IF NOT EXISTS 'root'@'$APP_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'$APP_IP' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'root'@'$QUEUE_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'$QUEUE_IP' WITH GRANT OPTION;

-- Create app users with specific container IPs
CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'$APP_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'$APP_IP';

CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'$QUEUE_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'$QUEUE_IP';

-- Make sure root user has full privileges from anywhere
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Make sure app user has full privileges on its database from anywhere
CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'%';

-- Apply changes
FLUSH PRIVILEGES;

-- Show configured users
SELECT user, host FROM mysql.user WHERE user IN ('root', 'cfpcwjwg_certi_user');
"

echo "MySQL users created successfully."
echo "Testing connection from app container..."
docker exec csl-certification-rest-api-app mysql -h db -uroot -p"#&H3k-ID0V" -e "SELECT 'Connection successful!';"

echo "Testing migrations..."
docker exec csl-certification-rest-api-app php artisan migrate:status
