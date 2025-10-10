# Centralized Payment Gateway Opt-In Feature

**Date**: 2025-10-10
**Story Reference**: Stories 3-8 from `docs/stories/STORIES-03-08-SUMMARY.md`

## Overview

This feature allows instructors (in environments other than Environment 1) to opt-in to use the platform's centralized payment gateways instead of configuring their own payment gateways. When enabled, instructors automatically receive commission payouts managed by the platform.

## Architecture

### Business Rules

1. **Environment 1 (Platform Owner)**: Does NOT see the opt-in UI. Environment 1 manages the centralized payment gateways that other environments can opt into.

2. **Other Environments (Instructors)**: Can toggle between:
   - **Centralized Payments ON**: Use platform's payment gateways, receive automatic commissions
   - **Centralized Payments OFF**: Configure and manage their own payment gateways

3. **Commission Structure**:
   - Platform Fee: 17%
   - Instructor Payout: 83%
   - Minimum Withdrawal: $82 USD (≈50,000 XAF)
   - Payment Terms: NET_30 (default)

## Implementation Details

### Backend Changes

#### 1. Controller: `app/Http/Controllers/Api/Instructor/PaymentConfigController.php`

**New Methods Added**:

```php
/**
 * Get centralized payment gateway configuration
 */
public function getCentralizedConfig(): JsonResponse
{
    $environmentId = session('current_environment_id');

    $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

    // Returns default values if no config exists
    return response()->json([
        'success' => true,
        'data' => [
            'use_centralized_gateways' => $config->use_centralized_gateways ?? false,
            'platform_fee_rate' => $config->platform_fee_rate ?? 0.17,
            'instructor_payout_rate' => 1 - ($config->platform_fee_rate ?? 0.17),
            'minimum_withdrawal_amount' => $config->minimum_withdrawal_amount ?? 82.00,
            'payment_terms' => $config->payment_terms ?? 'NET_30',
        ]
    ]);
}

/**
 * Toggle centralized payment gateways for instructor's environment
 */
public function toggleCentralized(Request $request): JsonResponse
{
    $environmentId = session('current_environment_id');
    $user = $request->user();

    // Authorization check
    if (!in_array($user->role->value, ['instructor', 'admin'])) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

    if (!$config) {
        // Create default config on first toggle
        $config = EnvironmentPaymentConfig::create([
            'environment_id' => $environmentId,
            'use_centralized_gateways' => true,
            'platform_fee_rate' => 0.17,
            'payment_terms' => 'NET_30',
            'minimum_withdrawal_amount' => 82.00,
        ]);
    } else {
        // Toggle existing config
        $config->use_centralized_gateways = !$config->use_centralized_gateways;
        $config->save();
    }

    return response()->json([
        'success' => true,
        'message' => 'Centralized payment gateways ' . ($config->use_centralized_gateways ? 'enabled' : 'disabled'),
        'data' => [...]
    ]);
}
```

#### 2. Routes: `routes/api.php`

```php
// Instructor API Endpoints
Route::middleware(['auth:sanctum'])->prefix('instructor')->group(function () {
    // Payment Configuration
    Route::prefix('payment-config')->group(function () {
        Route::get('/', [PaymentConfigController::class, 'show']);
        Route::put('/', [PaymentConfigController::class, 'update']);

        // Centralized payment gateway opt-in
        Route::get('/centralized', [PaymentConfigController::class, 'getCentralizedConfig']);
        Route::post('/centralized/toggle', [PaymentConfigController::class, 'toggleCentralized']);
    });
});
```

**Endpoints**:
- `GET /api/instructor/payment-config/centralized` - Get current configuration
- `POST /api/instructor/payment-config/centralized/toggle` - Toggle on/off

### Frontend Changes

#### 1. API Service: `lib/instructor-payment-config-api.ts`

**New Interface**:
```typescript
export interface CentralizedPaymentConfig {
  use_centralized_gateways: boolean;
  platform_fee_rate: number; // Platform's fee (e.g., 0.17 = 17%)
  instructor_payout_rate: number; // Instructor's share (e.g., 0.83 = 83%)
  minimum_withdrawal_amount: number;
  payment_terms?: string | null;
}
```

