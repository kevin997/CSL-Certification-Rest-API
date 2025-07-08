# Order Regularization System

## Overview

The Order Regularization System is designed to handle cases where transactions have been completed but their associated orders are still in pending status. This can happen when webhook notifications fail or callback processes are interrupted.

## Components

### 1. RegularizeCompletedOrders Command

**Location**: `app/Console/Commands/RegularizeCompletedOrders.php`

**Purpose**: Automatically identifies and processes orders that have completed transactions but remain in pending status.

**Features**:
- Configurable processing limits to prevent system overload
- Built-in sleep mechanism to avoid overwhelming the API
- Comprehensive logging for audit and debugging
- Error handling with detailed error reporting
- Progress tracking and summary reporting

### 2. Command Options

```bash
php artisan app:regularize-completed-orders [options]
```

**Available Options**:
- `--limit=50`: Maximum number of orders to process per run (default: 50)
- `--sleep=2`: Sleep time in seconds between processing orders (default: 2)

### 3. Scheduling

The command is scheduled to run every 5 minutes via Laravel's task scheduler:

```php
// In routes/console.php
Schedule::command(RegularizeCompletedOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

## Docker Integration

### Scheduler Service

A dedicated Docker service runs the scheduler in a separate container:

```yaml
# In docker-compose.yml
scheduler:
  image: localhost:5000/csl-certification-rest-api:latest
  container_name: csl-certification-rest-api-scheduler
  restart: unless-stopped
  environment:
    - CONTAINER_ROLE=scheduler
```

### Supervisor Configuration

**Location**: `docker/supervisor/scheduler.conf`

Two supervisor programs are configured:
1. `laravel-scheduler`: Runs Laravel's schedule:work command
2. `order-regularizer`: Runs the regularization command every 5 minutes

## Process Flow

1. **Query Identification**: Find orders with status 'pending' that have completed transactions
2. **Transaction Verification**: Verify the transaction is actually completed
3. **Event Triggering**: Fire the `OrderCompleted` event for the order
4. **Status Update**: Update the order status to 'completed'
5. **Logging**: Record the regularization action for audit purposes
6. **Sleep**: Wait between processing to prevent system overload

## Error Handling

- **Database Errors**: Logged with full stack trace
- **Missing Relationships**: Gracefully handled with warnings
- **Event Failures**: Caught and logged without stopping the process
- **Transaction Inconsistencies**: Identified and reported

## Monitoring

### Log Files

- **Application Logs**: `/var/www/html/storage/logs/laravel.log`
- **Scheduler Logs**: `/var/www/html/storage/logs/scheduler.log`
- **Order Regularizer Logs**: `/var/www/html/storage/logs/order-regularizer.log`

### Health Checks

The scheduler container includes health checks to ensure the cron service is running:

```yaml
healthcheck:
  test: ["CMD-SHELL", "ps aux | grep -v grep | grep 'cron' || exit 1"]
  interval: 30s
  timeout: 5s
  retries: 3
```

## Performance Considerations

### Rate Limiting
- Default limit of 50 orders per run
- 2-second sleep between orders (configurable)
- 5-minute intervals between runs

### Resource Usage
- Runs in separate container to isolate resource usage
- Uses database queries with proper indexing
- Minimal memory footprint per execution

## Security

- **Database Access**: Uses existing Laravel database connections
- **Event System**: Leverages Laravel's secure event system
- **Logging**: Sensitive data is excluded from logs
- **Container Isolation**: Runs in isolated Docker container

## Maintenance

### Manual Execution

```bash
# Run with custom parameters
php artisan app:regularize-completed-orders --limit=10 --sleep=5

# Check specific time range (if needed in future)
php artisan app:regularize-completed-orders --hours=1
```

### Monitoring Commands

```bash
# Check scheduler logs
docker exec csl-certification-rest-api-scheduler tail -f /var/www/html/storage/logs/scheduler.log

# Check order regularizer logs
docker exec csl-certification-rest-api-scheduler tail -f /var/www/html/storage/logs/order-regularizer.log

# Check supervisor status
docker exec csl-certification-rest-api-scheduler supervisorctl status
```

## Troubleshooting

### Common Issues

1. **No Orders Found**: Normal when all orders are properly synchronized
2. **Database Connection Errors**: Check RDS connectivity and credentials
3. **Event Failures**: Verify event listeners are properly registered
4. **Permission Errors**: Ensure proper file permissions in storage directory

### Debug Mode

Enable detailed logging by setting `LOG_LEVEL=debug` in your environment configuration.

## Integration Points

### OrderCompleted Event

The system triggers the existing `OrderCompleted` event, which handles:
- Enrollment processing
- Notification sending
- Subscription activation
- Third-party integrations

### Transaction Model

Integrates with the existing Transaction model and its status constants:
- `Transaction::STATUS_COMPLETED`
- `Transaction::STATUS_PENDING`
- `Transaction::STATUS_FAILED`

### Order Model

Works with the Order model and its status constants:
- `Order::STATUS_PENDING`
- `Order::STATUS_COMPLETED`
- `Order::STATUS_FAILED`

## Future Enhancements

1. **Metrics Dashboard**: Add monitoring dashboard for regularization statistics
2. **Alert System**: Implement alerts for high error rates or processing delays
3. **Batch Processing**: Optimize for larger datasets with batch processing
4. **Retry Logic**: Add intelligent retry mechanisms for failed regularizations
5. **Time-based Filtering**: Add options to process orders from specific time ranges
