# Stories 3-8: Payment Gateway Centralization - Summary

**Epic:** EPIC-PGC-001
**Created:** 2025-10-08

This document provides a consolidated summary of Stories 3-8. For Stories 1-2, see individual story files.

---

## Story 3: Commission & Withdrawal Services (PGC-003)

**User Story:** As a **system**, I want to track instructor earnings and manage withdrawal requests, so that instructors can be paid for transactions processed through centralized gateways.

**Duration:** 1 week | **Depends On:** Story 1, Story 2

### Key Deliverables
1. **InstructorCommissionService** (`app/Services/InstructorCommissionService.php`)
   - `createCommissionRecord(Transaction $transaction): InstructorCommission`
   - `calculateNetEarnings(int $environmentId): float`
   - `getTotalEarned(int $environmentId): float`
   - `getTotalPaid(int $environmentId): float`
   - `getAvailableBalance(int $environmentId): float`
   - `getCommissions(int $environmentId, array $filters): Collection`
   - `approveCommission(InstructorCommission $commission): bool`

2. **WithdrawalService** (`app/Services/WithdrawalService.php`)
   - `createWithdrawalRequest(int $environmentId, float $amount, array $details): WithdrawalRequest`
   - `approveWithdrawal(WithdrawalRequest $request): bool`
   - `rejectWithdrawal(WithdrawalRequest $request, string $reason): bool`
   - `processWithdrawal(WithdrawalRequest $request, string $reference): bool`
   - `getAvailableBalance(int $environmentId): float`
   - `validateWithdrawalAmount(int $environmentId, float $amount): bool`

3. **Modify TransactionController**
   - Update `callbackSuccess()` to create commission records on successful transactions
   - Only create records for environments using centralized gateways

### Critical Logic

**Commission Record Creation:**
```php
// In TransactionController::callbackSuccess()
if ($transaction->status === 'completed') {
    $environmentConfig = $this->environmentPaymentConfigService->getConfig($transaction->environment_id);

    if ($environmentConfig && $environmentConfig->use_centralized_gateways) {
        $this->instructorCommissionService->createCommissionRecord($transaction);
    }
}
```

**Balance Calculation:**
```php
// Available balance = Approved commissions - Withdrawn amounts
$availableBalance = InstructorCommission::where('environment_id', $environmentId)
    ->where('status', 'approved')
    ->whereNull('withdrawal_request_id')
    ->sum('net_amount');
```

### Testing Requirements
- Unit tests for both services (>90% coverage)
- Test commission record creation on successful transaction
- Test balance calculations (edge cases: negative, zero, very large)
- Test withdrawal validation (minimum amount, available balance)
- Test approval/rejection workflows
- Regression test: existing transaction flow unchanged

---

## Story 4: Admin API Endpoints (PGC-004)

**User Story:** As a **super admin**, I want API endpoints to manage commissions, withdrawals, and payment configurations, so that I can approve payouts and configure centralized payments.

**Duration:** 1 week | **Depends On:** Story 2, Story 3

### Controllers to Create

#### 1. CommissionController (`app/Http/Controllers/Api/Admin/CommissionController.php`)
**Routes:**
```php
GET    /api/admin/commissions              // List all instructor commissions
GET    /api/admin/commissions/{id}         // Get commission details
POST   /api/admin/commissions/{id}/approve // Approve a commission
POST   /api/admin/commissions/bulk-approve // Approve multiple commissions
GET    /api/admin/commissions/stats        // Commission statistics
GET    /api/admin/commissions/environment/{environmentId} // Filter by environment
```

**Key Methods:**
- `index()`: Paginated list with filters (environment, status, date range)
- `show()`: Single commission with relationships
- `approve()`: Approve commission, set status to 'approved'
- `bulkApprove()`: Approve multiple commissions by IDs
- `stats()`: Total owed, total paid, pending approval counts

#### 2. WithdrawalRequestController (`app/Http/Controllers/Api/Admin/WithdrawalRequestController.php`)
**Routes:**
```php
GET    /api/admin/withdrawal-requests              // List all requests
GET    /api/admin/withdrawal-requests/{id}         // Get request details
POST   /api/admin/withdrawal-requests/{id}/approve // Approve request
POST   /api/admin/withdrawal-requests/{id}/reject  // Reject request
POST   /api/admin/withdrawal-requests/{id}/process // Mark as processed/paid
GET    /api/admin/withdrawal-requests/stats        // Withdrawal stats
```

