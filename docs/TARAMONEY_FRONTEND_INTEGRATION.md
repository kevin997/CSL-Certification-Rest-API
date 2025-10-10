# TaraMoney Frontend Integration

## Overview
This document describes the frontend integration of TaraMoney payment gateway in the CSL Certification checkout page.

## Changes Made

### 1. Frontend Checkout Page (`/app/checkout/[domain]/page.tsx`)

#### Added Payment Gateway UI (Lines 917-923)
```tsx
{gateway.code === 'taramoney' && (
  <div className="flex items-center gap-1">
    <span>(WhatsApp, Telegram, Orange Money, MTN Mobile Money) </span>
    <Image src={"/logo-om.svg"} alt="Orange Money" width={20} height={20} className="h-5 w-5" />
    <Image src={"/logo-momo.svg"} alt="Mobile Money" width={20} height={20} className="h-5 w-5" />
  </div>
)}{gateway.code === 'taramoney' && "TaraMoney"}
```

**Description**: Added TaraMoney option to the payment gateway radio buttons with appropriate icons and description showing supported payment methods.

#### Added Payment Type Handling (Line 466)
```tsx
case 'paypal':
case 'lygos':
case 'monetbill':
case 'taramoney':  // Added
  // For redirect-based payments like PayPal, Lygos, MonetBill, and TaraMoney
  if (response.data.redirect_url) {
    setTimeout(() => {
      window.location.href = response.data.redirect_url || ''
    }, 1000)
  } else {
    throw new Error(`Missing redirect URL for ${response.data.payment_type} payment`)
  }
  break
```

**Description**: Added 'taramoney' case to handle redirect-based payment flow.

### 2. Backend API Controller (`app/Http/Controllers/Api/StorefrontController.php`)

#### Fixed Payment Type Detection (Line 1733-1738)
**Before**:
```php
case 'payment_url':
    // For Lygos redirect-based payments
    $responseData['payment_type'] = 'lygos';
    $responseData['redirect_url'] = $paymentResult['value'];
    break;
```

**After**:
```php
case 'payment_url':
    // For redirect-based payments (Lygos, TaraMoney, MonetBill)
    // Use the actual gateway code instead of hardcoding 'lygos'
    $responseData['payment_type'] = $gatewayCode;
    $responseData['redirect_url'] = $paymentResult['value'];
    break;
```

**Description**: Changed hardcoded 'lygos' to use the actual `$gatewayCode` variable, allowing the frontend to properly identify TaraMoney, MonetBill, and Lygos payments.

## How It Works

### Payment Flow

1. **Gateway Selection**: User selects TaraMoney from available payment options
2. **Form Submission**: User fills in billing details and submits the checkout form
3. **Order Creation**: Frontend calls `POST /api/storefront/{domain}/checkout` with:
   ```json
   {
     "payment_gateway": "taramoney",
     "payment_method": "4",  // Gateway ID
     // ... other order data
   }
   ```
4. **Backend Processing**:
   - StorefrontController creates order
   - Calls PaymentService which uses TaraMoneyGateway
   - TaraMoney API returns payment URL
   - Backend returns `payment_type: 'taramoney'` with `redirect_url`
5. **Frontend Redirect**: Frontend detects `payment_type === 'taramoney'` and redirects to the payment URL
6. **Payment Completion**: User completes payment on TaraMoney platform
7. **Webhook Notification**: TaraMoney sends webhook to `/api/payments/webhook`
8. **Success/Failure Redirect**: User is redirected to success or failure page

### Payment Methods Supported

TaraMoney supports multiple payment methods:
- **WhatsApp Payments** - Pay via WhatsApp messaging
- **Telegram Payments** - Pay via Telegram messaging
- **Orange Money** - Mobile money for Orange subscribers
- **MTN Mobile Money** - Mobile money for MTN subscribers

Users are redirected to TaraMoney's platform where they can choose their preferred method.

## Environment Variables Required

Add to `.env`:
```bash
TARAMONEY_API_KEY=your_production_api_key
TARAMONEY_BUSINESS_ID=your_business_id
TARAMONEY_WEBHOOK_SECRET=your_webhook_secret
TARAMONEY_TEST_API_KEY=your_sandbox_api_key
TARAMONEY_TEST_BUSINESS_ID=your_test_business_id
TARAMONEY_TEST_MODE=true
```

## Testing

### Enable TaraMoney Gateway
```bash
# Run seeder to create gateway
php artisan db:seed --class=TaraMoneyGatewaySeeder

# Or run full database seeder
php artisan db:seed
```

### Test Payment Flow
1. Navigate to `/storefront/{domain}/products`
2. Select a product and click "Buy Now"
3. Fill in checkout form
4. Select "TaraMoney" as payment method
5. Click "Pay $XX.XX"
6. Should redirect to TaraMoney payment page
7. Complete payment on TaraMoney platform
8. Should redirect back to success/failure page

### Verify Gateway Availability
```bash
# Check if gateway is available via API
curl -X GET "https://certification.csl-brands.com/api/storefront/1/payment-gateways"
```

Should return TaraMoney in the list:
```json
{
  "data": [
    {
      "id": 4,
      "code": "taramoney",
      "gateway_name": "TaraMoney",
      "display_name": "TaraMoney",
      "status": true,
      "is_default": false,
      "mode": "live",
      "sort_order": 40
    }
  ]
}
```

## UI/UX Considerations

- **Icons**: Uses existing Orange Money and MTN Mobile Money logos
- **Description**: Shows supported payment methods in parentheses
- **Redirect Message**: Generic message applies to all redirect-based gateways
- **Loading State**: Shows "Processing..." while order is being created
- **Error Handling**: Displays toast notification if payment creation fails

## Related Files

### Backend
- `app/Services/PaymentGateways/TaraMoneyGateway.php` - Gateway implementation
- `app/Http/Controllers/Api/TransactionController.php` - Webhook handler
- `app/Http/Controllers/Api/StorefrontController.php` - Checkout API endpoint
- `database/seeders/TaraMoneyGatewaySeeder.php` - Database seeder

### Frontend
- `app/checkout/[domain]/page.tsx` - Checkout page
- `app/checkout/[domain]/success/page.tsx` - Success page
- `app/checkout/[domain]/failure/page.tsx` - Failure page

### Documentation
- `docs/TARAMONEY_INTEGRATION.md` - Backend integration guide
- `docs/TARAMONEY_FRONTEND_INTEGRATION.md` - This document

## Troubleshooting

### Gateway not showing in checkout
- Verify gateway is seeded: `SELECT * FROM payment_gateway_settings WHERE code = 'taramoney';`
- Check gateway status: Ensure `status = 1` (enabled)
- Check environment: Ensure `environment_id = 1` matches your storefront

### Payment not redirecting
- Check browser console for errors
- Verify `redirect_url` is returned in API response
- Check that `payment_type` is 'taramoney' (not 'lygos')

### Webhook not receiving notifications
- Verify webhook URL is accessible publicly
- Check TaraMoney dashboard for webhook delivery logs
- Ensure webhook secret matches environment variable
- Check Laravel logs: `tail -f storage/logs/laravel.log`

## Next Steps

1. **Add TaraMoney Logo**: Create and add `/public/taramoney-icon.svg` for better branding
2. **Test Production Credentials**: Switch to production API keys when ready
3. **Monitor Transactions**: Check transaction logs for successful payments
4. **Customer Support**: Provide documentation to customers on using TaraMoney

## References

- [TaraMoney API Documentation](https://www.dklo.co/api/docs)
- [Backend Integration Guide](./TARAMONEY_INTEGRATION.md)
