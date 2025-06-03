-- Following pattern from GitHub issue #275 solution

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cfpcwjwg_certification_api_db`;

-- First, remove old user records (clean slate approach)
DROP USER IF EXISTS 'root'@'%';
DROP USER IF EXISTS 'root'@'172.%';
DROP USER IF EXISTS 'root'@'172.24.0.3';
DROP USER IF EXISTS 'root'@'172.25.0.3';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'172.%';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'172.24.0.3';
DROP USER IF EXISTS 'cfpcwjwg_certi_user'@'172.25.0.3';

-- Create root user with wildcard host (critical for Docker networking)
CREATE USER 'root'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Create app user with wildcard host
CREATE USER 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'%';

-- Create users for broader Docker network ranges
CREATE USER 'root'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.%' WITH GRANT OPTION;

CREATE USER 'cfpcwjwg_certi_user'@'172.%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.%';

-- Create IP-specific users for exact container IPs
-- We're covering both the previous IP and the current one
CREATE USER 'root'@'172.24.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.24.0.3' WITH GRANT OPTION;

CREATE USER 'root'@'172.25.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.25.0.3' WITH GRANT OPTION;

CREATE USER 'cfpcwjwg_certi_user'@'172.24.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.24.0.3';

CREATE USER 'cfpcwjwg_certi_user'@'172.25.0.3' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'172.25.0.3';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify users
SELECT user, host FROM mysql.user;
