# Centralized Payment Gateway Configuration Fix

**Issue**: "Payment not available" error on environments using centralized payment gateways
**Date Fixed**: 2026-02-07
**Status**: ✅ Resolved

---

## Problem Statement

When centralized payment gateways were enabled for training environments (environments 2, 3, etc.), users would see "Payment not available, we can process payments now" error during checkout, even though environment 1 had properly configured payment gateways.

### Expected Behavior
- Environment 1 has payment gateways configured
- Other environments have `use_centralized_gateways = true` in their `EnvironmentPaymentConfig`
- Those environments should use environment 1's payment gateways
- Checkout should work successfully

### Actual Behavior
- Other environments showed "Payment not available" error
- No payment gateways were returned by the API
- Checkout could not proceed

---

## Root Cause Analysis

### Issue #1: Global Scope Filtering (PRIMARY ISSUE)

**Location**: `/app/Http/Controllers/Api/StorefrontController.php:1522`

The `PaymentGatewaySetting` model uses the `BelongsToEnvironment` trait, which applies a global scope that automatically filters all queries by the current session's environment ID.

**The Bug**:
```php
// OLD CODE (Line 1522)
$gateways = PaymentGatewaySetting::where('environment_id', $targetEnvironmentId)
    ->where('status', true)
    ->whereNotIn('code', $excludeGateways)
    ->orderBy('sort_order')
    ->get();
```

**What happened**:
1. Environment 2 requests payment gateways
2. Code correctly sets `$targetEnvironmentId = 1` (due to centralized config)
3. Query tries to fetch `WHERE environment_id = 1`
4. **BUT** the `BelongsToEnvironment` global scope adds `AND environment_id = 2` (current session)
5. No results found (environment_id cannot be both 1 AND 2)
6. Frontend receives empty array
7. "Payment not available" error shown

**The Fix**:
```php
// NEW CODE (Line 1522)
$gateways = PaymentGatewaySetting::withoutGlobalScopes()
    ->where('environment_id', $targetEnvironmentId)
    ->where('status', true)
    ->whereNotIn('code', $excludeGateways)
    ->orderBy('sort_order')
    ->get();
```

Adding `withoutGlobalScopes()` bypasses the automatic environment filtering, allowing the query to fetch environment 1's gateways even when the current session is in a different environment.

---

### Issue #2: Missing Logging (SECONDARY)

**Location**: `/app/Http/Controllers/Api/StorefrontController.php:1509-1516`

There was no logging to help debug when centralized vs local gateways were being used.

**The Fix**:
Added comprehensive logging:

```php
if ($paymentConfig && $paymentConfig->use_centralized_gateways) {
    // Use centralized gateways from environment 1 (platform gateways)
    $targetEnvironmentId = 1;
    \Log::info('Using centralized payment gateways from environment 1', [
        'requesting_environment' => $environment->id,
        'target_environment' => $targetEnvironmentId
    ]);
} else {
    \Log::info('Using local payment gateways', [
        'environment' => $environment->id,
        'has_config' => $paymentConfig !== null,
        'use_centralized' => $paymentConfig ? $paymentConfig->use_centralized_gateways : null
    ]);
}
```

This helps track which payment gateway configuration is being used for each environment.

---

## How Centralized Payment Gateways Work

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│ Environment 1 (Platform/Central Environment)                │
│                                                               │
│  Payment Gateways:                                           │
│  ├─ Stripe (Active)                                          │
│  ├─ PayPal (Active)                                          │
│  └─ Flutterwave (Active)                                     │
└─────────────────────────────────────────────────────────────┘
                         ▲  ▲  ▲
                         │  │  │
        ┌────────────────┘  │  └─────────────────┐
        │                   │                     │
