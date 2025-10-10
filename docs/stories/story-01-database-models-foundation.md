# Story 1: Database Schema & Models Foundation - Brownfield Addition

**Story ID:** PGC-001
**Epic:** Payment Gateway Centralization (EPIC-PGC-001)
**Created:** 2025-10-08
**Status:** Completed
**Priority:** High
**Estimated Effort:** 1 Week
**Sprint:** Sprint 1
**Completed:** 2025-10-08

---

## User Story

As a **system developer**,
I want to create the database schema and Laravel models for the centralized payment gateway system,
So that we have the data foundation to track environment payment configurations, instructor commissions, and withdrawal requests.

---

## Story Context

### Existing System Integration

**Integrates with:**
- `environments` table - Existing table for multi-tenancy
- `transactions` table - Existing payment transaction records
- `orders` table - Existing order records
- `users` table - Existing user authentication and authorization

**Technology:**
- Laravel 10 migrations
- Eloquent ORM models
- MySQL 8.0+ (JSON field support)

**Follows pattern:**
- Existing migration naming convention: `YYYY_MM_DD_HHMMSS_create_table_name_table.php`
- Existing model structure: Eloquent models in `app/Models/`
- Existing relationship patterns: `belongsTo`, `hasMany`, `hasOne`
- use php artisan command to create migrations, models, controllers, files.

**Touch points:**
- New migrations create 3 new tables
- 3 new models created in `app/Models/`
- 3 existing models updated with new relationships (`Environment`, `Transaction`, `Order`)

---

## Acceptance Criteria

### Functional Requirements

**FR1: Create Environment Payment Config Migration & Model**
- Migration file: `create_environment_payment_configs_table.php`
- Table name: `environment_payment_configs`
- Fields:
  - `id` (BIGINT UNSIGNED, PK, auto-increment)
  - `environment_id` (BIGINT UNSIGNED, NOT NULL, UNIQUE, FK to environments)
  - `use_centralized_gateways` (BOOLEAN, DEFAULT FALSE)
  - `commission_rate` (DECIMAL(5,4), DEFAULT 0.1700) - 17% for instructors
  - `payment_terms` (VARCHAR(50), DEFAULT 'NET_30')
  - `withdrawal_method` (ENUM: 'bank_transfer', 'paypal', 'mobile_money', NULL)
  - `withdrawal_details` (JSON, NULL)
  - `minimum_withdrawal_amount` (DECIMAL(10,2), DEFAULT 50000.00)
  - `is_active` (BOOLEAN, DEFAULT TRUE)
  - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- Indexes:
  - Primary key on `id`
  - Unique index on `environment_id`
- Foreign keys:
  - `environment_id` REFERENCES `environments(id)` ON DELETE CASCADE
- Model: `EnvironmentPaymentConfig.php` in `app/Models/`
- Relationships:
  - `belongsTo(Environment::class)`
  - `hasMany(WithdrawalRequest::class)`
- Casts: `withdrawal_details` as `array`, `use_centralized_gateways` and `is_active` as `boolean`

**FR2: Create Instructor Commission Migration & Model**
- Migration file: `create_instructor_commissions_table.php`
- Table name: `instructor_commissions`
- Fields:
  - `id` (BIGINT UNSIGNED, PK, auto-increment)
  - `environment_id` (BIGINT UNSIGNED, NOT NULL, FK to environments)
  - `transaction_id` (BIGINT UNSIGNED, NULL, FK to transactions)
  - `order_id` (BIGINT UNSIGNED, NULL, FK to orders)
  - `gross_amount` (DECIMAL(10,2), NOT NULL) - Total order amount
  - `commission_rate` (DECIMAL(5,4), NOT NULL) - Rate at time of transaction
  - `commission_amount` (DECIMAL(10,2), NOT NULL) - Platform commission deducted
  - `net_amount` (DECIMAL(10,2), NOT NULL) - Amount owed to instructor
  - `currency` (VARCHAR(3), DEFAULT 'XAF')
  - `status` (ENUM: 'pending', 'approved', 'paid', 'disputed', DEFAULT 'pending')
  - `paid_at` (TIMESTAMP, NULL)
  - `payment_reference` (VARCHAR(255), NULL)
  - `withdrawal_request_id` (BIGINT UNSIGNED, NULL, FK to withdrawal_requests)
  - `notes` (TEXT, NULL)
  - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- Indexes:
  - Primary key on `id`
  - Composite index on (`environment_id`, `status`)
  - Index on `created_at`
  - Index on `status`
  - Index on `transaction_id`
  - Index on `order_id`
