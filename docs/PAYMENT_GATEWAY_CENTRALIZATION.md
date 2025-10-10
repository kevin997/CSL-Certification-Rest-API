# Payment Gateway Centralization - Brownfield Specification

## Executive Summary

**Project Goal**: Introduce an **optional** centralized payment processing system where environments can opt-in to have their transactions processed through Environment 1 (Super Admin) payment gateways. The current environment-specific gateway model remains the default and fully functional. Includes commission tracking and withdrawal management for instructors who opt into centralized processing.

**Status**: Brownfield Enhancement - Planning Phase
**Date**: 2025-10-08
**Impact Level**: Medium - Adds new optional payment flow alongside existing system (no breaking changes)

---

## Brownfield Analysis - ACTUAL Current State

### ✅ Existing Infrastructure (Already Implemented)

#### 1. Payment Gateway Model & Settings
- **Model**: `PaymentGatewaySetting` (`app/Models/PaymentGatewaySetting.php`)
- **Migration**: `2025_04_01_153830_create_payment_gateway_settings_table.php`
- **Features**:
  - Environment-specific gateway configurations (`environment_id` FK)
  - Gateway codes: stripe, monetbill, lygos, paypal
  - Settings stored as JSON with `getSetting()` / `setSetting()` methods
  - Active/inactive status toggle
  - Default gateway selection per environment
  - Transaction fee configuration (percentage + fixed)
  - Webhook URLs configured
- **Current Behavior**: Each environment configures its own payment gateways

#### 2. Transaction Processing System
- **Model**: `Transaction` (`app/Models/Transaction.php`)
- **Migration**: `2025_04_01_181419_create_transactions_table.php`
- **Features Already Implemented**:
  - ✅ `fee_amount` field (commission/platform fee)
  - ✅ `tax_amount` and `tax_rate` fields
  - ✅ `total_amount` calculation
  - ✅ `environment_id` foreign key
  - ✅ `payment_gateway_setting_id` relationship
  - ✅ Status tracking (pending, processing, completed, failed, refunded)
  - ✅ Gateway transaction ID tracking
  - ✅ Currency conversion support (XAF)
  - ✅ Customer information fields
  - **Missing**: No relationship to commission records for instructor payouts

#### 3. Commission System (Platform Fee Calculation)
- **Model**: `Commission` (`app/Models/Commission.php`)
  **⚠️ NOTE**: This is for PLATFORM FEE RATES, NOT transaction commission tracking
- **Migration**: `2025_06_09_151139_create_commissions_table.php`
- **Purpose**: Configures commission rates per environment (default 17%)
- **Fields**: `environment_id`, `name`, `rate`, `is_active`, `description`, `conditions`, `priority`, `valid_from`, `valid_until`
- **Usage**: Rate configuration only - does NOT track individual commission records

#### 4. CommissionService (Fee Calculation Logic)
- **Service**: `app/Services/Commission/CommissionService.php`
- **Features**:
  - ✅ `extractCommissionFromProductPrice()` - Reverse-calculates commission from price
  - ✅ `calculateTransactionAmountsWithCommissionIncluded()` - Calculates fee + tax
  - ✅ Integrated with `TaxZoneService` for tax calculation
  - ✅ Used in `PaymentService` for all transaction processing
  - **Missing**: No methods for tracking instructor earnings or withdrawal balances

#### 5. PaymentService (Core Payment Processing)
- **Service**: `app/Services/PaymentService.php`
- **Features**:
  - ✅ `createPayment()` - Creates transactions with commission/tax calculation
  - ✅ `processGatewayPayment()` - Handles Stripe, Lygos, MonetBill
  - ✅ Gateway factory pattern for extensibility
  - ✅ Callback processing (success, failure, cancelled)
  - ✅ Refund processing with proportional commission reversal
  - ✅ Environment-specific gateway initialization
  - **Missing**: No centralized gateway routing logic