┌───────┴────────┐  ┌───────┴────────┐  ┌────────┴───────┐
│ Environment 2  │  │ Environment 3  │  │ Environment 4  │
│                │  │                │  │                │
│ Config:        │  │ Config:        │  │ Config:        │
│ centralized:✓  │  │ centralized:✓  │  │ centralized:✓  │
│                │  │                │  │                │
│ Uses Env 1     │  │ Uses Env 1     │  │ Uses Env 1     │
│ gateways       │  │ gateways       │  │ gateways       │
└────────────────┘  └────────────────┘  └────────────────┘
```

### Database Structure

**Table**: `environment_payment_configs`
```sql
| id | environment_id | use_centralized_gateways | platform_fee_rate | created_at |
|----|----------------|---------------------------|-------------------|------------|
| 1  | 1              | 0                         | 0.17              | ...        |
| 2  | 2              | 1                         | 0.17              | ...        |
| 3  | 3              | 1                         | 0.17              | ...        |
```

**Table**: `payment_gateway_settings`
```sql
| id | environment_id | code        | status | credentials      | sort_order |
|----|----------------|-------------|--------|------------------|------------|
| 1  | 1              | stripe      | 1      | {...}            | 1          |
| 2  | 1              | paypal      | 1      | {...}            | 2          |
| 3  | 1              | flutterwave | 1      | {...}            | 3          |
```

### Flow Diagram

```
User on Environment 2 clicks "Checkout"
    ↓
Frontend calls GET /api/storefront/2/payment-gateways
    ↓
Backend StorefrontController.getPaymentGateways(environmentId: 2)
    ↓
Get environment record (id: 2)
    ↓
Query EnvironmentPaymentConfig for environment 2
    ↓
Check: use_centralized_gateways == true?
    ↓
YES → Set targetEnvironmentId = 1
    ↓
Query PaymentGatewaySetting::withoutGlobalScopes()
  WHERE environment_id = 1
    AND status = true
    AND code NOT IN ('lygos')
    ↓
Return: [Stripe, PayPal, Flutterwave]
    ↓
Frontend displays payment options
    ↓
✅ User can complete checkout
```

---

## Code Changes Summary

### File Modified
**Path**: `/app/Http/Controllers/Api/StorefrontController.php`

### Changes Made

#### Change 1: Add `withoutGlobalScopes()` (Line 1522)
**Before**:
```php
$gateways = PaymentGatewaySetting::where('environment_id', $targetEnvironmentId)
    ->where('status', true)
    ->whereNotIn('code', $excludeGateways)
    ->orderBy('sort_order')
    ->get();
```

**After**:
```php
// Use withoutGlobalScopes() to bypass EnvironmentScope when fetching centralized gateways
$gateways = PaymentGatewaySetting::withoutGlobalScopes()
    ->where('environment_id', $targetEnvironmentId)
    ->where('status', true)
    ->whereNotIn('code', $excludeGateways)
    ->orderBy('sort_order')
    ->get();
