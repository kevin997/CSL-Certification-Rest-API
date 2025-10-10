# TaraMoney Payment Gateway Integration

This document describes the TaraMoney payment gateway integration for the CSL Certification API.

## Overview

TaraMoney is a payment gateway that supports multiple payment methods:
- **Messaging Apps**: WhatsApp, Telegram, SMS
- **Mobile Money**: Orange Money, MTN Mobile Money (Cameroon)
- **Card Payments**: Coming soon

## Features

### 1. Order Link Payments
Generate payment links that can be shared via WhatsApp, Telegram, or SMS.

**API Endpoint**: `POST https://www.dklo.co/api/tara/order`

**Use Cases**:
- E-commerce checkout
- Invoice payments
- Course enrollments

### 2. Mobile Money Direct Payments
Initiate mobile money payments directly with customer phone number.

**API Endpoint**: `POST https://www.dklo.co/api/tara/cmmobile`

**Supported Operators**:
- Orange Money (Cameroon)
- MTN Mobile Money (Cameroon)

### 3. Webhook Notifications
Real-time payment status updates via webhooks.

**Webhook Types**:
- Payment success
- Payment failure
- Collection payments
- Card payments

## Installation & Configuration

### 1. Add to .env

```bash
# TaraMoney Production Credentials
TARAMONEY_API_KEY=lh7us1f2vfDyxmTgU0NIpcep
TARAMONEY_BUSINESS_ID=GqLD0LhdCh
TARAMONEY_WEBHOOK_SECRET=9Wb3EkeBpNJbzYXiE19P9YKY

# TaraMoney Sandbox Credentials
TARAMONEY_TEST_API_KEY=q9foJGFWYiHy6xXoa47eR7ka
TARAMONEY_TEST_BUSINESS_ID=GqLD0LhdCh
TARAMONEY_TEST_MODE=true
```

### 2. Run Database Seeder

```bash
php artisan db:seed --class=TaraMoneyGatewaySeeder
```

This will create TaraMoney gateway settings for all existing environments.

### 3. Update Environment Settings

After seeding, you can update gateway settings via the admin panel or directly in the database:

```sql
UPDATE payment_gateway_settings
SET mode = 'live',
    status = 1
WHERE code = 'taramoney'
  AND environment_id = 1;
```

## Usage

### Creating a Payment

#### Option 1: Order Link Payment (Default)

```php
use App\Services\PaymentService;

$paymentService = app(PaymentService::class);

$result = $paymentService->createPayment(
    $orderId,
    'taramoney',
    [],
    $environmentName
);

if ($result['success']) {
    // Redirect user to payment link
    $paymentUrl = $result['checkout_url'];

    // Or display multiple payment options
    $whatsappLink = $result['whatsapp_link'];
    $telegramLink = $result['telegram_link'];
    $dikaloLink = $result['dikalo_link'];
    $smsLink = $result['sms_link'];
}
```

#### Option 2: Mobile Money Direct Payment

```php
$result = $paymentService->createPayment(
    $orderId,
    'taramoney',
    [
        'paymentType' => 'mobile_money',
        'phoneNumber' => '696717597' // Cameroon phone number
    ],
    $environmentName
);

if ($result['success']) {
    // Display USSD code to user
    $ussdCode = $result['ussd_code']; // e.g., "#150*50#"
    $vendor = $result['vendor']; // "ORANGE_CAMEROON" or "MTN_CAMEROON"

    // User should dial this code on their phone
    echo "Please dial {$ussdCode} to complete payment";
}
```

### Handling Webhooks

Webhooks are automatically processed by the `TransactionController::webhook()` method.

**Webhook URL Format**:
```
https://your-domain.com/api/payments/transactions/webhook/taramoney/{environment_id}
```

**Webhook Payload Example** (Collections/Mobile Money):
```json
{
  "businessId": "GqLD0LhdCh",
  "paymentId": "2551754489",
  "amount": "100",
  "mobileOperator": "ORANGE_CAMEROON",
  "customerName": "",
  "collectionId": "27731",
  "transactionCode": "MP2507020016ECE2D1657ADF8111",
  "customerId": "",
  "phoneNumber": "611111111",
  "creationDate": "2025-07-02T14:13:53.888+02:00",
  "changeDate": "2025-07-02T14:13:53.088+02:00",
  "type": "DEPOSIT",
  "status": "SUCCESS"
}
```

**Webhook Payload Example** (Card Payments):
```json
{
  "businessId": "GqLD0LhdCh",
  "status": "SUCCESS",
  "paymentId": "2551754489",
  "collectionId": "27731",
  "creationDate": "2025-07-02T14:13:53.888+02:00",
  "changeDate": "2025-07-02T14:13:53.088+02:00"
}
```

## API Integration Details

### 1. Order API

**Request**:
```json
{
  "apiKey": "lh7us1f2vfDyxmTgU0NIpcep",
  "businessId": "GqLD0LhdCh",
  "productId": "product-456",
  "productName": "Product name",
  "productPrice": 100,
  "productDescription": "Product description",
  "productPictureUrl": "https://example.com/image.jpg",
  "returnUrl": "https://example.com/return",
  "webHookUrl": "https://example.com/webhook"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Link successfully generated",
  "whatsappLink": "https://wa.me/...",
  "telegramLink": "https://t.me/...",
  "dikaloLink": "https://dikalo.me/...",
  "smsLink": "sms:+2376...?body=..."
}
```

