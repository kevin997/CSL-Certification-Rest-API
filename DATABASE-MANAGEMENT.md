# CSL Database Management Setup

This document describes the database management tools integrated into the CSL Certification REST API Docker Compose setup.

## 🗄️ Database Infrastructure

### Primary Databases

#### 1. MySQL RDS - Certification API Database
- **Host**: `csl-brands-certification-rest-api-1.clyyomwg2s8k.us-east-2.rds.amazonaws.com`
- **Port**: `3306`
- **Database**: `cfpcwjwg_certification_api_db`
- **Username**: `certi_user`
- **Password**: `#&H3k-ID0V`
- **Purpose**: Main Laravel application database

#### 2. PostgreSQL RDS - Sales Database
- **Host**: `database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com`
- **Port**: `5432`
- **Database**: `csl_sales_db`
- **Username**: `postgres`
- **Password**: `1217w5w7j735J2===`
- **Purpose**: Sales and analytics data

## 🛠️ Management Tools

### phpMyAdmin (MySQL Management)
- **URL**: http://localhost:8091
- **Container**: `csl-phpmyadmin`
- **Purpose**: Web-based MySQL database administration
- **Features**:
  - Direct connection to MySQL RDS
  - Database browsing and editing
  - SQL query execution
  - Import/Export functionality
  - User management

**Access Details**:
- Pre-configured to connect to MySQL RDS
- No additional login required (auto-connected)
- Upload limit: 1GB
- Memory limit: 1GB
- Max execution time: 600 seconds

### pgAdmin (PostgreSQL Management)
- **URL**: http://localhost:8092
- **Container**: `csl-pgadmin`
- **Purpose**: Web-based PostgreSQL database administration
- **Features**:
  - Direct connection to PostgreSQL RDS
  - Database browsing and editing
  - SQL query execution
  - Import/Export functionality
  - User and role management

**Access Details**:
- **Email**: `admin@csl.com`
- **Password**: `csl_admin_2024!`
- Pre-configured server connection to PostgreSQL RDS
- Server mode disabled for simplified access

## 🚀 Quick Start

### 1. Start All Services
```bash
# Start the entire stack including database management tools
docker compose up -d

# Or start only the database management tools
docker compose up -d phpmyadmin pgadmin
```

### 2. Access Management Interfaces

#### phpMyAdmin (MySQL)
1. Open http://localhost:8091
2. You'll be automatically connected to the MySQL RDS instance
3. Browse databases, tables, and execute queries

#### pgAdmin (PostgreSQL)
1. Open http://localhost:8092
2. Login with:
   - **Email**: `admin@csl.com`
   - **Password**: `csl_admin_2024!`
3. The PostgreSQL RDS server will be pre-configured and available

### 3. Verify Connections

#### Test MySQL Connection
```bash
# Check phpMyAdmin container logs
docker logs csl-phpmyadmin

# Test direct connection
docker exec -it csl-phpmyadmin curl -f http://localhost:80
```

#### Test PostgreSQL Connection
```bash
# Check pgAdmin container logs
docker logs csl-pgadmin

# Test direct connection
docker exec -it csl-pgadmin wget -qO- http://localhost:80/misc/ping
```

## 🔧 Configuration Details

### Environment Variables

The following environment variables control the database management tools:

```bash
# pgAdmin Configuration
PGADMIN_DEFAULT_EMAIL=admin@csl.com
PGADMIN_DEFAULT_PASSWORD=csl_admin_2024!

# Database Connection Details (from .env.staging)
DB_HOST=csl-brands-certification-rest-api-1.clyyomwg2s8k.us-east-2.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=cfpcwjwg_certification_api_db
DB_USERNAME=certi_user
DB_PASSWORD="#&H3k-ID0V"

SALES_DATABASE_URL="postgresql://postgres:1217w5w7j735J2===@database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com:5432/csl_sales_db?schema=public&sslmode=require"
```

### Pre-configured Servers

#### pgAdmin Servers Configuration
The PostgreSQL server is automatically configured via `/docker/pgadmin/servers.json`:

```json
{
  "Servers": {
    "1": {
      "Group": "CSL Production Databases",
      "Name": "CSL Sales Database (PostgreSQL RDS)",
      "Host": "database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com",
      "Port": 5432,
      "MaintenanceDB": "csl_sales_db",
      "Username": "postgres",
      "Password": "1217w5w7j735J2===",
      "SSLMode": "require",
      "Favorite": true
    }
  }
}
```

## 🔒 Security Considerations

### Production Recommendations

1. **Change Default Passwords**:
   ```bash
   # Update in .env.staging
   PGADMIN_DEFAULT_PASSWORD=your_secure_password_here
   ```

2. **Restrict Network Access**:
   - Use firewall rules to limit access to ports 8091 and 8092
   - Consider VPN or bastion host access for production

3. **SSL/TLS Configuration**:
   - Both tools support SSL connections to RDS
   - PostgreSQL connection uses `sslmode=require`
   - MySQL connection can be configured for SSL

4. **User Management**:
   - Create specific database users with limited privileges
   - Avoid using root/admin accounts for routine operations

### Network Security

The management tools are isolated in the `csl-network` Docker network:
- Internal communication only
- No direct database exposure
- Controlled port mapping

## 📊 Monitoring and Health Checks

Both services include health checks:

### phpMyAdmin Health Check
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:80"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 30s
```

### pgAdmin Health Check
```yaml
healthcheck:
  test: ["CMD", "wget", "-qO-", "http://localhost:80/misc/ping"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 30s
```

## 🐛 Troubleshooting

### Common Issues

#### phpMyAdmin Connection Issues
```bash
# Check container status
docker ps | grep phpmyadmin

# View logs
docker logs csl-phpmyadmin

# Test RDS connectivity
docker exec -it csl-phpmyadmin ping csl-brands-certification-rest-api-1.clyyomwg2s8k.us-east-2.rds.amazonaws.com
```

#### pgAdmin Connection Issues
```bash
# Check container status
docker ps | grep pgadmin

# View logs
docker logs csl-pgadmin

# Test RDS connectivity
docker exec -it csl-pgadmin ping database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com
```

### Reset Configuration

#### Reset pgAdmin Configuration
```bash
# Remove pgAdmin data volume
docker compose down
docker volume rm csl-certification-rest-api_pgadmin-data
docker compose up -d pgadmin
```

#### Reset phpMyAdmin
```bash
# Restart phpMyAdmin container
docker compose restart phpmyadmin
```

## 📈 Usage Examples

### Common Database Tasks

#### Export Database Schema (MySQL)
1. Access phpMyAdmin at http://localhost:8091
2. Select `cfpcwjwg_certification_api_db` database
3. Go to "Export" tab
4. Choose "Custom" export method
5. Select "Structure" only for schema export

#### Run SQL Queries (PostgreSQL)
1. Access pgAdmin at http://localhost:8092
2. Navigate to CSL Sales Database
3. Right-click database → "Query Tool"
4. Execute your SQL queries

#### Monitor Database Performance
- Use built-in monitoring tools in both phpMyAdmin and pgAdmin
- Check slow query logs
- Monitor connection counts and resource usage

---

**Note**: This setup provides direct access to production RDS instances. Always follow your organization's database access policies and security guidelines.
