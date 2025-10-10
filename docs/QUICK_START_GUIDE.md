# Payment Gateway Centralization - Quick Start Guide

## Overview
Add an **optional** centralized payment processing system where environments can opt-in to process transactions through Environment 1's gateways. The current environment-specific gateway model remains the default. Includes instructor commission tracking and withdrawal management for environments using centralized processing.

---

## ⚠️ IMPORTANT: Brownfield Context

### What Already EXISTS in the Codebase:
- ✅ `PaymentGatewaySetting` model - Environment-specific gateway configs
- ✅ `Transaction` model - With `fee_amount`, `tax_amount` fields
- ✅ `Commission` model - **Platform fee RATE configuration** (NOT transaction tracking)
- ✅ `CommissionService` - Calculates fees from product prices
- ✅ `PaymentService` - Processes payments with commission calculation
- ✅ Gateway integrations: Stripe, MonetBill, Lygos, PayPal

### What DOES NOT EXIST (To Be Built):
- ❌ `EnvironmentPaymentConfig` model - Opt-in system for centralized gateways
- ❌ `InstructorCommission` model - Transaction-level commission tracking for payouts
- ❌ `WithdrawalRequest` model - Withdrawal management
- ❌ Centralized gateway routing logic in `PaymentService`
- ❌ Admin controllers for commission/withdrawal management
- ❌ Instructor endpoints for earnings and withdrawals
- ❌ Frontend pages (admin and instructor)

---

## Key Changes Summary

### 1. New Database Tables (3 tables)
| Table | Purpose |
|-------|---------|
| `environment_payment_configs` | Opt-in settings per environment for centralized gateways |
| `instructor_commissions` | Track instructor earnings per transaction (different from platform fee `commissions` table) |
| `withdrawal_requests` | Instructor withdrawal management workflow |

### 2. New Services (3 services)
- `EnvironmentPaymentConfigService` - Manage centralized gateway opt-in
- `InstructorCommissionService` - Track instructor earnings and balances
- `WithdrawalService` - Handle withdrawal requests and processing

### 3. Modified Services (1 service)
- `PaymentService` - Add centralized gateway routing logic

### 4. Admin Features (CSL-Sales-Website - 4 pages)
- `/admin/transactions` - View all cross-environment transactions
- `/admin/commissions` - Approve/manage instructor commission payouts
- `/admin/withdrawals` - Process instructor withdrawal requests
- `/admin/payment-settings` - Configure environment payment opt-ins

### 5. Instructor Features (CSL-Certification - 3 pages)
- `/instructor/earnings` - View commission history and balance
- `/instructor/withdrawals` - Request and track withdrawals
- `/instructor/payment-settings` - Configure withdrawal method

---

## Implementation Order (7 Weeks)

### Week 1: Database & Models
**Focus**: Create foundation for new functionality

#### Migrations to Create:
1. `create_environment_payment_configs_table`
2. `create_instructor_commissions_table` (NOT to be confused with existing `commissions`)
3. `create_withdrawal_requests_table`

#### Models to Create:
1. `EnvironmentPaymentConfig.php`
2. `InstructorCommission.php`
3. `WithdrawalRequest.php`

#### Models to Update:
1. `Environment.php` - Add relationships
2. `Transaction.php` - Add `instructorCommission()` relationship
3. `Order.php` - Add `instructorCommission()` relationship

**Deliverable**: Run migrations, seed default configs, test relationships

---

### Week 2: Backend Services & Logic
**Focus**: Build business logic for commission tracking and withdrawals

#### Services to Create:
1. `app/Services/EnvironmentPaymentConfigService.php`
   - Methods: `getConfig()`, `updateConfig()`, `enableCentralizedPayments()`, `isCentralized()`

2. `app/Services/InstructorCommissionService.php`
   - Methods: `createCommissionRecord()`, `getAvailableBalance()`, `getTotalEarned()`, `approveCommission()`

3. `app/Services/WithdrawalService.php`
   - Methods: `createWithdrawalRequest()`, `approveWithdrawal()`, `rejectWithdrawal()`, `processWithdrawal()`

#### Services to Modify:
1. `app/Services/PaymentService.php`
   - Add `shouldUseCentralizedGateway()` method
   - Modify `initializeGateway()` to fetch Environment 1's gateway if centralized
   - Call `InstructorCommissionService::createCommissionRecord()` after successful payment

2. `app/Http/Controllers/Api/TransactionController.php`
   - Update `callbackSuccess()` to create commission records

**Deliverable**: Unit tests for services, integration tests for payment flow

---

### Week 3: Admin API Endpoints
**Focus**: Build admin APIs for managing commissions and withdrawals

#### Controllers to Create:
1. `app/Http/Controllers/Api/Admin/CommissionController.php`
   - Routes: `GET /api/admin/commissions`, `POST /api/admin/commissions/{id}/approve`, etc.

2. `app/Http/Controllers/Api/Admin/WithdrawalRequestController.php`
   - Routes: `GET /api/admin/withdrawal-requests`, `POST /api/admin/withdrawal-requests/{id}/approve`, etc.

