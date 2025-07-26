#!/bin/bash
# Test Database Management Tools

echo "🗄️ CSL Database Management Tools Test"
echo "====================================="
echo

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TESTS_PASSED=0
TESTS_FAILED=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -n "Testing $test_name... "
    
    if eval "$test_command" >/dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ FAIL${NC}"
        ((TESTS_FAILED++))
    fi
}

# Function to check service
check_service() {
    local service_name="$1"
    local port="$2"
    echo -n "Checking $service_name service... "
    
    if docker ps | grep -q "$service_name"; then
        echo -e "${GREEN}✅ RUNNING${NC}"
        ((TESTS_PASSED++))
        
        # Check if port is accessible
        echo -n "  └─ Testing port $port... "
        if curl -s -f "http://localhost:$port" >/dev/null 2>&1; then
            echo -e "${GREEN}✅ ACCESSIBLE${NC}"
            ((TESTS_PASSED++))
        else
            echo -e "${RED}❌ NOT ACCESSIBLE${NC}"
            ((TESTS_FAILED++))
        fi
    else
        echo -e "${RED}❌ NOT RUNNING${NC}"
        ((TESTS_FAILED++))
    fi
}

echo -e "${BLUE}🔧 Container Status${NC}"
echo "-------------------"

# Check if containers are running
check_service "csl-phpmyadmin" "8091"
check_service "csl-pgadmin" "8092"

echo
echo -e "${BLUE}🌐 Network Connectivity${NC}"
echo "------------------------"

# Test RDS connectivity from containers
if docker ps | grep -q "csl-phpmyadmin"; then
    run_test "MySQL RDS connectivity (from phpMyAdmin)" "docker exec csl-phpmyadmin ping -c 1 csl-brands-certification-rest-api-1.clyyomwg2s8k.us-east-2.rds.amazonaws.com"
else
    echo "phpMyAdmin container not running - skipping MySQL RDS test"
    ((TESTS_FAILED++))
fi

if docker ps | grep -q "csl-pgadmin"; then
    run_test "PostgreSQL RDS connectivity (from pgAdmin)" "docker exec csl-pgadmin ping -c 1 database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com"
else
    echo "pgAdmin container not running - skipping PostgreSQL RDS test"
    ((TESTS_FAILED++))
fi

echo
echo -e "${BLUE}📁 Configuration Files${NC}"
echo "-----------------------"

# Check configuration files
run_test "pgAdmin servers.json exists" "[ -f ./docker/pgadmin/servers.json ]"
run_test ".env.staging has PGADMIN config" "grep -q 'PGADMIN_DEFAULT_EMAIL' .env.staging"

echo
echo -e "${BLUE}🔍 Service Health${NC}"
echo "------------------"

# Check service health
if docker ps | grep -q "csl-phpmyadmin"; then
    run_test "phpMyAdmin health check" "docker exec csl-phpmyadmin curl -f http://localhost:80"
fi

if docker ps | grep -q "csl-pgadmin"; then
    run_test "pgAdmin health check" "docker exec csl-pgadmin wget -qO- http://localhost:80/misc/ping"
fi

echo
echo -e "${BLUE}📊 Summary${NC}"
echo "=========="
echo -e "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed! Database management tools are ready.${NC}"
    echo
    echo -e "${BLUE}🚀 Access URLs:${NC}"
    echo "phpMyAdmin (MySQL): http://localhost:8091"
    echo "pgAdmin (PostgreSQL): http://localhost:8092"
    echo
    echo -e "${BLUE}📋 pgAdmin Login:${NC}"
    echo "Email: admin@csl.com"
    echo "Password: csl_admin_2024!"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some tests failed. Please check the issues above.${NC}"
    echo
    echo -e "${BLUE}🔧 Common fixes:${NC}"
    echo "- Start services: docker compose up -d phpmyadmin pgadmin"
    echo "- Check logs: docker logs csl-phpmyadmin && docker logs csl-pgladmin"
    echo "- Verify RDS security groups allow connections"
    echo "- Check network connectivity to RDS endpoints"
    exit 1
fi