```

#### Change 2: Add Logging (Lines 1517-1527)
**After the centralized check**:
```php
if ($paymentConfig && $paymentConfig->use_centralized_gateways) {
    $targetEnvironmentId = 1;
    \Log::info('Using centralized payment gateways from environment 1', [
        'requesting_environment' => $environment->id,
        'target_environment' => $targetEnvironmentId
    ]);
} else {
    \Log::info('Using local payment gateways', [
        'environment' => $environment->id,
        'has_config' => $paymentConfig !== null,
        'use_centralized' => $paymentConfig ? $paymentConfig->use_centralized_gateways : null
    ]);
}
```

---

## Testing Checklist

### Backend Testing
- [x] ✅ Environment 1 with local gateways - Returns environment 1's gateways
- [x] ✅ Environment 2 with centralized enabled - Returns environment 1's gateways
- [x] ✅ Environment 3 with centralized enabled - Returns environment 1's gateways
- [x] ✅ Environment 4 with centralized disabled - Returns environment 4's own gateways
- [x] ✅ Logs show correct target environment
- [x] ✅ Global scope bypass works correctly

### Frontend Testing
- [x] ✅ Checkout page on environment 2 shows payment gateways
- [x] ✅ Checkout page on environment 3 shows payment gateways
- [x] ✅ No "Payment not available" error
- [x] ✅ Payment gateway selection works
- [x] ✅ Payment processing works end-to-end

### Edge Cases
- [x] ✅ Environment with no EnvironmentPaymentConfig - Falls back to local
- [x] ✅ Environment 1 with no active gateways - Shows error (expected)
- [x] ✅ Environment with centralized but environment 1 has no gateways - Shows error (expected)

---

## Related Code Components

### Backend Services
1. **EnvironmentPaymentConfigService** (`/app/Services/EnvironmentPaymentConfigService.php`)
   - `getConfig(int $environmentId)` - Gets payment config
   - `isCentralized(int $environmentId)` - Checks centralization flag
   - `getEffectiveEnvironmentId(int $environmentId)` - Returns 1 if centralized, else original

2. **PaymentService** (`/app/Services/PaymentService.php`)
   - Uses `getEffectiveEnvironmentId()` for transaction processing
   - Already correctly implements centralized logic ✅

### Frontend Services
1. **StorefrontService** (`/lib/services/storefront-service.ts`)
   - `getPaymentGateways(environmentId)` - Fetches gateways from API ✅

2. **Checkout Component** (`/components/checkout/checkout-client.tsx`)
   - Calls `StorefrontService.getPaymentGateways()`
   - Displays "Payment not available" if empty array returned ✅

---

## Similar Implementations (For Reference)

### BillingPaymentGatewayController
**Location**: `/app/Http/Controllers/Api/BillingPaymentGatewayController.php`

This controller **already correctly uses** `withoutGlobalScopes()`:

```php
$query = PaymentGatewaySetting::withoutGlobalScopes()
    ->where('environment_id', 1)
    ->where('code', '!=', 'lygos');
```

This is for subscription billing and always uses environment 1 gateways.

---

## Lessons Learned

1. **Global Scopes Are Powerful But Hidden**
   - The `BelongsToEnvironment` trait adds automatic filtering
   - This is helpful for multi-tenancy but can cause issues when intentionally querying across environments
   - Always consider global scopes when querying models that use traits

2. **Always Add Logging for Complex Logic**
   - Centralized vs local gateway logic is not immediately obvious
   - Logging helps debug issues faster
   - Future developers will appreciate the context

3. **Test Cross-Environment Scenarios**
   - Multi-tenant features need testing across different environments
   - Don't assume global scopes won't interfere
   - Test with realistic environment configurations

4. **Reference Similar Implementations**
   - `BillingPaymentGatewayController` already had the correct pattern
   - Could have caught this sooner by comparing implementations

---

## Future Improvements

### Potential Enhancements
1. **Auto-Create EnvironmentPaymentConfig**
   - When creating a new environment, auto-create config with `use_centralized_gateways = true`
   - Prevents missing config edge case

2. **Admin UI Indicator**
   - Show visual indicator in admin panel when environment uses centralized gateways
   - "Using Platform Payment Gateways (Environment 1)"

3. **Centralized Gateway Testing**
   - Add automated test to verify centralized gateway logic
   - Test that withoutGlobalScopes() works correctly

4. **Environment Health Check**
   - Add health check endpoint that verifies payment gateway availability
   - Warn admins if environment has no available gateways

---

## Debugging Tips

### How to Debug Future Issues

1. **Check Logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep "payment gateways"
   ```

2. **Verify Environment Config**:
   ```sql
   SELECT * FROM environment_payment_configs WHERE environment_id = 2;
   ```

3. **Check Gateway Settings**:
   ```sql
   SELECT id, environment_id, code, status
   FROM payment_gateway_settings
   WHERE environment_id = 1
   AND status = 1;
   ```

4. **Test API Directly**:
   ```bash
   curl -H "Authorization: Bearer {token}" \
        https://api.example.com/api/storefront/2/payment-gateways
   ```

5. **Check Frontend Network Tab**:
   - Open DevTools → Network tab
   - Look for `/payment-gateways` request
   - Verify response has `data` array with gateways

---

## Related Issues

- ✅ **Fixed**: Enrollment code commission tracking
- ✅ **Fixed**: Block reorder state management
- ✅ **Fixed**: Centralized payment gateway scope issue

---

**Fix Verified**: 2026-02-07
**Tested By**: Development Team
**Status**: Production Ready ✅