**Key Methods:**
- `index()`: Paginated list with filters (status, environment, date)
- `show()`: Request with related commissions
- `approve()`: Approve request, set status to 'approved', set `approved_by` and `approved_at`
- `reject()`: Reject request, set status to 'rejected', save rejection_reason
- `process()`: Mark as processed, set status to 'completed', save payment_reference

#### 3. CentralizedTransactionController (`app/Http/Controllers/Api/Admin/CentralizedTransactionController.php`)
**Routes:**
```php
GET    /api/admin/centralized-transactions                  // All transactions
GET    /api/admin/centralized-transactions/{id}             // Transaction details
GET    /api/admin/centralized-transactions/stats            // Transaction stats
GET    /api/admin/centralized-transactions/environment/{id} // Filter by environment
GET    /api/admin/centralized-transactions/export           // Export to CSV/Excel
```

**Key Methods:**
- `index()`: List all transactions from environments using centralized gateways
- `stats()`: Total revenue, average transaction, success rate
- `export()`: Export to CSV with filters

#### 4. EnvironmentPaymentConfigController (`app/Http/Controllers/Api/Admin/EnvironmentPaymentConfigController.php`)
**Routes:**
```php
GET    /api/admin/environment-payment-configs                        // List all configs
GET    /api/admin/environment-payment-configs/{environmentId}        // Get config
PUT    /api/admin/environment-payment-configs/{environmentId}        // Update config
POST   /api/admin/environment-payment-configs/{environmentId}/toggle // Toggle centralized
```

**Key Methods:**
- `index()`: List all environment payment configs
- `show()`: Get config for specific environment
- `update()`: Update config (commission rate, payment terms, etc.)
- `toggle()`: Toggle `use_centralized_gateways` flag

### Validation Rules
- Use Laravel Form Requests for all POST/PUT endpoints
- Validate withdrawal amounts (>= minimum, <= available balance)
- Validate commission approval (must be 'pending')
- Validate payment reference on process (required, unique)

### Authorization
- All endpoints require `auth:sanctum` middleware
- All endpoints require super admin role check
- Instructors cannot access admin endpoints

### API Documentation
- OpenAPI/Swagger documentation for all endpoints
- Example requests/responses
- Error response formats
- Postman collection for testing

---

## Story 5: Instructor API Endpoints (PGC-005)

**User Story:** As an **instructor**, I want API endpoints to view my earnings and request withdrawals, so that I can track my income and get paid.

**Duration:** 1 week | **Depends On:** Story 3

### Controllers to Create

#### 1. EarningsController (`app/Http/Controllers/Api/Instructor/EarningsController.php`)
**Routes:**
```php
GET /api/instructor/earnings                // List commissions
GET /api/instructor/earnings/stats          // Earnings statistics
GET /api/instructor/earnings/balance        // Available balance
```

**Key Methods:**
- `index()`: List instructor's commissions (paginated, filtered)
- `stats()`: Total earned, total paid, pending amount, available balance
- `balance()`: Available balance for withdrawal

#### 2. WithdrawalController (`app/Http/Controllers/Api/Instructor/WithdrawalController.php`)
**Routes:**
```php
GET  /api/instructor/withdrawals            // List withdrawal requests
POST /api/instructor/withdrawals            // Create withdrawal request
GET  /api/instructor/withdrawals/{id}       // Get withdrawal details
```

**Key Methods:**
- `index()`: List instructor's withdrawal requests
- `store()`: Create new withdrawal request (validates balance)
- `show()`: Get withdrawal request details

#### 3. PaymentConfigController (`app/Http/Controllers/Api/Instructor/PaymentConfigController.php`)
**Routes:**
```php
GET /api/instructor/payment-config          // Get payment configuration
PUT /api/instructor/payment-config          // Update withdrawal method/details
```

**Key Methods:**
- `show()`: Get current payment config (withdrawal method, details)
- `update()`: Update withdrawal method and account details

### Validation Rules
- Withdrawal amount validation:
  - >= `minimum_withdrawal_amount` from environment config
  - <= available balance
- Withdrawal method validation: must be one of ['bank_transfer', 'paypal', 'mobile_money']
- Withdrawal details validation: required fields based on method

