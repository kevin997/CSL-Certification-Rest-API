-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cfpcwjwg_certification_api_db`;

-- Grant privileges to user from any host
CREATE USER IF NOT EXISTS 'cfpcwjwg_certi_user'@'%' IDENTIFIED BY '#&H3k-ID0V';
GRANT ALL PRIVILEGES ON `cfpcwjwg_certification_api_db`.* TO 'cfpcwjwg_certi_user'@'%';

-- Also grant privileges to root from any host
UPDATE mysql.user SET host='%' WHERE user='root';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Apply changes
FLUSH PRIVILEGES;
