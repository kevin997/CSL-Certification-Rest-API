-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cfpcwjwg_certification_api_db`;

-- Ensure root has proper permissions from any host
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Create application user with proper permissions
CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'%';

-- Create users for specific Docker network patterns
CREATE USER IF NOT EXISTS 'root'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.%' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.%';

-- Apply changes
FLUSH PRIVILEGES;