#### 6. Order & OrderItem System
- **Models**: `Order`, `OrderItem`
- **Features**:
  - ✅ Environment scoping
  - ✅ Transaction relationships (`hasMany` and `hasOne`)
  - ✅ Billing information storage
  - ✅ Status management
  - ✅ Referral tracking

#### 7. Controllers
- **StorefrontController**: Handles checkout flow, order creation
- **TransactionController**: Processes payment callbacks
- **FinanceController**: Basic financial reporting
- **Missing**: No commission management or withdrawal controllers

### ❌ Missing Components (To Be Implemented)

#### 1. Environment Payment Configuration System
**New Model Required**: `EnvironmentPaymentConfig`
```php
// Model does NOT exist - needs to be created
Fields needed:
- environment_id (FK to environments)
- use_centralized_gateways (boolean) // Opt-in flag
- commission_rate (decimal) // 15% default for instructors
- payment_terms (string) // NET_30, NET_60, etc.
- withdrawal_method (enum) // bank_transfer, paypal, mobile_money
- withdrawal_details (json) // Account info
- minimum_withdrawal_amount (decimal)
- is_active (boolean)
```

#### 2. Commission Tracking System (For Instructor Earnings)
**New Model Required**: `InstructorCommission` or rename to `CommissionRecord`
```php
// This is DIFFERENT from existing Commission model
// Existing Commission = platform fee RATE configuration
// New model = individual TRANSACTION commission records for instructor payouts

Fields needed:
- id
- environment_id (FK - instructor's environment)
- transaction_id (FK to transactions)
- order_id (FK to orders)
- gross_amount (total order amount)
- commission_rate (rate at time of transaction)
- commission_amount (calculated commission for instructor)
- net_amount (amount owed to instructor = gross - commission)
- status (pending, approved, paid, disputed)
- paid_at (timestamp)
- payment_reference (string)
- withdrawal_request_id (FK - optional)
- notes (text)
```

**⚠️ CRITICAL**: Existing `Transaction.fee_amount` tracks PLATFORM commission deducted.
New model tracks INSTRUCTOR earnings to be paid out.

#### 3. Withdrawal Request System
**New Model Required**: `WithdrawalRequest`
```php
// Does NOT exist - needs to be created
Fields needed:
- id
- environment_id (FK to environments)
- requested_by (FK to users)
- amount (decimal)
- currency (string)
- status (pending, approved, processing, completed, rejected)
- withdrawal_method (enum)
- withdrawal_details (json)
- commission_ids (json) // Array of commission IDs included
- approved_by (FK to users)
- approved_at (timestamp)
- processed_by (FK to users)
- processed_at (timestamp)
- payment_reference (string)
- rejection_reason (text)
- notes (text)
```

#### 4. Services to Create
- **NEW**: `EnvironmentPaymentConfigService` - Manage opt-in settings
- **NEW**: `InstructorCommissionService` - Track earnings, calculate balances
- **NEW**: `WithdrawalService` - Handle withdrawal requests and processing
- **MODIFY**: `PaymentService` - Add centralized gateway routing logic

#### 5. Controllers to Create
- **NEW**: `Api/Admin/CommissionController` - Manage instructor commissions
- **NEW**: `Api/Admin/WithdrawalRequestController` - Approve/process withdrawals
- **NEW**: `Api/Admin/EnvironmentPaymentConfigController` - Configure centralized payments
- **NEW**: `Api/Admin/CentralizedTransactionController` - View all transactions
- **NEW**: `Api/Instructor/EarningsController` - View earnings
- **NEW**: `Api/Instructor/WithdrawalController` - Request withdrawals

#### 6. Frontend Pages Needed
**Admin (CSL-Sales-Website):**
- `/admin/transactions` - Cross-environment transaction view
- `/admin/commissions` - Commission management and approval
- `/admin/withdrawals` - Withdrawal request processing
- `/admin/payment-settings` - Environment payment configuration