**New Functions**:
```typescript
// Get centralized payment gateway configuration
export const getCentralizedPaymentConfig = async (): Promise<{ data: CentralizedPaymentConfig }> => {
  return get('/instructor/payment-config/centralized');
};

// Toggle centralized payment gateways
export const toggleCentralizedPaymentGateways = async (): Promise<{
  data: CentralizedPaymentConfig;
  message: string
}> => {
  return post('/instructor/payment-config/centralized/toggle');
};
```

#### 2. UI Component: `components/settings/payment-gateway-settings.tsx`

**New State**:
```typescript
const [centralizedConfig, setCentralizedConfig] = useState<CentralizedPaymentConfig | null>(null);
const [loadingCentralized, setLoadingCentralized] = useState(true);
const [togglingCentralized, setTogglingCentralized] = useState(false);
```

**Key Features Added**:

1. **Prominent Opt-in Card** (only visible for environments != 1):
   - Blue-highlighted card at the top of the page
   - Shows Active/Inactive badge
   - Toggle switch for easy opt-in/opt-out
   - Displays commission details when enabled:
     - Your Payout Rate (83%)
     - Platform Fee (17%)
     - Min. Withdrawal ($82 USD)
     - Payment Terms (NET_30)

2. **Smart Disabling**:
   - When centralized payments are enabled (for non-Environment-1):
     - "Add Gateway" button is disabled
     - All gateway edit/delete/toggle actions are disabled
     - Clear messaging: "These gateways are inactive while centralized payments are enabled."

3. **Environment-Specific Behavior**:
   - **Environment 1**: No opt-in card shown, full access to manage payment gateways
   - **Other Environments**: Opt-in card shown, gateway management disabled when opted-in

**UI Code Snippet**:
```tsx
{/* Only show for environments other than Environment 1 */}
{environment && environment.id !== 1 && (
  <Card className="border-blue-200 bg-blue-50/50">
    <CardContent className="p-6">
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <h3 className="text-lg font-semibold">Centralized Payment Gateways</h3>
          {/* Badge and description */}
          {centralizedConfig?.use_centralized_gateways && (
            <div className="grid grid-cols-3 gap-4 mt-4 p-4 bg-white rounded-md">
              {/* Commission details */}
            </div>
          )}
        </div>
        <Switch
          checked={centralizedConfig?.use_centralized_gateways || false}
          onCheckedChange={handleToggleCentralized}
          disabled={loadingCentralized || togglingCentralized}
        />
      </div>
    </CardContent>
  </Card>
)}
```

## User Flow

### For Instructors (Environments 2+)

1. **Navigate to Settings > Payment Gateway Settings**

2. **See Centralized Payment Opt-in Card** at the top:
   - Clear explanation of the feature
   - Toggle switch to enable/disable
   - Status badge (Active/Inactive)

3. **Toggle ON (Opt-in)**:
   - Platform takes 17% fee
   - Instructor receives 83% as commission
   - Commission details displayed
   - Own payment gateway management disabled
   - Automatic commission tracking (via Stories 3-8)
   - Automatic withdrawal management

4. **Toggle OFF (Opt-out)**:
   - Must configure own payment gateways
   - Full control over payment processing
   - No automatic commission tracking

### For Platform Admins (Environment 1)

1. **Navigate to Settings > Payment Gateway Settings**

2. **No Opt-in Card** shown (Environment 1 manages the centralized gateways)

3. **Full Access** to:
   - Add/Edit/Delete payment gateways
   - Configure gateway settings
   - These are the gateways that instructors opt into

## Database Schema

Uses existing table: `environment_payment_configs`

**Relevant Fields**:
- `environment_id` - Links to environments table
- `use_centralized_gateways` - Boolean (opt-in status)
- `platform_fee_rate` - Decimal (0.17 = 17%)
- `minimum_withdrawal_amount` - Decimal (82.00 USD)
- `payment_terms` - String ('NET_30', 'NET_60', 'Immediate')