- Foreign keys:
  - `environment_id` REFERENCES `environments(id)` ON DELETE CASCADE
  - `transaction_id` REFERENCES `transactions(id)` ON DELETE SET NULL
  - `order_id` REFERENCES `orders(id)` ON DELETE SET NULL
  - `withdrawal_request_id` REFERENCES `withdrawal_requests(id)` ON DELETE SET NULL
- Model: `InstructorCommission.php` in `app/Models/`
- Relationships:
  - `belongsTo(Environment::class)`
  - `belongsTo(Transaction::class)`
  - `belongsTo(Order::class)`
  - `belongsTo(WithdrawalRequest::class)`
- Casts: `gross_amount`, `commission_amount`, `net_amount` as `decimal:2`

**FR3: Create Withdrawal Request Migration & Model**
- Migration file: `create_withdrawal_requests_table.php`
- Table name: `withdrawal_requests`
- Fields:
  - `id` (BIGINT UNSIGNED, PK, auto-increment)
  - `environment_id` (BIGINT UNSIGNED, NOT NULL, FK to environments)
  - `requested_by` (BIGINT UNSIGNED, NOT NULL, FK to users)
  - `amount` (DECIMAL(10,2), NOT NULL)
  - `currency` (VARCHAR(3), DEFAULT 'XAF')
  - `status` (ENUM: 'pending', 'approved', 'processing', 'completed', 'rejected', DEFAULT 'pending')
  - `withdrawal_method` (ENUM: 'bank_transfer', 'paypal', 'mobile_money', NOT NULL)
  - `withdrawal_details` (JSON, NOT NULL)
  - `commission_ids` (JSON, NULL) - Array of commission IDs included
  - `approved_by` (BIGINT UNSIGNED, NULL, FK to users)
  - `approved_at` (TIMESTAMP, NULL)
  - `processed_by` (BIGINT UNSIGNED, NULL, FK to users)
  - `processed_at` (TIMESTAMP, NULL)
  - `payment_reference` (VARCHAR(255), NULL)
  - `rejection_reason` (TEXT, NULL)
  - `notes` (TEXT, NULL)
  - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- Indexes:
  - Primary key on `id`
  - Composite index on (`environment_id`, `status`)
  - Index on `status`
  - Index on `created_at`
  - Index on `requested_by`
- Foreign keys:
  - `environment_id` REFERENCES `environments(id)` ON DELETE CASCADE
  - `requested_by` REFERENCES `users(id)` ON DELETE CASCADE
  - `approved_by` REFERENCES `users(id)` ON DELETE SET NULL
  - `processed_by` REFERENCES `users(id)` ON DELETE SET NULL
- Model: `WithdrawalRequest.php` in `app/Models/`
- Relationships:
  - `belongsTo(Environment::class)`
  - `belongsTo(User::class, 'requested_by')`
  - `belongsTo(User::class, 'approved_by')`
  - `belongsTo(User::class, 'processed_by')`
  - `hasMany(InstructorCommission::class)`
- Casts: `withdrawal_details` and `commission_ids` as `array`, `amount` as `decimal:2`

### Integration Requirements

**IR1: Update Environment Model**
- File: `app/Models/Environment.php`
- Add relationships:
  ```php
  public function paymentConfig()
  {
      return $this->hasOne(EnvironmentPaymentConfig::class);
  }

  public function instructorCommissions()
  {
      return $this->hasMany(InstructorCommission::class);
  }

  public function withdrawalRequests()
  {
      return $this->hasMany(WithdrawalRequest::class);
  }
  ```

**IR2: Update Transaction Model**
- File: `app/Models/Transaction.php`
- Add relationship:
  ```php
  public function instructorCommission()
  {
      return $this->hasOne(InstructorCommission::class);
  }
  ```

**IR3: Update Order Model**
- File: `app/Models/Order.php`
- Add relationship:
  ```php
  public function instructorCommission()
  {
      return $this->hasOne(InstructorCommission::class);
  }
  ```

