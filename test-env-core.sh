#!/bin/bash

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="http://localhost:8000"

# Admin user details
ADMIN_NAME="Admin User"
ADMIN_EMAIL="admin@example.com"
ADMIN_PASSWORD="password123"
ADMIN_DEVICE="admin-device"

# Regular user details
USER_NAME="Regular User"
USER_EMAIL="user@example.com"
USER_PASSWORD="password123"
USER_DEVICE="user-device"

# Environment 1 details
ENV1_NAME="Environment One"
ENV1_DOMAIN="env1.csl-certification.com"
ENV1_EMAIL="user@env1.com"
ENV1_PASSWORD="env1pass123"

# Environment 2 details
ENV2_NAME="Environment Two"
ENV2_DOMAIN="env2.csl-certification.com"
ENV2_EMAIL="user@env2.com"
ENV2_PASSWORD="env2pass123"

echo -e "${BLUE}Testing Environment-Specific Authentication Core Flow${NC}"

# Step 1: Register admin user
echo -e "\n${BLUE}Step 1: Register admin user${NC}"
ADMIN_REGISTER_DATA="{\"name\":\"$ADMIN_NAME\",\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\",\"password_confirmation\":\"$ADMIN_PASSWORD\",\"device_name\":\"$ADMIN_DEVICE\"}"
ADMIN_REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/api/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$ADMIN_REGISTER_DATA")

echo "$ADMIN_REGISTER_RESPONSE" | jq

# Extract admin token and ID
ADMIN_TOKEN=$(echo "$ADMIN_REGISTER_RESPONSE" | jq -r '.token')
ADMIN_ID=$(echo "$ADMIN_REGISTER_RESPONSE" | jq -r '.user.id')

if [ "$ADMIN_TOKEN" == "null" ]; then
  echo -e "${RED}Failed to register admin user${NC}"
  exit 1
fi

echo "Admin ID: $ADMIN_ID"
echo "Admin Token: $ADMIN_TOKEN"

# Step 2: Create Environment 1
echo -e "\n${BLUE}Step 2: Create Environment 1${NC}"
ENV1_DATA="{\"name\":\"$ENV1_NAME\",\"primary_domain\":\"$ENV1_DOMAIN\",\"is_active\":true}"
ENV1_RESPONSE=$(curl -s -X POST "$BASE_URL/api/environments" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "$ENV1_DATA")

echo "$ENV1_RESPONSE" | jq

# Extract environment ID
ENV1_ID=$(echo "$ENV1_RESPONSE" | jq -r '.id')
if [ "$ENV1_ID" == "null" ]; then
  echo -e "${RED}Failed to create Environment 1${NC}"
  exit 1
fi
echo "Environment 1 ID: $ENV1_ID"

# Step 3: Create Environment 2
echo -e "\n${BLUE}Step 3: Create Environment 2${NC}"
ENV2_DATA="{\"name\":\"$ENV2_NAME\",\"primary_domain\":\"$ENV2_DOMAIN\",\"is_active\":true}"
ENV2_RESPONSE=$(curl -s -X POST "$BASE_URL/api/environments" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "$ENV2_DATA")

echo "$ENV2_RESPONSE" | jq

# Extract environment ID
ENV2_ID=$(echo "$ENV2_RESPONSE" | jq -r '.id')
if [ "$ENV2_ID" == "null" ]; then
  echo -e "${RED}Failed to create Environment 2${NC}"
  exit 1
fi
echo "Environment 2 ID: $ENV2_ID"

# Step 4: Register regular user
echo -e "\n${BLUE}Step 4: Register regular user${NC}"
USER_REGISTER_DATA="{\"name\":\"$USER_NAME\",\"email\":\"$USER_EMAIL\",\"password\":\"$USER_PASSWORD\",\"password_confirmation\":\"$USER_PASSWORD\",\"device_name\":\"$USER_DEVICE\"}"
USER_REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/api/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$USER_REGISTER_DATA")

echo "$USER_REGISTER_RESPONSE" | jq

# Extract user token and ID
USER_TOKEN=$(echo "$USER_REGISTER_RESPONSE" | jq -r '.token')
USER_ID=$(echo "$USER_REGISTER_RESPONSE" | jq -r '.user.id')

if [ "$USER_TOKEN" == "null" ]; then
  echo -e "${RED}Failed to register regular user${NC}"
  exit 1
fi

echo "User ID: $USER_ID"
echo "User Token: $USER_TOKEN"