3. `app/Http/Controllers/Api/Admin/CentralizedTransactionController.php`
   - Routes: `GET /api/admin/centralized-transactions`, `GET /api/admin/centralized-transactions/export`, etc.

4. `app/Http/Controllers/Api/Admin/EnvironmentPaymentConfigController.php`
   - Routes: `GET /api/admin/environment-payment-configs`, `PUT /api/admin/environment-payment-configs/{id}`, etc.

#### Routes to Add (routes/api.php):
```php
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('commissions', CommissionController::class);
    Route::post('commissions/bulk-approve', [CommissionController::class, 'bulkApprove']);

    Route::apiResource('withdrawal-requests', WithdrawalRequestController::class);
    Route::post('withdrawal-requests/{id}/approve', [WithdrawalRequestController::class, 'approve']);
    Route::post('withdrawal-requests/{id}/reject', [WithdrawalRequestController::class, 'reject']);
    Route::post('withdrawal-requests/{id}/process', [WithdrawalRequestController::class, 'process']);

    Route::get('centralized-transactions', [CentralizedTransactionController::class, 'index']);
    Route::get('centralized-transactions/stats', [CentralizedTransactionController::class, 'stats']);
    Route::get('centralized-transactions/export', [CentralizedTransactionController::class, 'export']);

    Route::apiResource('environment-payment-configs', EnvironmentPaymentConfigController::class);
    Route::post('environment-payment-configs/{id}/toggle', [EnvironmentPaymentConfigController::class, 'toggle']);
});
```

**Deliverable**: Postman collection for testing all admin endpoints

---

### Week 4: Instructor API Endpoints
**Focus**: Build instructor-facing APIs for earnings and withdrawals

#### Controllers to Create:
1. `app/Http/Controllers/Api/Instructor/EarningsController.php`
   - Routes: `GET /api/instructor/earnings`, `GET /api/instructor/earnings/stats`, etc.

2. `app/Http/Controllers/Api/Instructor/WithdrawalController.php`
   - Routes: `GET /api/instructor/withdrawals`, `POST /api/instructor/withdrawals`, etc.

3. `app/Http/Controllers/Api/Instructor/PaymentConfigController.php`
   - Routes: `GET /api/instructor/payment-config`, `PUT /api/instructor/payment-config`

#### Routes to Add (routes/api.php):
```php
Route::prefix('instructor')->middleware(['auth:sanctum', 'instructor'])->group(function () {
    Route::get('earnings', [EarningsController::class, 'index']);
    Route::get('earnings/stats', [EarningsController::class, 'stats']);
    Route::get('earnings/balance', [EarningsController::class, 'balance']);

    Route::get('withdrawals', [WithdrawalController::class, 'index']);
    Route::post('withdrawals', [WithdrawalController::class, 'store']);
    Route::get('withdrawals/{id}', [WithdrawalController::class, 'show']);

    Route::get('payment-config', [PaymentConfigController::class, 'show']);
    Route::put('payment-config', [PaymentConfigController::class, 'update']);
});
```

**Deliverable**: API documentation (Swagger/OpenAPI), instructor API tests

---

### Week 5: Admin Frontend (CSL-Sales-Website)
**Focus**: Build admin dashboard pages for managing commissions and withdrawals

#### Pages to Create:
1. `app/admin/transactions/page.tsx`
   - Cross-environment transaction list
   - Filters: environment, status, date range, payment method
   - Export to CSV functionality

2. `app/admin/commissions/page.tsx`
   - Instructor commission list
   - Bulk approve functionality
   - Statistics cards: Total Owed, Total Paid, Pending

3. `app/admin/withdrawals/page.tsx`
   - Withdrawal request list
   - Approve/Reject modals
   - Process payment form

4. `app/admin/payment-settings/page.tsx`
   - Environment list with centralized gateway toggle
   - Commission rate configuration
   - Payment terms settings

#### Components to Create:
- `TransactionTable.tsx` - Filterable transaction table
- `CommissionApprovalModal.tsx` - Bulk approval interface
- `WithdrawalDetailsModal.tsx` - Withdrawal details + actions
- `PaymentConfigForm.tsx` - Environment payment settings form

**Deliverable**: Functional admin dashboard with all 4 pages

---

### Week 6: Instructor Frontend (CSL-Certification)
**Focus**: Build instructor pages for viewing earnings and requesting withdrawals

#### Pages to Create:
1. `app/instructor/earnings/page.tsx`
   - Commission history table
   - Filters: status, date range
   - Statistics: Total Earned, Total Paid, Available Balance
   - Download statements button

2. `app/instructor/withdrawals/page.tsx`
   - Withdrawal request form
   - Withdrawal history table
   - Status tracking

3. `app/instructor/payment-settings/page.tsx`
   - Configure withdrawal method (bank transfer, PayPal, mobile money)
   - Save account details (encrypted)

#### Dashboard Widget:
- Update `app/instructor/dashboard/page.tsx`
  - Add earnings summary widget
  - Add quick withdrawal button
  - Show pending commission count

**Deliverable**: Functional instructor pages with withdrawal workflow

---

### Week 7: Testing & Deployment
**Focus**: Comprehensive testing and production deployment