**Instructor (CSL-Certification):**
- `/instructor/earnings` - View commission history and balance
- `/instructor/withdrawals` - Request and track withdrawals
- `/instructor/payment-settings` - Configure withdrawal method

---

## Implementation Plan (Corrected Based on Actual Codebase)

### Phase 1: Database & Models (Week 1)

#### Tasks:
1. **Create New Migrations**
   - [ ] `create_environment_payment_configs_table`
     - `environment_id`, `use_centralized_gateways`, `commission_rate`, `payment_terms`, `withdrawal_method`, `withdrawal_details`, `minimum_withdrawal_amount`, `is_active`
   - [ ] `create_instructor_commissions_table` (or `commission_records`)
     - Tracks individual transaction commissions for instructor payouts
     - **DO NOT CONFUSE** with existing `commissions` table (platform fee rates)
   - [ ] `create_withdrawal_requests_table`
     - Full withdrawal management fields

2. **Create New Models**
   - [ ] `EnvironmentPaymentConfig` model
     - Relationships: `belongsTo(Environment)`, `hasMany(WithdrawalRequest)`
   - [ ] `InstructorCommission` model
     - Relationships: `belongsTo(Environment)`, `belongsTo(Transaction)`, `belongsTo(Order)`, `belongsTo(WithdrawalRequest)`
   - [ ] `WithdrawalRequest` model
     - Relationships: `belongsTo(Environment)`, `belongsTo(User)`, `hasMany(InstructorCommission)`

3. **Update Existing Models**
   - [ ] `Environment` model:
     - Add `paymentConfig()` relationship
     - Add `instructorCommissions()` relationship
     - Add `withdrawalRequests()` relationship
   - [ ] `Transaction` model:
     - Add `instructorCommission()` relationship (if centralized payment)
   - [ ] `Order` model:
     - Add `instructorCommission()` relationship
   - **DO NOT MODIFY**: Existing `Commission` model (platform fee rates)

### Phase 2: Backend Services & Logic (Week 2)

#### Tasks:
1. **Create EnvironmentPaymentConfigService**
   ```php
   - getConfig(int $environmentId): EnvironmentPaymentConfig|null
   - updateConfig(int $environmentId, array $data): EnvironmentPaymentConfig
   - enableCentralizedPayments(int $environmentId): bool
   - disableCentralizedPayments(int $environmentId): bool
   - isCentralized(int $environmentId): bool
   ```

2. **Create InstructorCommissionService**
   ```php
   - createCommissionRecord(Transaction $transaction): InstructorCommission
   - calculateNetEarnings(int $environmentId): float
   - getTotalEarned(int $environmentId): float
   - getTotalPaid(int $environmentId): float
   - getAvailableBalance(int $environmentId): float
   - getCommissions(int $environmentId, array $filters): Collection
   - approveCommission(InstructorCommission $commission): bool
   ```

3. **Create WithdrawalService**
   ```php
   - createWithdrawalRequest(int $environmentId, float $amount, array $details): WithdrawalRequest
   - approveWithdrawal(WithdrawalRequest $request): bool
   - rejectWithdrawal(WithdrawalRequest $request, string $reason): bool
   - processWithdrawal(WithdrawalRequest $request, string $reference): bool
   - getAvailableBalance(int $environmentId): float
   - validateWithdrawalAmount(int $environmentId, float $amount): bool
   ```

4. **Modify PaymentService**
   - [ ] Add `shouldUseCentralizedGateway(int $environmentId): bool` method
   - [ ] Modify `initializeGateway()` to check centralized setting
   - [ ] If centralized, fetch Environment 1's gateway settings instead
   - [ ] After successful payment, call `InstructorCommissionService::createCommissionRecord()`
   - [ ] Add logging for centralized vs. environment-specific routing

5. **Modify TransactionController**
   - [ ] Update callback handlers to create commission records on successful payment
   - [ ] Add commission record creation in `callbackSuccess()` method