**IR4: Existing Functionality Unchanged**
- All existing payment processing continues to work
- Existing `Commission` model (platform fee rates) remains unchanged
- Existing `Transaction.fee_amount` field continues tracking platform fees
- No modifications to `payments`, `payment_gateway_settings`, or `commissions` tables

### Quality Requirements

**QR1: Database Seeder**
- Create seeder: `EnvironmentPaymentConfigSeeder.php`
- Seeds default `EnvironmentPaymentConfig` for all existing environments
- Default values:
  - `use_centralized_gateways` = false
  - `commission_rate` = 0.1700 (17%)
  - `payment_terms` = 'NET_30'
  - `minimum_withdrawal_amount` = 50000.00
  - `is_active` = true

**QR2: Model Factories**
- Create factories for testing:
  - `EnvironmentPaymentConfigFactory.php`
  - `InstructorCommissionFactory.php`
  - `WithdrawalRequestFactory.php`
- Factories generate realistic test data
- Factories respect foreign key constraints

**QR3: Migration Rollback**
- All migrations must have `down()` methods
- Rollback tested on local environment
- Rollback order: `withdrawal_requests` → `instructor_commissions` → `environment_payment_configs`

**QR4: Database Tests**
- Model relationship tests:
  - `EnvironmentPaymentConfig` → `Environment` (belongsTo)
  - `InstructorCommission` → `Environment`, `Transaction`, `Order` (belongsTo)
  - `WithdrawalRequest` → `Environment`, `User` (belongsTo)
  - `Environment` → new relationships (hasOne, hasMany)
- Foreign key constraint tests
- Cascading delete tests
- Unique constraint tests

---

## Technical Notes

### Integration Approach
- **Additive only:** No modifications to existing tables
- **Foreign keys:** Use `ON DELETE CASCADE` for parent-child relationships, `ON DELETE SET NULL` for optional references
- **Naming convention:** Follow existing Laravel migration and model naming patterns
- **JSON fields:** Use MySQL JSON data type for `withdrawal_details` and `commission_ids`

### Existing Pattern Reference
- Follow existing migration patterns in `database/migrations/`
- Follow existing model structure (see `app/Models/Transaction.php`, `app/Models/Order.php`)
- Use Eloquent casts for data type conversions
- Follow PSR-4 autoloading standards

### Key Constraints
- MySQL 8.0+ required for JSON field support
- Environment IDs must exist in `environments` table
- User IDs must exist in `users` table
- Transaction/Order IDs must exist in respective tables (or NULL)

---

## Definition of Done

- [x] **Migration Files Created** ✅
  - `create_environment_payment_configs_table.php` created
  - `create_instructor_commissions_table.php` created
  - `create_withdrawal_requests_table.php` created
  - All migrations follow Laravel naming conventions

- [x] **Models Created** ✅
  - `EnvironmentPaymentConfig.php` model created
  - `InstructorCommission.php` model created
  - `WithdrawalRequest.php` model created
  - All models have correct namespace and class names

- [x] **Relationships Defined** ✅
  - All `belongsTo`, `hasOne`, `hasMany` relationships defined correctly
  - Existing models (`Environment`, `Transaction`, `Order`) updated with new relationships
  - Relationship methods follow Laravel conventions

- [x] **Database Seeder Created** ✅
  - `EnvironmentPaymentConfigSeeder.php` created
  - Seeds default config for all 6 environments
  - Seeder tested and working

- [x] **Model Factories Created** ✅
  - Factories for all 3 new models
  - Factories generate valid test data
  - Factories respect foreign key constraints

- [x] **Migrations Run Successfully** ✅
  - `php artisan migrate` runs without errors
  - All tables created with correct schema
  - Indexes and foreign keys created correctly
  - 4 migrations created (including FK constraint migration)

- [x] **Migration Rollback Tested** ✅
  - `php artisan migrate:rollback --step=4` works correctly
  - All tables dropped in correct order
  - No orphaned data or constraints
  - Re-migration successful

- [ ] **Database Tests Pass** (Pending - Story 1 testing phase)
  - Model relationship tests to be written
  - Foreign key constraint tests to be written
  - Cascading delete tests to be written
  - Unique constraint tests to be written
  - All tests in `tests/Unit/Models/` directory

