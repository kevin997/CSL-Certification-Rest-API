# Session Context: Payment Gateway Centralization Epic

**Session Date:** 2025-10-08
**Product Manager:** John
**Epic:** EPIC-PGC-001

---

## Active Project Directories

### 1. CSL-Certification-Rest-API (Laravel 10 Backend)
**Path:** `/home/atlas/Projects/CSL/CSL-Certification-Rest-API`

**Purpose:** REST API backend for CSL Certification platform

**Key Directories:**
```
app/
├── Models/                          # Eloquent models
│   ├── Commission.php              # ✅ Existing - Platform fee rates
│   ├── Environment.php             # ✅ Existing - Multi-tenancy
│   ├── Order.php                   # ✅ Existing - Order records
│   ├── PaymentGatewaySetting.php   # ✅ Existing - Gateway configs
│   ├── Transaction.php             # ✅ Existing - Payment transactions
│   ├── User.php                    # ✅ Existing - User auth
│   ├── EnvironmentPaymentConfig.php  # ❌ NEW (Story 1)
│   ├── InstructorCommission.php      # ❌ NEW (Story 1)
│   └── WithdrawalRequest.php         # ❌ NEW (Story 1)
│
├── Services/                        # Business logic services
│   ├── BrandingService.php         # ✅ Existing
│   ├── OrderService.php            # ✅ Existing
│   ├── PaymentService.php          # ✅ Existing - TO MODIFY (Story 2)
│   ├── Commission/
│   │   └── CommissionService.php   # ✅ Existing - Platform fee calculations
│   ├── Tax/
│   │   └── TaxZoneService.php      # ✅ Existing
│   ├── EnvironmentPaymentConfigService.php  # ❌ NEW (Story 2)
│   ├── InstructorCommissionService.php      # ❌ NEW (Story 3)
│   └── WithdrawalService.php                # ❌ NEW (Story 3)
│
├── Http/Controllers/Api/           # API controllers
│   ├── StorefrontController.php    # ✅ Existing - Checkout flow
│   ├── TransactionController.php   # ✅ Existing - TO MODIFY (Story 3)
│   ├── Admin/                      # Admin controllers
│   │   ├── CommissionController.php              # ❌ NEW (Story 4)
│   │   ├── WithdrawalRequestController.php       # ❌ NEW (Story 4)
│   │   ├── CentralizedTransactionController.php  # ❌ NEW (Story 4)
│   │   └── EnvironmentPaymentConfigController.php # ❌ NEW (Story 4)
│   └── Instructor/                 # Instructor controllers
│       ├── EarningsController.php           # ❌ NEW (Story 5)
│       ├── WithdrawalController.php         # ❌ NEW (Story 5)
│       └── PaymentConfigController.php      # ❌ NEW (Story 5)
│
database/
├── migrations/                     # Database migrations
│   ├── 2025_04_01_153830_create_payment_gateway_settings_table.php  # ✅ Existing
│   ├── 2025_04_01_181419_create_transactions_table.php              # ✅ Existing
│   ├── 2025_06_09_151139_create_commissions_table.php               # ✅ Existing
│   ├── create_environment_payment_configs_table.php    # ❌ NEW (Story 1)
│   ├── create_instructor_commissions_table.php         # ❌ NEW (Story 1)
│   └── create_withdrawal_requests_table.php            # ❌ NEW (Story 1)
│
routes/
└── api.php                         # API route definitions (TO MODIFY Stories 4, 5)

docs/
└── stories/                        # Epic and user stories
    ├── epic-payment-gateway-centralization.md
    ├── story-01-database-models-foundation.md
    ├── story-02-environment-payment-config-service.md
    └── STORIES-03-08-SUMMARY.md
```

---

### 2. CSL-Sales-Website (Next.js Admin Frontend)
**Path:** `/home/atlas/Projects/CSL/CSL-Sales-Website/app/admin`

**Purpose:** Super Admin dashboard for managing CSL platform

