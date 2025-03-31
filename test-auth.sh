#!/bin/bash

# Test Authentication Script for CSL Certification Platform

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8000"

# Default values
EMAIL="test@example.com"
PASSWORD="password"
DEVICE_NAME="test-device"
ENVIRONMENT_ID=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -e|--email)
      EMAIL="$2"
      shift 2
      ;;
    -p|--password)
      PASSWORD="$2"
      shift 2
      ;;
    -d|--device)
      DEVICE_NAME="$2"
      shift 2
      ;;
    -env|--environment)
      ENVIRONMENT_ID="$2"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

echo -e "${BLUE}Testing Authentication for CSL Certification Platform${NC}"
echo "Email: $EMAIL"
echo "Device: $DEVICE_NAME"
if [ -n "$ENVIRONMENT_ID" ]; then
  echo "Environment ID: $ENVIRONMENT_ID"
  LOGIN_DATA="{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"device_name\":\"$DEVICE_NAME\",\"environment_id\":$ENVIRONMENT_ID}"
else
  echo "Standard authentication (no environment)"
  LOGIN_DATA="{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"device_name\":\"$DEVICE_NAME\"}"
fi

echo -e "\n${BLUE}Step 1: Login and get token${NC}"
TOKEN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/tokens" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$LOGIN_DATA")

echo "$TOKEN_RESPONSE" | jq .

# Extract token
TOKEN=$(echo "$TOKEN_RESPONSE" | jq -r '.token')

if [ "$TOKEN" == "null" ]; then
  echo -e "${RED}Failed to get token${NC}"
  exit 1
fi

echo -e "\n${GREEN}Token obtained: $TOKEN${NC}"

echo -e "\n${BLUE}Step 2: Get user information${NC}"
USER_RESPONSE=$(curl -s -X GET "$BASE_URL/api/user" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

echo "$USER_RESPONSE" | jq .

echo -e "\n${BLUE}Step 3: Get current environment${NC}"
ENV_RESPONSE=$(curl -s -X GET "$BASE_URL/api/current-environment" \
  -H "Accept: application/json" \
  -H "Host: test.example.com" \
  -H "Authorization: Bearer $TOKEN")

echo "$ENV_RESPONSE" | jq .

echo -e "\n${BLUE}Step 4: Revoke token${NC}"
REVOKE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/api/tokens" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

echo "$REVOKE_RESPONSE" | jq .

echo -e "\n${BLUE}Step 5: Verify token is revoked${NC}"
VERIFY_RESPONSE=$(curl -s -X GET "$BASE_URL/api/user" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

echo "$VERIFY_RESPONSE" | jq .

if [[ "$VERIFY_RESPONSE" == *"Unauthenticated"* ]]; then
  echo -e "\n${GREEN}Authentication test completed successfully!${NC}"
else
  echo -e "\n${RED}Token was not properly revoked!${NC}"
fi
