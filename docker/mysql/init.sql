-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cfpcwjwg_certification_api_db`;

-- Drop and recreate user to ensure clean permissions
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'localhost';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'172.%';

-- Create user with proper permissions
CREATE USER 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'%';

-- Ensure root has proper permissions from any host
DROP USER IF EXISTS 'root'@'172.%';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;

-- Apply changes
FLUSH PRIVILEGES;

-- Verify users
SELECT User, Host FROM mysql.user;
