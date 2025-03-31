#!/bin/bash

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="http://localhost:8000"
EMAIL="test-api@example.com"
PASSWORD="password"
DEVICE_NAME="test-api-device"

echo -e "${BLUE}Testing API Authentication for CSL Certification Platform${NC}"
echo "Email: $EMAIL"
echo "Device: $DEVICE_NAME"

# Test Register
echo -e "\n${BLUE}Step 1: Register a new user${NC}"
REGISTER_DATA="{\"name\":\"API Test User\",\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\",\"device_name\":\"$DEVICE_NAME\"}"
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/api/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$REGISTER_DATA")

echo "$REGISTER_RESPONSE" | jq

# Extract token from registration response
TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.token')
echo "Token obtained: $TOKEN"

# Test Forgot Password
echo -e "\n${BLUE}Step 2: Test forgot password${NC}"
FORGOT_PASSWORD_DATA="{\"email\":\"$EMAIL\"}"
FORGOT_PASSWORD_RESPONSE=$(curl -s -X POST "$BASE_URL/api/forgot-password" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$FORGOT_PASSWORD_DATA")

echo "$FORGOT_PASSWORD_RESPONSE" | jq

# Test Reset Password (this would normally require a token from email)
echo -e "\n${BLUE}Step 3: Test reset password (this will fail without a valid token)${NC}"
RESET_PASSWORD_DATA="{\"email\":\"$EMAIL\",\"password\":\"newpassword\",\"password_confirmation\":\"newpassword\",\"token\":\"dummy-token\"}"
RESET_PASSWORD_RESPONSE=$(curl -s -X POST "$BASE_URL/api/reset-password" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$RESET_PASSWORD_DATA")

echo "$RESET_PASSWORD_RESPONSE" | jq

# Revoke token
echo -e "\n${BLUE}Step 4: Revoke token${NC}"
REVOKE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/api/tokens" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

echo "$REVOKE_RESPONSE" | jq

echo -e "\n${GREEN}API Authentication test completed!${NC}"