### Authorization
- All endpoints require `auth:sanctum` middleware
- All endpoints require instructor role
- Instructors can only access their own data (scoped by `environment_id`)

---

## Story 6: Admin Frontend UI (CSL-Sales-Website) (PGC-006)

**User Story:** As a **super admin**, I want UI pages to manage commissions and withdrawals, so that I can visually approve payouts and configure payment settings.

**Duration:** 1 week | **Depends On:** Story 4

### Pages to Create

#### 1. Transactions Dashboard (`app/admin/transactions/page.tsx`)
**Features:**
- Filterable transaction table (environment, status, date range, payment method)
- Columns: Transaction ID, Environment, Amount, Commission, Status, Date
- Export to CSV button
- Pagination (50 per page)
- Real-time status updates

**Components:**
- `TransactionTable.tsx` - Data table with filters
- `TransactionFilters.tsx` - Filter sidebar
- `ExportButton.tsx` - CSV export

#### 2. Commissions Management (`app/admin/commissions/page.tsx`)
**Features:**
- Commission list with bulk selection
- Statistics cards: Total Owed, Total Paid, Pending Approval
- Bulk approve button (select multiple, approve all)
- Filters: Environment, Status, Date Range
- Commission details modal (view breakdown)

**Components:**
- `CommissionTable.tsx` - Table with checkboxes
- `CommissionStatsCards.tsx` - Statistics cards
- `BulkApproveButton.tsx` - Bulk operations
- `CommissionDetailsModal.tsx` - Details modal

#### 3. Withdrawal Requests (`app/admin/withdrawals/page.tsx`)
**Features:**
- Withdrawal request list
- Status badges (pending, approved, processing, completed, rejected)
- Action buttons: Approve, Reject, Process Payment
- Filters: Status, Environment, Date Range
- Withdrawal details modal with commission breakdown

**Components:**
- `WithdrawalTable.tsx` - Withdrawal list
- `WithdrawalActions.tsx` - Action buttons
- `WithdrawalDetailsModal.tsx` - Details + actions
- `RejectModal.tsx` - Rejection reason input
- `ProcessPaymentModal.tsx` - Payment reference input

#### 4. Payment Settings (`app/admin/payment-settings/page.tsx`)
**Features:**
- Environment list with centralized status toggle
- Commission rate configuration (per environment)
- Payment terms dropdown (NET_30, NET_60, Immediate)
- Minimum withdrawal amount input
- Save button (updates config)

**Components:**
- `EnvironmentListTable.tsx` - Environment list
- `CentralizedToggle.tsx` - Toggle switch
- `PaymentConfigForm.tsx` - Configuration form

### UI/UX Requirements
- Responsive design (mobile, tablet, desktop)
- Loading states (skeletons)
- Error handling (toast notifications)
- Success messages (toast notifications)
- Confirmation dialogs (approve, reject, process)
- Accessible (ARIA labels, keyboard navigation)

---

## Story 7: Instructor Frontend UI (CSL-Certification) (PGC-007)

**User Story:** As an **instructor**, I want UI pages to view my earnings and request withdrawals, so that I can track my income and get paid easily.

**Duration:** 1 week | **Depends On:** Story 5

### Pages to Create

#### 1. Earnings Page (`app/instructor/earnings/page.tsx`)
**Features:**
- Commission history table
- Filters: Status (all, pending, approved, paid), Date Range
- Statistics cards: Total Earned, Total Paid, Available Balance
- Download statements button (PDF/CSV)
- Pagination

**Components:**
- `EarningsTable.tsx` - Commission history
- `EarningsStatsCards.tsx` - Stats cards
- `DownloadStatementsButton.tsx` - Export button

#### 2. Withdrawals Page (`app/instructor/withdrawals/page.tsx`)
**Features:**
- Withdrawal request form (amount, method selection)
- Withdrawal history table
- Status tracking (pending → approved → processing → completed)
- Request button (opens modal)

**Components:**
- `WithdrawalForm.tsx` - Request form
- `WithdrawalHistoryTable.tsx` - History table
- `WithdrawalRequestModal.tsx` - Request modal

#### 3. Payment Settings (`app/instructor/payment-settings/page.tsx`)
**Features:**
- Withdrawal method selector (bank transfer, PayPal, mobile money)
- Account details form (fields change based on method)
- Save button (encrypted storage)
- Validation messages