### Phase 3: Admin API Endpoints (Week 3)

#### New Controllers:

1. **CommissionController** (`/api/admin/commissions`)
   ```php
   GET    /api/admin/commissions              // List all instructor commissions
   GET    /api/admin/commissions/{id}         // Get commission details
   POST   /api/admin/commissions/{id}/approve // Approve a commission
   POST   /api/admin/commissions/bulk-approve // Approve multiple commissions
   GET    /api/admin/commissions/stats        // Commission statistics
   GET    /api/admin/commissions/environment/{environmentId} // By environment
   ```

2. **WithdrawalRequestController** (`/api/admin/withdrawal-requests`)
   ```php
   GET    /api/admin/withdrawal-requests              // List all requests
   GET    /api/admin/withdrawal-requests/{id}         // Get request details
   POST   /api/admin/withdrawal-requests/{id}/approve // Approve request
   POST   /api/admin/withdrawal-requests/{id}/reject  // Reject request
   POST   /api/admin/withdrawal-requests/{id}/process // Mark as processed/paid
   GET    /api/admin/withdrawal-requests/stats        // Withdrawal stats
   ```

3. **CentralizedTransactionController** (`/api/admin/centralized-transactions`)
   ```php
   GET    /api/admin/centralized-transactions                  // All transactions
   GET    /api/admin/centralized-transactions/{id}             // Transaction details
   GET    /api/admin/centralized-transactions/stats            // Transaction stats
   GET    /api/admin/centralized-transactions/environment/{id} // Filter by environment
   GET    /api/admin/centralized-transactions/export           // Export to CSV/Excel
   ```

4. **EnvironmentPaymentConfigController** (`/api/admin/environment-payment-configs`)
   ```php
   GET    /api/admin/environment-payment-configs                        // List all configs
   GET    /api/admin/environment-payment-configs/{environmentId}        // Get config
   PUT    /api/admin/environment-payment-configs/{environmentId}        // Update config
   POST   /api/admin/environment-payment-configs/{environmentId}/toggle // Toggle centralized
   ```

### Phase 4: Instructor API Endpoints (Week 4)

#### New Controllers:

1. **EarningsController** (`/api/instructor/earnings`)
   ```php
   GET /api/instructor/earnings                // List commissions
   GET /api/instructor/earnings/stats          // Earnings statistics
   GET /api/instructor/earnings/balance        // Available balance
   ```

2. **WithdrawalController** (`/api/instructor/withdrawals`)
   ```php
   GET  /api/instructor/withdrawals            // List withdrawal requests
   POST /api/instructor/withdrawals            // Create withdrawal request
   GET  /api/instructor/withdrawals/{id}       // Get withdrawal details
   ```

3. **PaymentConfigController** (`/api/instructor/payment-config`)
   ```php
   GET /api/instructor/payment-config          // Get payment configuration
   PUT /api/instructor/payment-config          // Update withdrawal method/details
   ```

### Phase 5: Admin Frontend UI (Week 5)

#### Pages in CSL-Sales-Website `/app/admin/`:

1. **Transactions Dashboard** (`/admin/transactions`)
   - View all transactions across all environments
   - Filter by environment, status, date range, payment method
   - Export functionality
   - Commission breakdown per transaction

2. **Commissions Management** (`/admin/commissions`)
   - List all instructor commissions
   - Filter by environment, status, date range
   - Bulk approve functionality
   - Commission details modal
   - Statistics: Total Owed, Total Paid, Pending Approval

3. **Withdrawal Requests** (`/admin/withdrawals`)
   - List all withdrawal requests
   - Filter by status, environment, date range
   - Approve/Reject actions with reason input
   - Process payment functionality
   - Withdrawal details modal

4. **Environment Payment Settings** (`/admin/payment-settings`)
   - List environments with centralized payment status
   - Toggle centralized payment processing per environment
   - Set commission rates per environment
   - Configure payment terms