**Existing Pages:**
```
app/admin/
├── customers/              # ✅ Existing - Customer management
├── plans/                  # ✅ Existing - Subscription plans
├── referrals/              # ✅ Existing - Referral management
├── sales-agents/           # ✅ Existing - Sales agents
├── subscriptions/          # ✅ Existing - Subscription management
├── third-party-services/   # ✅ Existing - Third-party integrations
├── layout.tsx              # ✅ Existing - Admin layout
└── page.tsx                # ✅ Existing - Admin dashboard
```

**NEW Pages (Story 6):**
```
app/admin/
├── transactions/           # ❌ NEW - Cross-environment transaction view
│   └── page.tsx           # Filterable transaction table, export CSV
├── commissions/            # ❌ NEW - Commission management
│   └── page.tsx           # List, bulk approve, statistics
├── withdrawals/            # ❌ NEW - Withdrawal request processing
│   └── page.tsx           # Approve/reject/process withdrawals
└── payment-settings/       # ❌ NEW - Environment payment configuration
    └── page.tsx           # Toggle centralized, commission rates
```

**Tech Stack:**
- Next.js 13+ (App Router)
- TypeScript
- TailwindCSS
- React Server Components

---

### 3. CSL-Certification (Next.js Instructor/Learner Frontend)
**Path:** `/home/atlas/Projects/CSL/CSL-Certification`

**Purpose:** Instructor and learner interface for courses, certifications, and earnings

**Existing Pages:**
```
app/
├── analytics/              # ✅ Existing - Analytics dashboard
├── auth/                   # ✅ Existing - Authentication
├── billing/                # ✅ Existing - Billing management
├── checkout/               # ✅ Existing - Checkout flow
├── courses/                # ✅ Existing - Course management
├── dashboard/              # ✅ Existing - Main dashboard (TO MODIFY - Story 7)
├── finances/               # ✅ Existing - Financial overview
├── learners/               # ✅ Existing - Learner management
├── orders/                 # ✅ Existing - Order management
├── products/               # ✅ Existing - Product catalog
├── profile/                # ✅ Existing - User profile
└── settings/               # ✅ Existing - Account settings
```

**NEW Pages (Story 7) - Instructor Features:**
```
app/
├── instructor/             # ❌ NEW - Instructor-specific pages
│   ├── earnings/          # ❌ NEW - View commission history
│   │   └── page.tsx       # Commission table, statistics, download statements
│   ├── withdrawals/       # ❌ NEW - Request withdrawals
│   │   └── page.tsx       # Request form, withdrawal history
│   └── payment-settings/  # ❌ NEW - Configure withdrawal method
│       └── page.tsx       # Bank/PayPal/Mobile Money setup
│
└── dashboard/
    └── page.tsx           # TO MODIFY - Add earnings widget
```

**Tech Stack:**
- Next.js 13+ (App Router)
- TypeScript
- TailwindCSS
- React Server Components
- SWR for data fetching

---

## Existing Infrastructure (Verified)

### ✅ Already Implemented

**1. Payment Gateway System**
- `PaymentGatewaySetting` model with environment-specific configs
- Supports: Stripe, MonetBill, Lygos, PayPal
- Active/inactive status toggle
- Default gateway selection

**2. Transaction Processing**
- `Transaction` model with `fee_amount`, `tax_amount`, `total_amount`
- Status tracking: pending, processing, completed, failed, refunded
- Environment scoping via `environment_id` FK

**3. Commission Rate Configuration**
- `Commission` model (platform fee RATES only)
- Default 17% platform commission
- Environment-specific rate configuration
- **NOTE:** This is NOT for tracking instructor payouts

**4. Services**
- `CommissionService` - Calculates platform fees
- `PaymentService` - Core payment processing with gateway factory
- `OrderService` - Order management
- `TaxZoneService` - Tax calculation

**5. Controllers**
- `StorefrontController` - Checkout flow
- `TransactionController` - Payment callbacks
- Gateway-specific controllers in `PaymentGateways/`

---

## Missing Components (To Be Built)

### ❌ Database Schema (Story 1)
- `environment_payment_configs` table
- `instructor_commissions` table
- `withdrawal_requests` table

