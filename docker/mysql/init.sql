-- Drop all existing users except localhost (to start clean)
DROP USER IF EXISTS 'root'@'%';
DROP USER IF EXISTS 'root'@'172.%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'172.%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'localhost';

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cfpcwjwg_certification_api_db`;

-- Create users with exact IPs for Docker containers
-- Create root users with wildcard hosts
CREATE USER 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Create root users with specific Docker subnet patterns
CREATE USER 'root'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.%' WITH GRANT OPTION;

-- Create app users with wildcard hosts
CREATE USER 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'%';

-- Create app users with specific Docker subnet patterns
CREATE USER 'cfpcwjwg_certi_user'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.%';

-- Add specific IP for the current container network
-- This should match the IP from the error message
CREATE USER 'root'@'172.24.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.24.0.3' WITH GRANT OPTION;

CREATE USER 'cfpcwjwg_certi_user'@'172.24.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.24.0.3';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify users
SELECT user, host FROM mysql.user;