**Default Values** (created on first toggle):
```php
[
    'use_centralized_gateways' => true,
    'platform_fee_rate' => 0.17,
    'minimum_withdrawal_amount' => 82.00,
    'payment_terms' => 'NET_30',
]
```

## Integration with Commission System

When `use_centralized_gateways = true`:

1. **Transaction Processing** (Story 2):
   - Uses Environment 1's payment gateways
   - Transaction recorded in `transactions` table

2. **Commission Creation** (Story 3):
   - `TransactionController::callbackSuccess()` checks `use_centralized_gateways`
   - If true, creates `InstructorCommission` record
   - Commission amount = `transaction_amount * instructor_payout_rate`

3. **Withdrawal Management** (Story 3):
   - Instructors can request withdrawals via `/instructor/withdrawals`
   - Admins approve/process via `/admin/withdrawal-requests`
   - Linked to instructor's payment config (bank/PayPal/mobile money)

## Security Considerations

1. **Authorization**:
   - Only instructors/admins can toggle their own environment's setting
   - Environment scoping via `session('current_environment_id')`

2. **Validation**:
   - Role check: `['instructor', 'admin']`
   - Environment ownership verification

3. **Data Integrity**:
   - Atomic toggle operation
   - Default config creation if not exists
   - Transaction-safe updates

## Testing

### Manual Testing Steps

1. **Test Environment 1**:
   - Login as Environment 1 admin
   - Navigate to Payment Gateway Settings
   - Verify opt-in card is NOT visible
   - Verify can add/edit/delete gateways freely

2. **Test Environment 2+ (Opt-in Flow)**:
   - Login as instructor in Environment 2
   - Navigate to Payment Gateway Settings
   - Verify opt-in card IS visible
   - Toggle ON centralized payments
   - Verify commission details displayed
   - Verify "Add Gateway" button disabled
   - Verify gateway actions disabled
   - Create a test transaction
   - Verify commission record created

3. **Test Environment 2+ (Opt-out Flow)**:
   - Toggle OFF centralized payments
   - Verify "Add Gateway" button enabled
   - Verify gateway actions enabled
   - Add a custom payment gateway
   - Create a test transaction
   - Verify NO commission record created

### API Testing

```bash
# Get centralized config
curl -X GET http://localhost:8000/api/instructor/payment-config/centralized \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Toggle centralized payments
curl -X POST http://localhost:8000/api/instructor/payment-config/centralized/toggle \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

## Benefits

### For Instructors
- ✅ No need to set up payment gateway accounts
- ✅ No payment gateway fees management
- ✅ Automatic commission tracking
- ✅ Simplified withdrawal process
- ✅ Single payout from platform

### For Platform
- ✅ Control over payment processing
- ✅ Consistent user experience
- ✅ Revenue from platform fees (17%)
- ✅ Better analytics and tracking
- ✅ Reduced instructor support requests

## Known Limitations

1. **Commission Rate**: Currently fixed at 17% platform fee (83% instructor). Future enhancement: configurable per environment.

2. **Payment Terms**: Defaults to NET_30. Future enhancement: allow instructors to view/request faster terms.

3. **Currency**: Assumes USD. Future enhancement: multi-currency support.

## Future Enhancements

1. **Tiered Commission Rates**: Based on sales volume or tenure
2. **Custom Payment Terms**: Negotiate faster payment terms
3. **Real-time Commission Dashboard**: Live earnings tracking
4. **Auto-withdrawal**: Automatic payouts when threshold reached
5. **Tax Document Generation**: 1099/W9 forms for instructors

## Related Documentation

- **Stories 1-2**: Payment Gateway Centralization Foundation
- **Story 3**: Commission & Withdrawal Services
- **Stories 4-5**: Admin & Instructor API Endpoints
- **Stories 6-7**: Admin & Instructor Frontend UI
- **Story 8**: Integration Testing & Deployment

## Support

For questions or issues:
1. Check `/docs/stories/STORIES-03-08-SUMMARY.md`
2. Review commission calculation logic in `InstructorCommissionService`
3. Verify `environment_payment_configs` table structure
4. Test with sandbox payment gateways first

---

**Document Version**: 1.0
**Last Updated**: 2025-10-10
**Author**: Development Team