### ❌ Models (Story 1)
- `EnvironmentPaymentConfig` - Opt-in settings
- `InstructorCommission` - Transaction-level commission records
- `WithdrawalRequest` - Withdrawal management

### ❌ Services (Stories 2, 3)
- `EnvironmentPaymentConfigService` - Manage opt-in
- `InstructorCommissionService` - Track instructor earnings
- `WithdrawalService` - Handle withdrawal requests

### ❌ API Endpoints (Stories 4, 5)
- 4 admin controllers (commission, withdrawal, transaction, config)
- 3 instructor controllers (earnings, withdrawal, payment config)

### ❌ Frontend Pages (Stories 6, 7)
- 4 admin pages (transactions, commissions, withdrawals, settings)
- 3 instructor pages (earnings, withdrawals, payment settings)

---

## Key Integration Points

### 1. Payment Routing Logic (Story 2)
**File:** `app/Services/PaymentService.php`
**Method:** `initializeGateway(int $environmentId, string $gateway = null)`
**Change:** Add conditional routing to Environment 1's gateway when `use_centralized_gateways` = true

### 2. Commission Record Creation (Story 3)
**File:** `app/Http/Controllers/Api/TransactionController.php`
**Method:** `callbackSuccess()`
**Change:** Create `InstructorCommission` record on successful transaction for centralized environments

### 3. Model Relationships (Story 1)
**Files:**
- `app/Models/Environment.php` - Add `paymentConfig()`, `instructorCommissions()`, `withdrawalRequests()`
- `app/Models/Transaction.php` - Add `instructorCommission()`
- `app/Models/Order.php` - Add `instructorCommission()`

---

## Critical Naming Conventions

**⚠️ IMPORTANT:**
- **`Commission`** = Platform fee RATE configuration (existing, DO NOT MODIFY)
- **`InstructorCommission`** = Transaction-level commission RECORD for instructor payouts (NEW)
- **DO NOT** confuse these two models!

**Table Names:**
- `commissions` - Existing (platform fee rates)
- `instructor_commissions` - New (instructor payout records)
- `environment_payment_configs` - New (opt-in settings)
- `withdrawal_requests` - New (withdrawal management)

---

## Development Guidelines

### Laravel Artisan Commands
```bash
# Create migration
php artisan make:migration create_table_name_table

# Create model with migration
php artisan make:model ModelName -m

# Create controller
php artisan make:controller Api/Admin/ControllerName

# Create service (manually create file)
# No artisan command, create in app/Services/

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback --step=3

# Create seeder
php artisan make:seeder SeederName

# Run seeder
php artisan db:seed --class=SeederName
```

### Next.js Development
```bash
# Run development server (admin)
cd /home/atlas/Projects/CSL/CSL-Sales-Website
npm run dev

# Run development server (certification)
cd /home/atlas/Projects/CSL/CSL-Certification
npm run dev

# Build for production
npm run build

# Type checking
npx tsc --noEmit
```

---

## Session Summary

**Epic Created:** EPIC-PGC-001 - Payment Gateway Centralization
**Stories Created:** 8 stories (1-8)
**Duration:** 8 weeks (1 story per week)
**Status:** Ready for Development

**Next Steps:**
1. Review and approve epic document
2. Answer key decisions (commission rate, minimum withdrawal, etc.)
3. Start Story 1 (Database & Models Foundation)
4. Sequential execution through Story 8

**Documents Available:**
- ✅ Epic document: `docs/stories/epic-payment-gateway-centralization.md`
- ✅ Story 1 (detailed): `docs/stories/story-01-database-models-foundation.md`
- ✅ Story 2 (detailed): `docs/stories/story-02-environment-payment-config-service.md`
- ✅ Stories 3-8 (summary): `docs/stories/STORIES-03-08-SUMMARY.md`
- ✅ Session context: `docs/stories/SESSION-CONTEXT.md` (this file)

---

**Session Active:** ✅
**All Directories Loaded:** ✅
**Ready for Development:** ✅
