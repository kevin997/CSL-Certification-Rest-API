# 7. External APIs

## 7.1 External API Overview

The CSL platform integrates with multiple external services for payment processing, email delivery, notifications, and file storage. All external API credentials are stored in environment-specific `PaymentGatewaySetting` records (for payment gateways) or in Laravel configuration files (for other services).

**Security Practices:**
- All API keys encrypted at rest in database
- API secrets stored in environment variables (`.env` file)
- HTTPS/TLS for all external API communication
- Webhook signature verification for callbacks
- Rate limiting and retry logic with exponential backoff

---

## 7.2 Payment Gateway APIs

### 7.2.1 Stripe API

**Purpose:** Credit/debit card processing for global payments

**Documentation:** https://stripe.com/docs/api

**Base URL(s):**
- Production: `https://api.stripe.com`
- Test: `https://api.stripe.com` (uses test API keys: `sk_test_...`, `pk_test_...`)

**Authentication:** Bearer token authentication with secret API key
```http
Authorization: Bearer sk_live_51ABC...xyz
```

**API Version:** `2023-10-16` (configurable per environment)

**Rate Limits:**
- 100 requests per second per API key
- 25 concurrent requests per API key
- Stripe handles rate limiting gracefully with retry-after headers

**Key Endpoints Used:**

- `POST /v1/payment_intents` - Create payment intent for checkout
  ```json
  {
    "amount": 5000,
    "currency": "usd",
    "payment_method_types": ["card"],
    "metadata": {
      "environment_id": "3",
      "order_id": "ORD-123456"
    }
  }
  ```

- `POST /v1/payment_intents/{id}/confirm` - Confirm payment
- `GET /v1/payment_intents/{id}` - Retrieve payment intent status
- `POST /v1/refunds` - Process refund
- `POST /v1/customers` - Create customer record
- `POST /v1/webhooks` - Webhook endpoint for payment events

**Webhook Events:**
- `payment_intent.succeeded` - Payment completed successfully
- `payment_intent.payment_failed` - Payment failed
- `charge.refunded` - Refund processed
- `customer.subscription.created` - Subscription created

**Integration Notes:**
- Uses Stripe SDK for PHP: `stripe/stripe-php` (composer package)
- Client-side integration via Stripe.js for PCI compliance
- Webhook signature verification using `webhook_secret`
- Test mode uses separate API keys (stored in `test_api_key` setting)
- Supports 3D Secure (SCA) for European payments

**Error Handling:**
- API errors caught via `Stripe\Exception\ApiErrorException`
- Transaction status updated to 'failed' on errors
- Detailed error messages logged to Laravel log

**File:** `app/Services/PaymentGateways/StripeGateway.php`

---

### 7.2.2 MonetBil API

**Purpose:** Mobile money payments for Cameroon (MTN Mobile Money, Orange Money)

**Documentation:** https://www.monetbil.com/support/documentation/en

**Base URL(s):**
- Production: `https://api.monetbil.com`
- Widget URL: `https://www.monetbil.com/widget/{version}`

**Authentication:** Service key + service secret authentication
```http
Content-Type: application/x-www-form-urlencoded
service=ABC123&secret=xyz789
```

**Widget Version:** `v2.1` (configurable)

**Rate Limits:** Not publicly documented (assumed reasonable commercial use)

**Key Endpoints Used:**

- `POST /v1/payment` - Initiate payment
  ```json
  {
    "service": "ABC123",
    "amount": "5000",
    "currency": "XAF",
    "payment_ref": "TXN-123456",
    "user": "237670000000",
    "item_ref": "ORD-123456",
    "return_url": "https://example.com/callback/success",
    "notify_url": "https://example.com/webhook/monetbil"
  }
  ```

- `GET /v1/checkPayment` - Check payment status
- `POST /v1/refund` - Process refund (if supported)

**Webhook Events:**
- Payment success notification (POST to `notify_url`)
- Payment failure notification

**Integration Notes:**
- Uses HTTP client (Guzzle via Laravel Http facade)
- Widget-based payment flow (redirect to MonetBil hosted page)
- Supports XAF (Central African Franc) currency
- Test mode uses `test_service_key` and `test_service_secret`
- Signature verification for webhook callbacks

**Mobile Money Providers Supported:**
- MTN Mobile Money (Cameroon)
- Orange Money (Cameroon)

**Error Handling:**
- HTTP errors caught and logged
- Transaction marked 'failed' on API errors
- User redirected to failure URL on payment decline

**File:** `app/Services/PaymentGateways/MonetbillGateway.php`

---

### 7.2.3 PayPal API

**Purpose:** PayPal wallet payments for global users

**Documentation:** https://developer.paypal.com/docs/api/overview/

**Base URL(s):**
- Production: `https://api.paypal.com`
- Sandbox: `https://api.sandbox.paypal.com`

**Authentication:** OAuth 2.0 Bearer token
```http
Authorization: Bearer A21AAB...xyz
```
- Obtain token via `POST /v1/oauth2/token` with client_id and client_secret

**API Version:** REST API v2

