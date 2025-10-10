# 14. Deployment Architecture

## 14.1 Infrastructure Overview

**Hosting Environment:**
- **Platform:** Self-hosted on Ubuntu 22.04 LTS servers
- **Orchestration:** Docker Compose
- **Web Server:** Nginx (reverse proxy)
- **Application Server:** PHP-FPM 8.3
- **Databases:** MySQL 8.0 (primary), PostgreSQL 15 (sales data)
- **Cache:** Redis 7
- **Queue:** RabbitMQ 3
- **CDN:** Cloudflare (optional)
- **Storage:** Local storage + AWS S3 (optional)

---

## 14.2 Docker Compose Setup

**docker-compose.yml Structure:**
```yaml
version: '3.8'

services:
  # Nginx reverse proxy
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d
      - ./certbot/conf:/etc/letsencrypt
    depends_on:
      - backend
      - frontend-certification
      - frontend-admin

  # Laravel backend
  backend:
    build:
      context: ./CSL-Certification-Rest-API
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
      - REDIS_HOST=redis
      - RABBITMQ_HOST=rabbitmq
    volumes:
      - ./CSL-Certification-Rest-API:/var/www/html
    depends_on:
      - mysql
      - redis
      - rabbitmq

  # Next.js frontend (certification)
  frontend-certification:
    build:
      context: ./CSL-Certification
      dockerfile: Dockerfile
    environment:
      - NEXT_PUBLIC_API_BASE_URL=https://api.cfpcsl.com/api
    ports:
      - "3000:3000"

  # Next.js frontend (admin)
  frontend-admin:
    build:
      context: ./CSL-Sales-Website
      dockerfile: Dockerfile
    ports:
      - "3001:3000"

  # MySQL database
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: csl_certification
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

  # PostgreSQL database
  postgres:
    image: postgres:15
    environment:
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: csl_sales
    volumes:
      - postgres_data:/var/lib/postgresql/data

  # Redis cache
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  # RabbitMQ message broker
  rabbitmq:
    image: rabbitmq:3-management-alpine
    ports:
      - "5672:5672"
      - "15672:15672"
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq

volumes:
  mysql_data:
  postgres_data:
  redis_data:
  rabbitmq_data:
```

---

## 14.3 Deployment Process

**Zero-Downtime Deployment (Blue-Green):**

```bash