**Components:**
- `PaymentMethodSelector.tsx` - Method dropdown
- `BankTransferForm.tsx` - Bank account fields
- `PayPalForm.tsx` - PayPal email
- `MobileMoneyForm.tsx` - Phone number + provider

#### 4. Dashboard Widget Update (`app/instructor/dashboard/page.tsx`)
**Features:**
- Earnings summary widget (total earned, available balance)
- Quick withdrawal button (opens modal)
- Pending commission count badge

**Components:**
- `EarningsWidget.tsx` - Dashboard widget
- `QuickWithdrawalButton.tsx` - CTA button

### UI/UX Requirements
- Responsive design
- Loading states
- Error handling (toast notifications)
- Validation errors (inline)
- Success messages
- Accessible

---

## Story 8: Integration Testing & Production Deployment (PGC-008)

**User Story:** As a **product team**, we want comprehensive testing and safe production deployment, so that we launch with confidence and zero downtime.

**Duration:** 1 week | **Depends On:** Stories 1-7

### Testing Tasks

#### 1. Unit Tests
- Service tests (all 3 new services): >90% coverage
- Model relationship tests
- Commission calculation accuracy tests
- Withdrawal validation tests
- Controller unit tests (request/response handling)

#### 2. Integration Tests
- End-to-end checkout with centralized gateway
- Commission record creation on successful payment
- Withdrawal approval workflow (request → approve → process → complete)
- Refund handling with commission reversal
- Fallback to environment gateway when Environment 1 fails
- Payment config toggle (enable → transaction → disable → transaction)

#### 3. Manual Testing
- Test with Stripe test account (card: 4242 4242 4242 4242)
- Test with MonetBil test credentials
- Test withdrawal approval workflow end-to-end
- Test commission approval and payout calculation
- Test admin UI (all pages functional)
- Test instructor UI (all pages functional)
- Test mobile responsiveness

### Deployment Tasks

#### 1. Database Migration
- Run migrations on staging environment
- Seed default `EnvironmentPaymentConfig` for all environments
- Verify data integrity
- Test rollback on staging
- **Optional:** Backfill commission records for historical transactions (decision needed)

#### 2. Documentation
- API documentation update (Swagger/OpenAPI)
- Admin user guide (PDF):
  - How to approve commissions
  - How to process withdrawals
  - How to toggle centralized payments
- Instructor user guide (PDF):
  - How to view earnings
  - How to request withdrawals
  - How to configure payment method
- Developer guide (Markdown):
  - Model naming conventions
  - Integration points
  - Testing strategies
- Rollback procedures (Runbook)

#### 3. Production Deployment
- **Pre-deployment:**
  - Database backup
  - Smoke tests on staging
  - Stakeholder approval
- **Deployment:**
  - Deploy backend (API)
  - Run migrations
  - Seed configs
  - Deploy admin frontend (CSL-Sales-Website)
  - Deploy instructor frontend (CSL-Certification)
  - Verify all services healthy
- **Post-deployment:**
  - Monitor error logs (Sentry)
  - Monitor transaction success rates
  - Manually verify first 100 centralized transactions
  - Monitor commission record creation
  - Check performance metrics (<100ms overhead)

### Monitoring & Alerts
- Sentry alerts for payment failures
- Log alerts for centralized gateway failures
- Performance alerts (slow queries, high latency)
- Business metric alerts (withdrawal approval delay > 7 days)

### Success Metrics (Week 1)
- Zero P0/P1 bugs
- Transaction success rate >95%
- Commission accuracy 100% (sample of 100)
- At least 1 environment opts into centralized gateways
- Performance overhead <100ms

---

## Epic Completion Checklist

When all stories (1-8) are complete, verify:

- [x] All 8 stories marked as "Done"
- [x] All acceptance criteria met
- [x] Zero breaking changes to existing payment flow
- [x] Test coverage >90%
- [x] API documentation updated
- [x] User guides published
- [x] Deployed to production
- [x] First 100 transactions verified
- [x] Stakeholder sign-off obtained
- [x] Epic closed

---

**Document Created By:** John (Product Manager)
**Document Version:** 1.0
**Last Updated:** 2025-10-08
**Status:** Ready for Development