**Rate Limits:**
- 10,000 calls per day per app (production)
- 500 calls per day (sandbox)

**Key Endpoints Used:**

- `POST /v2/checkout/orders` - Create order
  ```json
  {
    "intent": "CAPTURE",
    "purchase_units": [{
      "amount": {
        "currency_code": "USD",
        "value": "50.00"
      },
      "reference_id": "ORD-123456"
    }]
  }
  ```

- `POST /v2/checkout/orders/{id}/capture` - Capture payment
- `GET /v2/checkout/orders/{id}` - Get order details
- `POST /v2/payments/refunds` - Process refund

**Webhook Events:**
- `CHECKOUT.ORDER.APPROVED` - Order approved by payer
- `PAYMENT.CAPTURE.COMPLETED` - Payment captured
- `PAYMENT.CAPTURE.DENIED` - Payment denied

**Integration Notes:**
- Uses PayPal SDK or HTTP client
- Redirect-based payment flow
- Webhook signature verification required
- Sandbox credentials for testing

**File:** `app/Services/PaymentGateways/PayPalGateway.php`

---

### 7.2.4 Lygos API

**Purpose:** Regional payment processing (Africa-focused gateway)

**Documentation:** (Proprietary - contact Lygos for API docs)

**Base URL(s):**
- Production: `https://api.lygos.cm` (or similar)
- Test: `https://test-api.lygos.cm`

**Authentication:** API key authentication (implementation-specific)

**Rate Limits:** Not publicly documented

**Key Endpoints Used:**
- Payment initialization endpoint
- Payment status check endpoint
- Refund endpoint (if supported)
- Webhook callback endpoint

**Integration Notes:**
- Implementation follows `PaymentGatewayInterface`
- Redirect or widget-based payment flow
- Supports local payment methods (mobile money, bank transfers)
- Test mode credentials separate from production

**File:** `app/Services/PaymentGateways/LygosGateway.php`

---

## 7.3 Email Delivery Services

### 7.3.1 Postmark API

**Purpose:** Transactional email delivery (password resets, notifications, certificates)

**Documentation:** https://postmarkapp.com/developer

**Base URL:** `https://api.postmarkapp.com`

**Authentication:** Server API token in header
```http
X-Postmark-Server-Token: abc123-def456-xyz789
```

**Rate Limits:**
- 10 million emails per month (varies by plan)
- 300 requests per minute per server token

**Key Endpoints Used:**

- `POST /email` - Send single email
  ```json
  {
    "From": "noreply@csl-platform.com",
    "To": "user@example.com",
    "Subject": "Password Reset Request",
    "HtmlBody": "<p>Click here to reset your password...</p>",
    "MessageStream": "outbound"
  }
  ```

- `POST /email/batch` - Send multiple emails
- `GET /messages/{id}` - Get email delivery status

**Webhook Events:**
- `Delivery` - Email delivered successfully
- `Bounce` - Email bounced
- `Open` - Email opened (if tracking enabled)
- `Click` - Link clicked (if tracking enabled)

**Integration Notes:**
- Laravel Mail driver configured for Postmark
- Template support via Postmark templates
- Automatic retry on transient failures
- Bounce handling and suppression list management

**Configuration:**
```php
// config/services.php
'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
]
```

**File:** Laravel Mail system (built-in)

---

### 7.3.2 Mailjet API (Alternative)

**Purpose:** Alternative transactional email service

**Documentation:** https://dev.mailjet.com/

**Base URL:** `https://api.mailjet.com`

**Authentication:** API key + API secret (Basic Auth)
```http
Authorization: Basic base64(api_key:api_secret)
```

**Rate Limits:**
- 6,000 emails per day (free tier)
- 200 emails per second (paid plans)

**Key Endpoints Used:**
- `POST /v3.1/send` - Send email
- `GET /v3/REST/message/{id}` - Get message status

**Configuration:**
```php
// config/services.php
'mailjet' => [
    'key' => env('MAILJET_APIKEY'),
    'secret' => env('MAILJET_APISECRET'),
]
```

---

## 7.4 Notification Services

### 7.4.1 Telegram Bot API

**Purpose:** Send admin notifications to Telegram (failed jobs, payment errors)

**Documentation:** https://core.telegram.org/bots/api

**Base URL:** `https://api.telegram.org/bot{token}`

**Authentication:** Bot token in URL path

**Rate Limits:**
- 30 messages per second per bot
- 1 message per second per chat

**Key Endpoints Used:**

- `POST /bot{token}/sendMessage` - Send text message
  ```json
  {
    "chat_id": "-1001836815830",
    "text": "⚠️ Queue failed: 5 jobs in failed_jobs table",
    "parse_mode": "HTML"
  }
  ```

- `POST /bot{token}/sendPhoto` - Send image
- `POST /bot{token}/sendDocument` - Send file

**Integration Notes:**
- Used for critical system alerts (payment failures, queue failures)
- Bot token and chat ID stored in `config/services.php`
- Telegram notifications sent via `TelegramService`
- Non-blocking (queued jobs for notification sending)