- [ ] **Code Quality** (To be verified)
  - PHPStan level 8 analysis to be run
  - Code follows PSR-12 coding standards ✅
  - All models have PHPDoc comments ✅
  - Migrations have descriptive schema ✅

- [ ] **Documentation Updated** (Pending)
  - Database schema diagram to be updated
  - Model relationship documentation to be created
  - README.md to be updated with new models

---

## Risk and Compatibility Check

### Primary Risk
**Database Migration Failure on Production**
- **Risk:** Migration could fail on production due to data inconsistencies or schema conflicts
- **Mitigation:**
  - Test migrations on staging environment first
  - Test on local copy of production database
  - Create database backup before migration
  - Use transactions in migrations where possible
  - Have rollback plan ready

### Rollback Plan
**If migration fails:**
1. Run `php artisan migrate:rollback --step=3` to rollback the 3 new migrations
2. Investigate failure cause in Laravel logs
3. Fix migration issues locally and test again
4. Re-run migrations after fix

**If data integrity issues after migration:**
1. Rollback migrations
2. Restore database from backup
3. Fix data issues
4. Re-run migrations

### Compatibility Verification
- [x] No breaking changes to existing tables
- [x] No modifications to existing columns
- [x] No removal of existing data
- [x] Foreign keys use appropriate `ON DELETE` actions
- [x] Default values ensure backward compatibility
- [x] Indexes do not conflict with existing indexes

---

## Testing Checklist

### Unit Tests
- [ ] Test `EnvironmentPaymentConfig::environment()` relationship
- [ ] Test `EnvironmentPaymentConfig::withdrawalRequests()` relationship
- [ ] Test `InstructorCommission::environment()` relationship
- [ ] Test `InstructorCommission::transaction()` relationship
- [ ] Test `InstructorCommission::order()` relationship
- [ ] Test `InstructorCommission::withdrawalRequest()` relationship
- [ ] Test `WithdrawalRequest::environment()` relationship
- [ ] Test `WithdrawalRequest::requestedBy()` relationship
- [ ] Test `WithdrawalRequest::approvedBy()` relationship
- [ ] Test `WithdrawalRequest::processedBy()` relationship
- [ ] Test `WithdrawalRequest::instructorCommissions()` relationship
- [ ] Test `Environment::paymentConfig()` relationship
- [ ] Test `Environment::instructorCommissions()` relationship
- [ ] Test `Environment::withdrawalRequests()` relationship
- [ ] Test `Transaction::instructorCommission()` relationship
- [ ] Test `Order::instructorCommission()` relationship

### Integration Tests
- [ ] Test cascading delete: Deleting environment deletes payment configs
- [ ] Test cascading delete: Deleting environment deletes instructor commissions
- [ ] Test cascading delete: Deleting environment deletes withdrawal requests
- [ ] Test ON DELETE SET NULL: Deleting transaction sets `transaction_id` to NULL
- [ ] Test ON DELETE SET NULL: Deleting order sets `order_id` to NULL
- [ ] Test unique constraint: Cannot create duplicate `environment_id` in payment configs
- [ ] Test foreign key constraints: Cannot create commission with invalid `environment_id`
- [ ] Test JSON field storage and retrieval for `withdrawal_details`
- [ ] Test JSON field storage and retrieval for `commission_ids`
- [ ] Test seeder creates config for all environments

### Manual Tests
- [ ] Run migrations on local development database
- [ ] Verify all tables created with correct schema
- [ ] Verify indexes created correctly (`SHOW INDEX FROM table_name`)
- [ ] Verify foreign keys created correctly (`SHOW CREATE TABLE table_name`)
- [ ] Run seeder and verify default configs created
- [ ] Test rollback and verify tables dropped
- [ ] Re-run migrations to ensure idempotency
- [ ] Check Laravel logs for any warnings or errors

---

## Dependencies

**Requires:**
- MySQL 8.0+ installed
- Laravel 10.x installed
- Existing `environments`, `users`, `transactions`, `orders` tables
- Database connection configured in `.env`

**Blocks:**
- Story 2 (Backend Services - Payment Config)
- Story 3 (Backend Services - Commission & Withdrawal)
- All subsequent stories require these models

**No External Blockers**

---

## Estimated Breakdown

