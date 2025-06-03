#!/bin/bash
# Script to initialize AWS RDS database with schema and initial data
# Created for CSL-Certification-Rest-API
#
# This script is called during the deployment workflow to:
# 1. Verify connection to the AWS RDS instance
# 2. Create the database if it doesn't exist
# 3. Import the initial schema and data from cfpcwjwg_certification_api_db.sql
# 4. Verify that tables were successfully created
#
# Usage: ./initialize-rds.sh [PASSWORD]
# Environment variables:
#   DB_HOST - AWS RDS endpoint (default: database-1.ccr2s68cu8xf.us-east-1.rds.amazonaws.com)
#   DB_USER - Database username (default: certi_user)
#   DB_PASSWORD - Database password (can be provided as first argument)
#   DB_NAME - Database name (default: cfpcwjwg_certification_api_db)

# Default values - override with environment variables or command line args
DB_HOST=${DB_HOST:-database-1.ccr2s68cu8xf.us-east-1.rds.amazonaws.com}
DB_USER=${DB_USER:-certi_user}
DB_PASSWORD=${1:-"#&H3k-ID0V"}  # Can be provided as first argument
DB_NAME=${DB_NAME:-cfpcwjwg_certification_api_db}

echo "=== AWS RDS Database Initialization ==="
echo "This script will initialize the $DB_NAME database on $DB_HOST"
echo "Using credentials: $DB_USER:$DB_PASSWORD"

# Test connection to AWS RDS
echo -n "Testing connection to AWS RDS... "
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 'Connection successful!';" &> /dev/null; then
    echo "SUCCESS"
else
    echo "FAILED"
    echo "Could not connect to AWS RDS with provided credentials."
    echo "Please check your connection details and try again."
    exit 1
fi

# Create database if it doesn't exist
echo -n "Creating database if it doesn't exist... "
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" &> /dev/null
if [ $? -eq 0 ]; then
    echo "SUCCESS"
else
    echo "FAILED"
    echo "Failed to create database. Check user privileges."
    exit 1
fi

# Import schema and data from SQL file
echo "Importing database schema and initial data..."
if [ -f "cfpcwjwg_certification_api_db.sql" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < cfpcwjwg_certification_api_db.sql
    if [ $? -eq 0 ]; then
        echo "Database schema and data imported successfully!"
    else
        echo "Failed to import database schema and data."
        exit 1
    fi
else
    echo "SQL file not found: cfpcwjwg_certification_api_db.sql"
    echo "Please make sure the SQL file is in the current directory."
    exit 1
fi

# Verify database tables were created
echo -n "Verifying database tables... "
TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT COUNT(TABLE_NAME) FROM information_schema.tables WHERE table_schema='$DB_NAME';" -N)
if [ "$TABLE_COUNT" -gt 0 ]; then
    echo "SUCCESS ($TABLE_COUNT tables found)"
else
    echo "FAILED (No tables found)"
    exit 1
fi

echo "=== AWS RDS Database Initialization Complete ==="
echo "Your Laravel application should now be able to connect to the AWS RDS instance."
echo "Remember to update your .env file with the correct database credentials."