**Configuration:**
```php
// config/services.php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
]
```

**File:** `app/Services/TelegramService.php`

---

### 7.4.2 Slack API (Alternative)

**Purpose:** Send admin notifications to Slack channels

**Documentation:** https://api.slack.com/

**Base URL:** `https://slack.com/api`

**Authentication:** Bot OAuth token
```http
Authorization: Bearer xoxb-123456-abcdef
```

**Key Endpoints Used:**
- `POST /chat.postMessage` - Send message to channel

**Configuration:**
```php
// config/services.php
'slack' => [
    'notifications' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
]
```

---

## 7.5 File Storage Services

### 7.5.1 AWS S3 API (Optional)

**Purpose:** Cloud file storage for course materials, certificates, user uploads

**Documentation:** https://docs.aws.amazon.com/s3/

**Base URL:** `https://{bucket}.s3.{region}.amazonaws.com`

**Authentication:** AWS Signature Version 4 (IAM credentials)
```
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
```

**Rate Limits:**
- 3,500 PUT/POST/DELETE requests per second per prefix
- 5,500 GET/HEAD requests per second per prefix

**Key Operations Used:**
- `PUT /{object-key}` - Upload file
- `GET /{object-key}` - Download file
- `DELETE /{object-key}` - Delete file
- `GET /?list-type=2` - List objects in bucket

**Integration Notes:**
- Laravel filesystem driver configured for S3
- Public and private buckets (public for certificates, private for course content)
- CloudFront CDN in front of S3 for faster delivery (optional)
- Automatic file cleanup for expired demo environments

**Configuration:**
```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'bucket' => env('AWS_BUCKET'),
]
```

**File:** Laravel Storage facade (built-in)

---

### 7.5.2 Local File Storage (Default)

**Purpose:** Local file storage for development and small deployments

**Base Path:** `storage/app/public`

**Integration Notes:**
- Default storage driver in Laravel
- Files accessible via symlink: `public/storage -> storage/app/public`
- Suitable for development and single-server deployments
- Should migrate to S3 for multi-server or high-traffic production

---

## 7.6 External API Security

**API Key Management:**
- Payment gateway API keys stored in `payment_gateway_settings` table (encrypted)
- Other service API keys in `.env` file (not committed to git)
- Key rotation supported via admin UI (payment gateways)

**Webhook Security:**
- Signature verification for all webhook callbacks
- IP whitelisting where supported (Stripe, PayPal)
- HTTPS-only webhook endpoints
- Idempotency keys to prevent duplicate processing

**Error Handling:**
- Exponential backoff retry logic (3 retries with 2^n seconds delay)
- Circuit breaker pattern for failing external services
- Graceful degradation (e.g., local storage fallback if S3 fails)
- Detailed logging of all external API calls

**Monitoring:**
- External API response times tracked in logs
- Failed API calls trigger Telegram/Slack alerts
- Webhook processing failures logged to `failed_jobs` table
- Sentry integration for error tracking

---

## 7.7 External API Integration Diagram

```mermaid
graph TB
    subgraph "CSL Platform"
        PaymentService[PaymentService]
        MailService[Laravel Mail]
        TelegramService[TelegramService]
        FileStorage[File Storage Facade]
    end

    subgraph "Payment Gateway APIs"
        Stripe[Stripe API<br/>api.stripe.com]
        PayPal[PayPal API<br/>api.paypal.com]
        Monetbil[MonetBil API<br/>api.monetbil.com]
        Lygos[Lygos API<br/>api.lygos.cm]
    end

    subgraph "Email Services"
        Postmark[Postmark API<br/>api.postmarkapp.com]
        Mailjet[Mailjet API<br/>api.mailjet.com]
    end

    subgraph "Notification Services"
        Telegram[Telegram Bot API<br/>api.telegram.org]
        Slack[Slack API<br/>slack.com/api]
    end

    subgraph "Storage Services"
        S3[AWS S3<br/>s3.amazonaws.com]
        LocalStorage[Local Storage<br/>storage/app/public]
    end

    %% Payment Service to Gateways
    PaymentService -->|HTTPS + API Key| Stripe
    PaymentService -->|HTTPS + API Key| PayPal
    PaymentService -->|HTTPS + Service Key/Secret| Monetbil
    PaymentService -->|HTTPS + API Key| Lygos

    %% Email Service
    MailService -->|HTTPS + Server Token| Postmark
    MailService -->|HTTPS + API Key| Mailjet

    %% Notification Service
    TelegramService -->|HTTPS + Bot Token| Telegram
    TelegramService -->|HTTPS + OAuth Token| Slack

    %% File Storage
    FileStorage -->|AWS SDK + IAM Credentials| S3
    FileStorage -->|Local Filesystem| LocalStorage

    %% Webhook Callbacks
    Stripe -.->|Webhook Callback| PaymentService
    PayPal -.->|Webhook Callback| PaymentService
    Monetbil -.->|Webhook Callback| PaymentService
    Lygos -.->|Webhook Callback| PaymentService
```

---

---