#### Testing Tasks:
1. **Unit Tests**
   - Service tests (InstructorCommissionService, WithdrawalService)
   - Model relationship tests
   - Commission calculation accuracy tests

2. **Integration Tests**
   - End-to-end checkout with centralized gateway
   - Commission record creation on successful payment
   - Withdrawal approval workflow
   - Refund handling with commission reversal

3. **Manual Testing**
   - Test with real Stripe/MonetBil test accounts
   - Test environment gateway fallback
   - Test withdrawal processing
   - Test commission approval workflow

#### Deployment Tasks:
1. **Database Migration**
   - Run migrations on staging
   - Seed default `EnvironmentPaymentConfig` for all environments
   - Optional: Backfill commission records for historical transactions

2. **Documentation**
   - API documentation update
   - Admin user guide
   - Instructor user guide
   - Rollback procedures

3. **Production Deployment**
   - Deploy API changes
   - Deploy admin frontend
   - Deploy instructor frontend
   - Monitor error logs and transaction success rates

**Deliverable**: Production-ready feature with full test coverage

---

## Critical Implementation Notes

### 1. Commission Model Naming Conflict
**⚠️ WARNING**: Existing `Commission` model is for platform fee RATES.
New model `InstructorCommission` is for transaction-level payout tracking.

**Do NOT confuse these two:**
- `Commission` → Platform fee rate configuration (e.g., "17% fee")
- `InstructorCommission` → Individual commission records (e.g., "$100 owed to instructor for Order #123")

### 2. Centralized Gateway Routing Logic
In `PaymentService::initializeGateway()`:
```php
// BEFORE (existing):
$gatewaySettings = PaymentGatewaySetting::where('environment_id', $environmentId)->first();

// AFTER (new logic):
if ($this->environmentPaymentConfigService->isCentralized($environmentId)) {
    // Fetch Environment 1's gateway
    $gatewaySettings = PaymentGatewaySetting::where('environment_id', 1)->first();
} else {
    // Use environment's own gateway
    $gatewaySettings = PaymentGatewaySetting::where('environment_id', $environmentId)->first();
}
```

### 3. Commission Record Creation
After successful transaction callback:
```php
// In TransactionController::callbackSuccess()
if ($transaction->status === 'completed') {
    $this->instructorCommissionService->createCommissionRecord($transaction);
}
```

### 4. Withdrawal Balance Calculation
```php
// Available balance = Approved commissions - Withdrawn amounts
$availableBalance = InstructorCommission::where('environment_id', $environmentId)
    ->where('status', 'approved')
    ->whereNull('withdrawal_request_id')
    ->sum('net_amount');
```

---

## Key Decisions Needed Before Starting

1. **Commission Rate for Instructors**: Default rate? (Suggested: 15%)
2. **Minimum Withdrawal Amount**: Minimum? (Suggested: 50,000 XAF)
3. **Payment Terms**: NET 30, NET 60, or immediate? (Suggested: NET 30)
4. **Historical Data**: Backfill commission records for past transactions?
5. **Model Naming**: Use `InstructorCommission` or `CommissionRecord`?
6. **Gateway Fallback**: If Environment 1 gateway fails, fall back to environment gateway?

---

## Quick Reference: File Locations

### Backend (CSL-Certification-Rest-API)
```
app/
├── Models/
│   ├── EnvironmentPaymentConfig.php          (NEW)
│   ├── InstructorCommission.php              (NEW)
│   └── WithdrawalRequest.php                 (NEW)
├── Services/
│   ├── EnvironmentPaymentConfigService.php   (NEW)
│   ├── InstructorCommissionService.php       (NEW)
│   ├── WithdrawalService.php                 (NEW)
│   └── PaymentService.php                    (MODIFY)
└── Http/Controllers/Api/
    ├── Admin/
    │   ├── CommissionController.php          (NEW)
    │   ├── WithdrawalRequestController.php   (NEW)
    │   ├── CentralizedTransactionController.php (NEW)
    │   └── EnvironmentPaymentConfigController.php (NEW)
    ├── Instructor/
    │   ├── EarningsController.php            (NEW)
    │   ├── WithdrawalController.php          (NEW)
    │   └── PaymentConfigController.php       (NEW)
    └── TransactionController.php             (MODIFY)
```

### Frontend Admin (CSL-Sales-Website)
```
app/admin/
├── transactions/page.tsx                      (NEW)
├── commissions/page.tsx                       (NEW)
├── withdrawals/page.tsx                       (NEW)
└── payment-settings/page.tsx                  (NEW)
```

### Frontend Instructor (CSL-Certification)
```
app/instructor/
├── earnings/page.tsx                          (NEW)
├── withdrawals/page.tsx                       (NEW)
├── payment-settings/page.tsx                  (NEW)
└── dashboard/page.tsx                         (MODIFY - add earnings widget)
```

---

## Next Step
Review `PAYMENT_GATEWAY_CENTRALIZATION.md` for full specification, then start with **Week 1: Database & Models**.

---

**Document Version**: 2.0 (Corrected with Brownfield Analysis)
**Last Updated**: 2025-10-08
**Analyzed By**: Mary (Business Analyst)
