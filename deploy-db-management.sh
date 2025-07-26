#!/bin/bash
# Deploy Database Management Tools

echo "🗄️ Deploying CSL Database Management Tools"
echo "=========================================="
echo

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if docker-compose.yml exists
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ docker-compose.yml not found!${NC}"
    echo "Please run this script from the project root directory."
    exit 1
fi

# Check if .env.staging exists
if [ ! -f ".env.staging" ]; then
    echo -e "${RED}❌ .env.staging not found!${NC}"
    echo "Please ensure .env.staging file exists with database configuration."
    exit 1
fi

echo -e "${BLUE}📋 Pre-deployment Checks${NC}"
echo "-------------------------"

# Check if pgAdmin configuration exists
if [ ! -f "docker/pgadmin/servers.json" ]; then
    echo -e "${YELLOW}⚠️  pgAdmin servers.json not found. Creating directory...${NC}"
    mkdir -p docker/pgadmin
    echo "Please ensure docker/pgadmin/servers.json is created with your PostgreSQL configuration."
fi

# Check if required environment variables are set
echo -n "Checking environment variables... "
if grep -q "PGADMIN_DEFAULT_EMAIL" .env.staging && grep -q "DB_HOST" .env.staging; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ MISSING${NC}"
    echo "Please ensure .env.staging contains PGLADMIN_DEFAULT_EMAIL and database configuration."
    exit 1
fi

echo
echo -e "${BLUE}🚀 Starting Database Management Services${NC}"
echo "----------------------------------------"

# Pull latest images
echo "Pulling latest images..."
docker compose pull phpmyadmin pgadmin

# Start the services
echo "Starting phpMyAdmin and pgAdmin..."
docker compose up -d phpmyadmin pgadmin

# Wait for services to be ready
echo "Waiting for services to start..."
sleep 10

echo
echo -e "${BLUE}🔍 Service Status${NC}"
echo "------------------"

# Check if services are running
if docker ps | grep -q "csl-phpmyadmin"; then
    echo -e "phpMyAdmin: ${GREEN}✅ RUNNING${NC}"
else
    echo -e "phpMyAdmin: ${RED}❌ NOT RUNNING${NC}"
fi

if docker ps | grep -q "csl-pgadmin"; then
    echo -e "pgAdmin: ${GREEN}✅ RUNNING${NC}"
else
    echo -e "pgAdmin: ${RED}❌ NOT RUNNING${NC}"
fi

echo
echo -e "${BLUE}🌐 Access Information${NC}"
echo "----------------------"
echo -e "phpMyAdmin (MySQL): ${GREEN}http://localhost:8091${NC}"
echo -e "pgAdmin (PostgreSQL): ${GREEN}http://localhost:8092${NC}"
echo
echo -e "${BLUE}📋 pgAdmin Login Credentials${NC}"
echo "----------------------------"
echo "Email: admin@csl.com"
echo "Password: csl_admin_2024!"
echo

echo -e "${BLUE}🔧 Testing Connectivity${NC}"
echo "-----------------------"

# Test phpMyAdmin
echo -n "Testing phpMyAdmin... "
if curl -s -f "http://localhost:8091" >/dev/null 2>&1; then
    echo -e "${GREEN}✅ ACCESSIBLE${NC}"
else
    echo -e "${RED}❌ NOT ACCESSIBLE${NC}"
fi

# Test pgAdmin
echo -n "Testing pgAdmin... "
if curl -s -f "http://localhost:8092" >/dev/null 2>&1; then
    echo -e "${GREEN}✅ ACCESSIBLE${NC}"
else
    echo -e "${RED}❌ NOT ACCESSIBLE${NC}"
fi

echo
echo -e "${BLUE}📊 Next Steps${NC}"
echo "==============")
echo "1. Open phpMyAdmin at http://localhost:8091 to manage MySQL databases"
echo "2. Open pgAdmin at http://localhost:8092 to manage PostgreSQL databases"
echo "3. Run './test-db-management.sh' to perform comprehensive tests"
echo "4. Check logs if any issues: docker logs csl-phpmyadmin && docker logs csl-pgadmin"
echo

echo -e "${GREEN}🎉 Database management tools deployment complete!${NC}"
