#!/bin/bash
# Script to fix MySQL permissions when containers are running
# Enhanced version for comprehensive Docker IP handling
# Updated: 2025-06-03

# Log function for better output formatting
log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Get container IP addresses and network details
log "Inspecting container network configuration..."
APP_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' csl-certification-rest-api)
QUEUE_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' csl-certification-rest-api-queue)
DB_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' csl-certification-rest-api-db)
NETWORK_PREFIX=$(echo $APP_IP | cut -d'.' -f1-2)

log "Found container IPs:"
log " - App container IP: $APP_IP"
log " - Queue container IP: $QUEUE_IP"
log " - Database container IP: $DB_IP"
log " - Network prefix: $NETWORK_PREFIX.*"

# Connect to MySQL and reconfigure settings for optimal Docker networking
log "Configuring MySQL runtime parameters..."
docker exec csl-certification-rest-api-db mysql -uroot -p"#&H3k-ID0V" -e "
SET GLOBAL skip_name_resolve=0;
SET GLOBAL host_cache_size=0;
"

# Reset and recreate all users with correct permissions
log "Recreating MySQL users with correct permissions..."
docker exec csl-certification-rest-api-db mysql -uroot -p"#&H3k-ID0V" -e "
-- First clean up any existing users
DROP USER IF EXISTS 'root'@'localhost';
DROP USER IF EXISTS 'root'@'%';
DROP USER IF EXISTS 'root'@'$APP_IP';
DROP USER IF EXISTS 'root'@'$QUEUE_IP';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'localhost';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'$APP_IP';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'$QUEUE_IP';

-- Create root users with specific container IPs
CREATE USER 'root'@'localhost' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;

CREATE USER 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

CREATE USER 'root'@'$APP_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'$APP_IP' WITH GRANT OPTION;

CREATE USER 'root'@'$QUEUE_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'$QUEUE_IP' WITH GRANT OPTION;

-- Create application users
CREATE USER 'cfpcwjwg_certi_user'@'localhost' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'localhost';

CREATE USER 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'%';

CREATE USER 'cfpcwjwg_certi_user'@'$APP_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'$APP_IP';

CREATE USER 'cfpcwjwg_certi_user'@'$QUEUE_IP' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'$QUEUE_IP';

-- Create network range users (ensure any container on this network can connect)
CREATE USER 'root'@'$NETWORK_PREFIX.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'$NETWORK_PREFIX.%' WITH GRANT OPTION;

CREATE USER 'cfpcwjwg_certi_user'@'$NETWORK_PREFIX.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON cfpcwjwg_certification_api_db.* TO 'cfpcwjwg_certi_user'@'$NETWORK_PREFIX.%';

-- Apply changes
FLUSH PRIVILEGES;

-- Show configured users
SELECT user, host FROM mysql.user WHERE user IN ('root', 'cfpcwjwg_certi_user');
"

# Create my.cnf with correct settings if it doesn't exist
log "Ensuring my.cnf has correct settings..."
docker exec csl-certification-rest-api-db bash -c "cat > /etc/mysql/conf.d/docker.cnf << 'EOF'
[mysqld]
bind-address = 0.0.0.0
skip-name-resolve = 0
skip-host-cache = 1
host_cache_size = 0
loose-skip-networking = 0
EOF
"

# Restart MySQL to apply changes
log "Restarting MySQL service inside container..."
docker exec csl-certification-rest-api-db bash -c "mysqladmin -uroot -p'#&H3k-ID0V' reload"

# Test connectivity
log "Testing connection from app container..."
docker exec csl-certification-rest-api mysql -h db -uroot -p"#&H3k-ID0V" -e "SELECT 'Connection successful!';" || {
  log "Failed direct connection test. Trying with IP instead of hostname..."
  docker exec csl-certification-rest-api mysql -h $DB_IP -uroot -p"#&H3k-ID0V" -e "SELECT 'Connection successful!';"
}

log "Testing migrations..."
docker exec csl-certification-rest-api php artisan migrate:status || {
  log "Migration check failed. This may need further troubleshooting."
  log "Verifying .env file configuration..."
  docker exec csl-certification-rest-api cat .env | grep -E "DB_HOST|DB_DATABASE|DB_USERNAME"
}

log "Testing app user connection..."
docker exec csl-certification-rest-api mysql -h db -u cfpcwjwg_certi_user -p"#&H3k-ID0V" -e "SELECT 'App user connection successful!';"

log "MySQL permission fix script completed"
log "If you're still experiencing issues, please run: docker exec -it csl-certification-rest-api-db bash -c 'mysql -uroot -p"#&H3k-ID0V" -e "SELECT user, host FROM mysql.user WHERE user IN (\'root\', \'cfpcwjwg_certi_user\')"'"
