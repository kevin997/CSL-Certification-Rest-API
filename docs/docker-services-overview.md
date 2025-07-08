# Docker Services Overview

## Service Architecture

The CSL Certification REST API uses a multi-container Docker architecture with specialized services for different responsibilities:

### 1. App Service (Main Application)
- **Container**: `csl-certification-rest-api`
- **Role**: `CONTAINER_ROLE=app`
- **Purpose**: Main Laravel application with Nginx and PHP-FPM
- **Port**: `8080:80`
- **Health Check**: HTTP endpoint at `/health/index.php`

### 2. Queue Worker Service
- **Container**: `csl-certification-rest-api-queue`
- **Role**: `CONTAINER_ROLE=queue`
- **Purpose**: Process Laravel queues (default, emails, notifications)
- **Supervisor Programs**: 2 queue worker processes
- **Health Check**: Supervisor process monitoring

### 3. WebSocket Service (Laravel Reverb)
- **Container**: `csl-certification-rest-api-reverb`
- **Role**: `CONTAINER_ROLE=reverb`
- **Purpose**: Real-time WebSocket connections
- **Port**: `8085:8080`
- **Health Check**: Reverb process monitoring

### 4. Scheduler Service
- **Container**: `csl-certification-rest-api-scheduler`
- **Role**: `CONTAINER_ROLE=scheduler`
- **Purpose**: Run scheduled tasks and order regularization
- **Programs**:
  - `laravel-scheduler`: Laravel's schedule:work command
  - `order-regularizer`: Order regularization every 5 minutes
- **Health Check**: Cron process monitoring

### 5. Nightwatch Agent Service
- **Container**: `csl-certification-rest-api-nightwatch`
- **Role**: `CONTAINER_ROLE=nightwatch`
- **Purpose**: Application monitoring and logging
- **Configuration**: 
  - Token: `NIGHTWATCH_TOKEN`
  - Sample Rate: `NIGHTWATCH_REQUEST_SAMPLE_RATE`
- **Health Check**: Nightwatch agent process monitoring

## Environment Variables

### Common Variables (All Services)
```env
APP_ENV=staging
APP_DEBUG=false
DB_HOST=database-1.ccr2s68cu8xf.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Service-Specific Variables

#### Reverb Service
```env
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
```

#### Nightwatch Service
```env
NIGHTWATCH_TOKEN=your_nightwatch_token
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1
LOG_CHANNEL=nightwatch
```

#### Queue Service
```env
QUEUE_CONNECTION=database
```

## Supervisor Configurations

### Queue Worker (`docker/supervisor/queue.conf`)
- 2 worker processes
- Handles: default, emails, notifications queues
- Auto-restart on failure
- 3600 second stop timeout

### Scheduler (`docker/supervisor/scheduler.conf`)
- Laravel scheduler process
- Order regularization process (every 5 minutes)
- Auto-restart on failure

### Nightwatch (`docker/supervisor/nightwatch.conf`)
- Single Nightwatch agent process
- Auto-restart on failure
- Dedicated log file

## Health Checks

Each service includes health checks to monitor service status:

| Service | Health Check | Interval | Timeout | Retries |
|---------|-------------|----------|---------|---------|
| App | HTTP curl to /health/index.php | 10s | 5s | 5 |
| Queue | Supervisor process check | 10s | 5s | 3 |
| Reverb | Reverb process check | 10s | 5s | 3 |
| Scheduler | Cron process check | 30s | 5s | 3 |
| Nightwatch | Nightwatch agent check | 30s | 5s | 3 |

## Log Files

### Application Logs
- **Location**: `/var/www/html/storage/logs/`
- **Main Log**: `laravel.log` (daily rotation with 7-day retention)
- **Queue Log**: `queue.log`
- **Scheduler Log**: `scheduler.log`
- **Order Regularizer Log**: `order-regularizer.log`
- **Nightwatch Log**: `nightwatch.log`

### System Logs
- **Supervisor**: `/var/log/supervisor/supervisord.log`
- **Nginx**: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`

## Service Dependencies

```
App (Main) ← Queue Worker
App (Main) ← Reverb
App (Main) ← Scheduler
App (Main) ← Nightwatch
```

All services depend on the main app service being healthy before starting.

## Deployment Commands

### Start All Services
```bash
docker-compose up -d
```

### Start Specific Service
```bash
docker-compose up -d scheduler
docker-compose up -d nightwatch
```

### View Service Logs
```bash
docker-compose logs -f scheduler
docker-compose logs -f nightwatch
docker-compose logs -f queue
```

### Check Service Status
```bash
docker-compose ps
docker-compose exec scheduler supervisorctl status
docker-compose exec nightwatch supervisorctl status
```

## Monitoring Commands

### Check Scheduler Status
```bash
docker exec csl-certification-rest-api-scheduler php artisan schedule:list
docker exec csl-certification-rest-api-scheduler supervisorctl status
```

### Check Nightwatch Status
```bash
docker exec csl-certification-rest-api-nightwatch php artisan nightwatch:status
```

### Check Queue Status
```bash
docker exec csl-certification-rest-api-queue php artisan queue:work --once
docker exec csl-certification-rest-api-queue supervisorctl status
```

## Scaling Considerations

### Queue Workers
Increase `numprocs` in `queue.conf` for higher throughput:
```ini
numprocs=4  # Increase from 2 to 4 workers
```

### Order Regularization
Adjust frequency and batch size in scheduler configuration:
```bash
# In scheduler.conf, modify the command
command=bash -c 'while true; do php /var/www/html/artisan app:regularize-completed-orders --limit=50 --sleep=1; sleep 180; done'
```

### Nightwatch Sampling
Adjust sample rate for production:
```env
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.05  # Reduce from 0.1 to 0.05
```

## Troubleshooting

### Common Issues

1. **Service Won't Start**: Check environment variables and database connectivity
2. **High Memory Usage**: Monitor queue worker memory and restart if needed
3. **Log File Growth**: Ensure log rotation is configured properly
4. **Database Connections**: Monitor connection pool usage across services

### Debug Commands

```bash
# Check all container status
docker-compose ps

# View container logs
docker-compose logs [service_name]

# Execute commands in containers
docker-compose exec [service_name] bash

# Check supervisor status
docker-compose exec [service_name] supervisorctl status

# Restart specific service
docker-compose restart [service_name]
```