### Phase 6: Instructor Frontend UI (Week 6)

#### Pages in CSL-Certification:

1. **Instructor Dashboard Updates**
   - Add earnings overview widget
   - Show pending commissions
   - Display available balance
   - Quick withdrawal request button

2. **Earnings Page** (`/instructor/earnings`)
   - View all commissions with filters
   - Commission breakdown per order
   - Total earnings statistics
   - Downloadable statements

3. **Withdrawals Page** (`/instructor/withdrawals`)
   - Request withdrawal form
   - Withdrawal history table
   - Configure withdrawal method
   - Track withdrawal status

### Phase 7: Testing & Migration (Week 7)

#### Tasks:
1. **Data Migration**
   - [ ] Backfill commission records for existing completed transactions (optional)
   - [ ] Create default `EnvironmentPaymentConfig` for all environments
   - [ ] Test migration scripts on staging

2. **Testing**
   - [ ] Unit tests for new services
   - [ ] Integration tests for centralized payment flow
   - [ ] End-to-end checkout tests
   - [ ] Commission calculation accuracy tests
   - [ ] Withdrawal request workflow tests
   - [ ] Gateway routing logic tests

3. **Documentation**
   - [ ] API documentation updates (Swagger/OpenAPI)
   - [ ] User guide for instructors (withdrawal process)
   - [ ] Admin guide for managing commissions/withdrawals
   - [ ] Migration guide and rollback plan

---

## Technical Considerations

### 1. Backward Compatibility
- **Strategy**: Maintain environment-specific gateways as default
- **Implementation**: Check `use_centralized_gateways` flag in `EnvironmentPaymentConfig`
- **Migration Path**: Gradual opt-in, no forced migration
- **Existing Transactions**: Continue working with existing payment flow

### 2. Commission Calculation (Two Purposes)
**Platform Fee (Existing):**
- Uses existing `Commission` model (rate configuration)
- Calculated by `CommissionService::extractCommissionFromProductPrice()`
- Stored in `Transaction.fee_amount`

**Instructor Earnings (New):**
- Uses new `InstructorCommission` model (transaction records)
- Created on successful transaction completion
- Tracks net amount owed to instructor

### 3. Security
- **Gateway Credentials**: Only Environment 1 stores centralized gateway credentials
- **Withdrawal Approval**: Require super admin approval before processing
- **Audit Trail**: Log all commission and withdrawal actions to `AuditLog`
- **Access Control**: Instructors can only view their own commissions/withdrawals

### 4. Performance
- **Indexing**: Add indexes on `environment_id`, `status`, `created_at` for all new tables
- **Caching**: Cache commission totals per environment (Redis)
- **Batch Processing**: Support bulk operations for commission approvals
- **Eager Loading**: Use `with()` relationships to avoid N+1 queries

### 5. Error Handling
- **Failed Payments**: Do NOT create commission record if transaction fails
- **Refunds**: Create negative commission record to reverse earnings
- **Disputes**: Mark commission as disputed, hold payment
- **Gateway Failures**: Fallback to environment gateway if centralized fails

---

## Database Schema

### environment_payment_configs (NEW TABLE)
```sql
CREATE TABLE environment_payment_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    environment_id BIGINT UNSIGNED NOT NULL UNIQUE,
    use_centralized_gateways BOOLEAN DEFAULT FALSE,
    commission_rate DECIMAL(5,4) DEFAULT 0.1500 COMMENT '15% default for instructors',
    payment_terms VARCHAR(50) DEFAULT 'NET_30',
    withdrawal_method ENUM('bank_transfer', 'paypal', 'mobile_money') NULL,
    withdrawal_details JSON NULL,
    minimum_withdrawal_amount DECIMAL(10,2) DEFAULT 50000.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE
);
```