### 2. Mobile Money API

**Request**:
```json
{
  "apiKey": "lh7us1f2vfDyxmTgU0NIpcep",
  "businessId": "GqLD0LhdCh",
  "productId": "product-456",
  "productName": "Product name",
  "productPrice": 100,
  "phoneNumber": "6xxxxxxxx",
  "webHookUrl": "https://example.com/webhook"
}
```

**Response**:
```json
{
  "message": "API_ORDER_SUCESSFULL",
  "status": "SUCCESS",
  "ussdCode": "#150*50#",
  "vendor": "ORANGE_CAMEROON"
}
```

## Payment Flow

### Order Link Flow

1. Customer initiates checkout
2. System creates transaction in database
3. TaraMoney gateway generates payment links
4. Customer receives links via WhatsApp/Telegram/SMS
5. Customer clicks link and completes payment
6. TaraMoney sends webhook notification
7. System updates transaction status
8. Order is marked as completed

### Mobile Money Flow

1. Customer provides phone number at checkout
2. System creates transaction and initiates payment
3. TaraMoney returns USSD code
4. Customer dials USSD code on their phone
5. Customer completes payment via mobile money menu
6. TaraMoney sends webhook notification
7. System updates transaction status
8. Order is marked as completed

## Currency Support

TaraMoney primarily works with:
- **XAF** (Central African CFA Franc)
- **XOF** (West African CFA Franc)

The integration automatically converts other currencies to XAF using the `Transaction::convertToXAF()` method.

## Testing

### Test Mode

Set `TARAMONEY_TEST_MODE=true` in your `.env` file to use sandbox credentials.

### Test Webhook

Use tools like [Webhook.site](https://webhook.site) to test webhook delivery:

1. Get a webhook URL from webhook.site
2. Update the webhook URL in the payment request
3. Complete a test payment
4. View webhook payload on webhook.site

### Test Payment

```bash
# Using cURL
curl -X POST https://www.dklo.co/api/tara/order \
  -H "Content-Type: application/json" \
  -d '{
  "apiKey": "q9foJGFWYiHy6xXoa47eR7ka",
  "businessId": "GqLD0LhdCh",
  "productId": "test-product",
  "productName": "Test Product",
  "productPrice": 100,
  "productDescription": "Test payment",
  "productPictureUrl": "https://example.com/image.jpg",
  "returnUrl": "https://example.com/return",
  "webHookUrl": "https://webhook.site/your-unique-url"
}'
```

## Troubleshooting

### Common Issues

#### 1. Transaction Not Found in Webhook

**Problem**: Webhook receives payment notification but can't find transaction.

**Solution**:
- Check that `paymentId` in webhook matches `gateway_transaction_id` or `transaction_id` in database
- Verify webhook is hitting the correct environment endpoint
- Check logs: `storage/logs/laravel.log`

#### 2. Missing API Credentials

**Problem**: Gateway initialization fails with "Missing API credentials".

**Solution**:
```bash
# Check .env configuration
php artisan config:cache
php artisan cache:clear

# Verify settings in database
php artisan tinker
>>> \App\Models\PaymentGatewaySetting::where('code', 'taramoney')->first()->settings
```

#### 3. Currency Conversion Issues

**Problem**: Payment amount is incorrect in XAF.

**Solution**:
- Ensure `Transaction::convertToXAF()` method is properly implemented
- Check exchange rates in `ExchangeRate` table
- Verify original currency is supported

#### 4. Webhook Signature Verification Failed

**Problem**: Webhooks are rejected due to invalid signature.

**Solution**:
- Verify `TARAMONEY_WEBHOOK_SECRET` matches TaraMoney dashboard
- Check webhook signature verification logic in `TaraMoneyGateway::verifyWebhookSignature()`
- Temporarily disable signature verification for debugging (not recommended for production)

### Debug Mode

Enable detailed logging:

```php
// In TaraMoneyGateway.php
Log::channel('daily')->info('TaraMoney Debug', [
    'transaction_id' => $transaction->id,
    'api_key' => substr($this->apiKey, 0, 5) . '...',
    'request' => $requestData,
    'response' => $responseData
]);
```

## Security Considerations

1. **API Keys**: Store in `.env`, never commit to version control
2. **Webhook Secret**: Use to verify webhook authenticity
3. **HTTPS**: Always use HTTPS for production webhooks
4. **IP Whitelisting**: Consider restricting webhook endpoints to TaraMoney IPs
5. **Rate Limiting**: Implement rate limiting on payment endpoints

## Support

- **TaraMoney Dashboard**: [https://www.dklo.co/dashboard](https://www.dklo.co/dashboard)
- **API Documentation**: Contact TaraMoney support
- **Technical Issues**: Check logs at `storage/logs/laravel.log`

## Code References

- Gateway Implementation: `app/Services/PaymentGateways/TaraMoneyGateway.php`
- Webhook Handler: `app/Http/Controllers/Api/TransactionController.php::handleTaraMoneyWebhook()`
- Gateway Factory: `app/Services/PaymentGateways/PaymentGatewayFactory.php`
- Database Seeder: `database/seeders/TaraMoneyGatewaySeeder.php`