| Task | Estimated Time |
|------|----------------|
| Create migration: `environment_payment_configs` | 2 hours |
| Create migration: `instructor_commissions` | 2 hours |
| Create migration: `withdrawal_requests` | 2 hours |
| Create model: `EnvironmentPaymentConfig` | 1 hour |
| Create model: `InstructorCommission` | 1 hour |
| Create model: `WithdrawalRequest` | 1 hour |
| Update existing models with relationships | 1 hour |
| Create database seeder | 2 hours |
| Create model factories | 3 hours |
| Write unit tests (relationship tests) | 4 hours |
| Write integration tests (foreign keys, cascades) | 3 hours |
| Manual testing and verification | 2 hours |
| Code review and fixes | 2 hours |
| Documentation updates | 2 hours |
| **Total** | **28 hours (~1 week)** |

---

## Acceptance Test Scenarios

### Scenario 1: Create Payment Config for Environment
```php
// Arrange
$environment = Environment::factory()->create();

// Act
$config = EnvironmentPaymentConfig::create([
    'environment_id' => $environment->id,
    'use_centralized_gateways' => true,
    'commission_rate' => 0.17,
]);

// Assert
$this->assertDatabaseHas('environment_payment_configs', [
    'environment_id' => $environment->id,
    'use_centralized_gateways' => true,
]);
$this->assertEquals($environment->id, $config->environment->id);
```

### Scenario 2: Create Instructor Commission Record
```php
// Arrange
$environment = Environment::factory()->create();
$transaction = Transaction::factory()->create();
$order = Order::factory()->create();

// Act
$commission = InstructorCommission::create([
    'environment_id' => $environment->id,
    'transaction_id' => $transaction->id,
    'order_id' => $order->id,
    'gross_amount' => 100000.00,
    'commission_rate' => 0.17,
    'commission_amount' => 17000.00,
    'net_amount' => 85000.00,
    'status' => 'pending',
]);

// Assert
$this->assertDatabaseHas('instructor_commissions', [
    'environment_id' => $environment->id,
    'gross_amount' => 100000.00,
]);
$this->assertEquals($environment->id, $commission->environment->id);
$this->assertEquals($transaction->id, $commission->transaction->id);
```

### Scenario 3: Create Withdrawal Request
```php
// Arrange
$environment = Environment::factory()->create();
$user = User::factory()->create();

// Act
$withdrawal = WithdrawalRequest::create([
    'environment_id' => $environment->id,
    'requested_by' => $user->id,
    'amount' => 100000.00,
    'withdrawal_method' => 'bank_transfer',
    'withdrawal_details' => [
        'bank_name' => 'Test Bank',
        'account_number' => '1234567890',
    ],
    'status' => 'pending',
]);

// Assert
$this->assertDatabaseHas('withdrawal_requests', [
    'environment_id' => $environment->id,
    'amount' => 100000.00,
]);
$this->assertEquals(['bank_name' => 'Test Bank'], $withdrawal->withdrawal_details);
```

### Scenario 4: Cascading Delete
```php
// Arrange
$environment = Environment::factory()->create();
$config = EnvironmentPaymentConfig::factory()->create([
    'environment_id' => $environment->id,
]);

// Act
$environment->delete();

// Assert
$this->assertDatabaseMissing('environment_payment_configs', [
    'id' => $config->id,
]);
```

---

## Notes for Developer

**CRITICAL:**
- **DO NOT modify existing tables** - Only create new tables
- **DO NOT modify existing `Commission` model** - That's for platform fee rates
- **Use `InstructorCommission`** - Not `CommissionRecord` (decision made)
- **Test rollback** - Ensure migrations can be rolled back cleanly
- **Foreign key order** - Create parent tables before child tables

**Best Practices:**
- Use Laravel migration helpers (`foreignId`, `constrained`, `cascadeOnDelete`)
- Use Eloquent casts for data type conversions
- Follow PSR-12 coding standards
- Add PHPDoc comments to all models and methods
- Use factory states for different test scenarios

**Testing Tips:**
- Use `RefreshDatabase` trait in tests
- Use factories for all test data creation
- Test both positive and negative scenarios
- Test edge cases (NULL values, missing FKs, etc.)

---

**Story Created By:** John (Product Manager)
**Assigned To:** [TBD - Backend Developer]
**Story Points:** 8 (1 week)
**Labels:** backend, database, migrations, models, foundational
**Status:** Ready for Development