### instructor_commissions (NEW TABLE)
```sql
CREATE TABLE instructor_commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    environment_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    gross_amount DECIMAL(10,2) NOT NULL COMMENT 'Total order amount',
    commission_rate DECIMAL(5,4) NOT NULL COMMENT 'Rate at time of transaction',
    commission_amount DECIMAL(10,2) NOT NULL COMMENT 'Platform commission deducted',
    net_amount DECIMAL(10,2) NOT NULL COMMENT 'Amount owed to instructor',
    currency VARCHAR(3) DEFAULT 'XAF',
    status ENUM('pending', 'approved', 'paid', 'disputed') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    payment_reference VARCHAR(255) NULL,
    withdrawal_request_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (withdrawal_request_id) REFERENCES withdrawal_requests(id) ON DELETE SET NULL,
    INDEX idx_environment_status (environment_id, status),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
);
```

### withdrawal_requests (NEW TABLE)
```sql
CREATE TABLE withdrawal_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    environment_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'XAF',
    status ENUM('pending', 'approved', 'processing', 'completed', 'rejected') DEFAULT 'pending',
    withdrawal_method ENUM('bank_transfer', 'paypal', 'mobile_money') NOT NULL,
    withdrawal_details JSON NOT NULL,
    commission_ids JSON NULL COMMENT 'Array of commission IDs included',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    processed_by BIGINT UNSIGNED NULL,
    processed_at TIMESTAMP NULL,
    payment_reference VARCHAR(255) NULL,
    rejection_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_environment_status (environment_id, status),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

---

## Risk Assessment

### High Risk Areas
1. **Payment Flow Changes**: Critical path - requires extensive testing with real gateways
2. **Commission Calculation**: Must differentiate between platform fee and instructor earnings
3. **Data Migration**: Backfilling historical commission records needs careful planning
4. **Model Naming Confusion**: Existing `Commission` vs. new `InstructorCommission`

### Mitigation Strategies
1. **Feature Flags**: Implement gradual rollout per environment
2. **Parallel Testing**: Run old and new systems side-by-side during transition
3. **Audit Logging**: Comprehensive logging of all financial operations
4. **Rollback Plan**: Ability to disable centralized gateways per environment
5. **Clear Naming**: Use `InstructorCommission` or `CommissionRecord` to avoid confusion with existing `Commission` model

---

## Success Metrics

1. **Adoption Rate**: % of environments using centralized gateways (target: 80% in 6 months)
2. **Transaction Success Rate**: Maintain >95% success rate for centralized payments
3. **Commission Accuracy**: 100% accurate commission calculations (automated tests)
4. **Withdrawal Processing Time**: Average <7 days from request to payment
5. **Support Tickets**: Reduction in payment-related support issues (target: -50%)

---

## Next Steps

1. **✅ Review & Approval**: Get stakeholder sign-off on this corrected specification
2. **Sprint Planning**: Break down into 2-week sprints
3. **Development Environment**: Set up testing environment with test gateway credentials
4. **Begin Phase 1**: Start with database migrations and models

---

## Questions & Decisions Needed

1. **Commission Rate for Instructors**: What should be the default? (Suggested: 15%)
2. **Minimum Withdrawal**: What's the minimum withdrawal amount? (Suggested: 50,000 XAF)
3. **Payment Terms**: NET 30, NET 60, or immediate? (Suggested: NET 30)
4. **Historical Data**: Should we backfill commission records for past transactions?
5. **Refund Handling**: Create negative commission records or reverse existing ones?
6. **Model Naming**: Use `InstructorCommission` or `CommissionRecord` to differentiate from existing `Commission` model?
7. **Centralized Gateway Fallback**: If Environment 1 gateway fails, fall back to environment-specific gateway?

---

**Document Version**: 2.0 (Corrected Brownfield Analysis)
**Last Updated**: 2025-10-08
**Analyzed By**: Mary (Business Analyst)
**Status**: Ready for Review - Awaiting Stakeholder Approval