# Step 5: Add user to Environment 1 with environment-specific credentials
echo -e "\n${BLUE}Step 5: Add user to Environment 1 with environment-specific credentials${NC}"
ADD_TO_ENV1_DATA="{\"user_id\":$USER_ID,\"role\":\"learner\",\"use_environment_credentials\":true,\"environment_email\":\"$ENV1_EMAIL\",\"environment_password\":\"$ENV1_PASSWORD\"}"
ADD_TO_ENV1_RESPONSE=$(curl -s -X POST "$BASE_URL/api/environments/$ENV1_ID/users" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "$ADD_TO_ENV1_DATA")

echo "$ADD_TO_ENV1_RESPONSE" | jq

# Step 6: Add user to Environment 2 with different environment-specific credentials
echo -e "\n${BLUE}Step 6: Add user to Environment 2 with different environment-specific credentials${NC}"
ADD_TO_ENV2_DATA="{\"user_id\":$USER_ID,\"role\":\"learner\",\"use_environment_credentials\":true,\"environment_email\":\"$ENV2_EMAIL\",\"environment_password\":\"$ENV2_PASSWORD\"}"
ADD_TO_ENV2_RESPONSE=$(curl -s -X POST "$BASE_URL/api/environments/$ENV2_ID/users" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d "$ADD_TO_ENV2_DATA")

echo "$ADD_TO_ENV2_RESPONSE" | jq

# Step 7: Test login with Environment 1 credentials
echo -e "\n${BLUE}Step 7: Test login with Environment 1 credentials${NC}"
LOGIN_ENV1_DATA="{\"email\":\"$ENV1_EMAIL\",\"password\":\"$ENV1_PASSWORD\",\"device_name\":\"$USER_DEVICE\",\"environment_id\":$ENV1_ID}"
LOGIN_ENV1_RESPONSE=$(curl -s -X POST "$BASE_URL/api/tokens" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$LOGIN_ENV1_DATA")

echo "$LOGIN_ENV1_RESPONSE" | jq

# Extract token
ENV1_TOKEN=$(echo "$LOGIN_ENV1_RESPONSE" | jq -r '.token')
echo "Environment 1 Token: $ENV1_TOKEN"

# Step 8: Test login with Environment 2 credentials
echo -e "\n${BLUE}Step 8: Test login with Environment 2 credentials${NC}"
LOGIN_ENV2_DATA="{\"email\":\"$ENV2_EMAIL\",\"password\":\"$ENV2_PASSWORD\",\"device_name\":\"$USER_DEVICE\",\"environment_id\":$ENV2_ID}"
LOGIN_ENV2_RESPONSE=$(curl -s -X POST "$BASE_URL/api/tokens" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$LOGIN_ENV2_DATA")

echo "$LOGIN_ENV2_RESPONSE" | jq

# Extract token
ENV2_TOKEN=$(echo "$LOGIN_ENV2_RESPONSE" | jq -r '.token')
echo "Environment 2 Token: $ENV2_TOKEN"

# Step 9: Verify user information with Environment 1 token
echo -e "\n${BLUE}Step 9: Verify user information with Environment 1 token${NC}"
if [ "$ENV1_TOKEN" != "null" ]; then
  USER_INFO_ENV1=$(curl -s -X GET "$BASE_URL/api/user" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $ENV1_TOKEN")
  
  echo "$USER_INFO_ENV1" | jq
else
  echo -e "${RED}No valid token for Environment 1${NC}"
fi

# Step 10: Verify user information with Environment 2 token
echo -e "\n${BLUE}Step 10: Verify user information with Environment 2 token${NC}"
if [ "$ENV2_TOKEN" != "null" ]; then
  USER_INFO_ENV2=$(curl -s -X GET "$BASE_URL/api/user" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $ENV2_TOKEN")
  
  echo "$USER_INFO_ENV2" | jq
else
  echo -e "${RED}No valid token for Environment 2${NC}"
fi

# Step 11: Revoke all tokens
echo -e "\n${BLUE}Step 11: Revoke all tokens${NC}"
if [ "$ENV1_TOKEN" != "null" ]; then
  curl -s -X DELETE "$BASE_URL/api/tokens" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $ENV1_TOKEN"
fi

if [ "$ENV2_TOKEN" != "null" ]; then
  curl -s -X DELETE "$BASE_URL/api/tokens" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $ENV2_TOKEN"
fi

curl -s -X DELETE "$BASE_URL/api/tokens" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $USER_TOKEN"

curl -s -X DELETE "$BASE_URL/api/tokens" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN"

echo -e "\n${GREEN}Environment-specific authentication core flow test completed!${NC}"
