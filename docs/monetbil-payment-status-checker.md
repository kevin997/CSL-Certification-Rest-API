# Monetbil Payment Status Checker Command

## Overview

The `CheckMonetBilPaymentsStatus` command is a Laravel Artisan command that automatically checks the status of pending Monetbil transactions and updates their status in the system. This command integrates with the existing payment processing flow to ensure payments are properly tracked and completed.

## Command Usage

### Basic Usage
```bash
php artisan app:check-monet-bil-payments-status
```

### Options

- `--environment=ID` : Check transactions for a specific environment ID only
- `--hours=N` : Check transactions from the last N hours (default: 24)
- `--limit=N` : Maximum number of transactions to check in one run (default: 50)

### Examples

```bash
# Check all pending Monetbil transactions from the last 24 hours
php artisan app:check-monet-bil-payments-status

# Check transactions for environment ID 1 from the last 12 hours
php artisan app:check-monet-bil-payments-status --environment=1 --hours=12

# Check only the 10 most recent pending transactions
php artisan app:check-monet-bil-payments-status --limit=10

# Check transactions from the last 48 hours with a limit of 100
php artisan app:check-monet-bil-payments-status --hours=48 --limit=100
```

## How It Works

### 1. Transaction Selection
The command queries the database for transactions that match these criteria:
- Status is `pending`
- Associated with a Monetbil payment gateway (`code = 'monetbill'`)
- Created within the specified time range (default: last 24 hours)
- Limited to the specified number (default: 50 transactions)

### 2. Payment Status Verification
For each transaction, the command:
- Initializes the MonetbillGateway with the appropriate settings
- Calls Monetbil's API to check the current payment status
- Logs the API response for audit purposes

### 3. Status Processing
Based on the API response, the command processes transactions through the existing payment flow:

#### Successful Payments
- Calls `PaymentService::processSuccessCallback()`
- Updates transaction status to `completed`
- Processes related records (orders, subscriptions, etc.)
- Logs the successful completion

#### Failed/Cancelled Payments
- Calls `PaymentService::processFailureCallback()` or `PaymentService::processCancelledCallback()`
- Updates transaction status to `failed` or `cancelled`
- Logs the failure reason

#### Unchanged Transactions
- Transactions that are still pending or have API errors remain unchanged
- Errors are logged for investigation

### 4. Results Summary
The command provides a summary of processed transactions:
- Number of successful payments processed
- Number of failed/cancelled payments processed
- Number of unchanged transactions
- Total transactions checked

## Integration with Existing System

### PaymentService Integration
The command uses the existing `PaymentService` methods to ensure consistency:
- `processSuccessCallback()` - Handles successful payment completion
- `processFailureCallback()` - Handles payment failures
- `processCancelledCallback()` - Handles payment cancellations

### MonetbillGateway Integration
The command leverages the existing `MonetbillGateway::verifyPayment()` method:
- Uses configured service keys and secrets
- Makes authenticated requests to Monetbil API
- Handles response parsing and error management

### Logging and Audit Trail
All operations are logged with appropriate detail levels:
- Info logs for successful operations
- Warning logs for recoverable issues
- Error logs for failures and exceptions
- Audit trail maintained through existing payment flow

## Scheduling

### Cron Job Setup
To run the command automatically, add it to your cron schedule:

```bash
# Check payment statuses every 30 minutes
*/30 * * * * cd /path/to/your/laravel && php artisan app:check-monet-bil-payments-status >> /dev/null 2>&1

# Check payment statuses every 2 hours with specific limits
0 */2 * * * cd /path/to/your/laravel && php artisan app:check-monet-bil-payments-status --hours=6 --limit=100 >> /dev/null 2>&1
```

### Laravel Task Scheduling
Alternatively, add to your Laravel scheduler in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check Monetbil payment statuses every 30 minutes
    $schedule->command('app:check-monet-bil-payments-status')
             ->everyThirtyMinutes()
             ->withoutOverlapping()
             ->runInBackground();
}
```

## Error Handling

### API Failures
- Network timeouts are logged but don't stop processing of other transactions
- API rate limiting is handled with built-in delays (0.5 seconds between requests)
- Invalid responses are logged with full context for debugging

### Database Errors
- Transaction lookup failures are logged and skipped
- Payment processing errors are caught and logged
- Database connection issues are handled gracefully

### Recovery Mechanisms
- Failed transactions remain in pending status for retry
- Command can be safely re-run without side effects
- Logs provide detailed information for manual investigation

## Monitoring and Maintenance

### Log Locations
Check these log files for command activity:
- `storage/logs/laravel.log` - General application logs
- Look for entries with `CheckMonetBilPaymentsStatus` context

### Performance Considerations
- Default limit of 50 transactions prevents memory issues
- 0.5-second delay between API calls prevents rate limiting
- Command timeout handled by Laravel's built-in mechanisms

### Troubleshooting
1. **No transactions found**: Check if there are pending Monetbil transactions in the specified time range
2. **API errors**: Verify Monetbil service keys and secrets are configured correctly
3. **Processing failures**: Check PaymentService configuration and database connectivity
4. **High memory usage**: Reduce the `--limit` parameter value

## Security Considerations

- Service keys and secrets are read from encrypted gateway settings
- API communications use HTTPS with signature verification
- All operations are logged for audit compliance
- No sensitive data is exposed in command output

## Testing

To test the command safely:

```bash
# Test with a small limit first
php artisan app:check-monet-bil-payments-status --limit=1

# Test with recent transactions only
php artisan app:check-monet-bil-payments-status --hours=1 --limit=5

# Test with specific environment
php artisan app:check-monet-bil-payments-status --environment=1 --limit=3
```
