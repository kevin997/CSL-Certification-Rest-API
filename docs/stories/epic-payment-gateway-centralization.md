# Epic: Payment Gateway Centralization - Brownfield Enhancement

**Epic ID:** EPIC-PGC-001
**Created:** 2025-10-08
**Status:** Planning
**Priority:** High
**Estimated Duration:** 7-8 Weeks

---

## Epic Goal

Enable environments to **opt-in** to centralized payment processing through Environment 1's payment gateways while maintaining the current environment-specific gateway model as the default. Include instructor commission tracking and withdrawal management for environments using centralized processing.

---

## Epic Description

### Existing System Context

**Current Payment Architecture:**
- **Technology Stack:** Laravel 10 API, Next.js frontend, MySQL database
- **Payment Gateways:** Stripe, MonetBill, Lygos, PayPal (environment-specific)
- **Key Models:** `PaymentGatewaySetting`, `Transaction`, `Commission` (fee rates), `Order`, `OrderItem`
- **Core Services:** `PaymentService`, `CommissionService`, `TaxZoneService`
- **Controllers:** `StorefrontController`, `TransactionController`, `FinanceController`

**Current Flow:**
1. Each environment configures its own payment gateways
2. Transactions process through environment-specific `PaymentGatewaySetting`
3. Platform commission (17% default) calculated via `CommissionService`
4. Commission stored in `Transaction.fee_amount` field
5. No instructor payout tracking or withdrawal system

### Enhancement Details

**What's Being Added:**

1. **Opt-in Centralization System**
   - New `EnvironmentPaymentConfig` model with `use_centralized_gateways` flag
   - Gateway routing logic in `PaymentService` to use Environment 1's gateways when opted-in
   - Admin UI to toggle centralization per environment

2. **Instructor Commission Tracking**
   - New `InstructorCommission` model for transaction-level payout records
   - Differentiated from existing `Commission` model (fee rates vs. payout records)
   - Commission record creation on successful transactions
   - Balance calculation for instructors

3. **Withdrawal Management**
   - New `WithdrawalRequest` model and workflow
   - Admin approval/rejection/processing capabilities
   - Instructor request and tracking interfaces
   - Payment method configuration (bank transfer, PayPal, mobile money)

**How It Integrates:**
- **Non-breaking:** Existing payment flow remains default
- **Additive:** New models, services, and controllers alongside existing code
- **Conditional:** Centralized routing only activates when environment opts in
- **Compatible:** Existing `Transaction.fee_amount` tracking continues unchanged

**Success Criteria:**
- ✅ Environments can opt-in/out of centralized gateways without code changes
- ✅ Existing environment-specific payment flows continue working unchanged
- ✅ Instructor commission records created for all completed transactions
- ✅ Admin can approve and process withdrawal requests
- ✅ Instructors can view earnings and request withdrawals
- ✅ Zero breaking changes to existing payment functionality
- ✅ 100% test coverage for new features

---

## Stories

This epic is decomposed into **8 sequential stories**:

### 1. **Story 1: Database Schema & Models Foundation**
Create the database foundation with 3 new tables and models: `EnvironmentPaymentConfig`, `InstructorCommission`, `WithdrawalRequest`. Update existing models with new relationships.

**Estimated Effort:** 1 week
**Dependencies:** None
**Status:** Not Started

---

### 2. **Story 2: Environment Payment Config Service**
Build `EnvironmentPaymentConfigService` to manage opt-in settings and modify `PaymentService` to route payments based on centralized gateway flag.

**Estimated Effort:** 1 week
**Dependencies:** Story 1 (models must exist)
**Status:** Not Started

---

### 3. **Story 3: Commission & Withdrawal Services**
Implement `InstructorCommissionService` and `WithdrawalService` for tracking instructor earnings and managing withdrawal requests.

**Estimated Effort:** 1 week
**Dependencies:** Story 1 (models), Story 2 (payment routing)
**Status:** Not Started

---

### 4. **Story 4: Admin API Endpoints**
Create 4 admin controllers: `CommissionController`, `WithdrawalRequestController`, `CentralizedTransactionController`, `EnvironmentPaymentConfigController` with full CRUD and approval workflows.

**Estimated Effort:** 1 week
**Dependencies:** Story 2, Story 3 (services)
**Status:** Not Started

---

### 5. **Story 5: Instructor API Endpoints**
Build 3 instructor controllers: `EarningsController`, `WithdrawalController`, `PaymentConfigController` for instructor-facing earnings and withdrawal features.

**Estimated Effort:** 1 week
**Dependencies:** Story 3 (services)
**Status:** Not Started

---

### 6. **Story 6: Admin Frontend UI (CSL-Sales-Website)**
Develop 4 admin pages: Transactions dashboard, Commissions management, Withdrawal requests, and Payment settings with bulk operations and filters.

**Estimated Effort:** 1 week
**Dependencies:** Story 4 (admin APIs)
**Status:** Not Started

---

### 7. **Story 7: Instructor Frontend UI (CSL-Certification)**
Create 3 instructor pages: Earnings view, Withdrawal requests, and Payment settings configuration with dashboard widget updates.

**Estimated Effort:** 1 week
**Dependencies:** Story 5 (instructor APIs)
**Status:** Not Started

---

### 8. **Story 8: Integration Testing & Production Deployment**
Comprehensive testing (unit, integration, E2E), data migration, documentation, and production rollout with monitoring.

**Estimated Effort:** 1 week
**Dependencies:** Stories 1-7 (all features complete)
**Status:** Not Started

---

## Compatibility Requirements

### Database Compatibility
- ✅ All new tables are additive (no modifications to existing schema)
- ✅ Foreign keys use `ON DELETE CASCADE` or `SET NULL` appropriately
- ✅ Indexes added for performance on new query patterns
- ✅ Default values ensure backward compatibility

### API Compatibility
- ✅ Existing API endpoints remain unchanged
- ✅ New endpoints use separate namespaces (`/api/admin/*`, `/api/instructor/*`)
- ✅ Existing payment flow continues working without modification
- ✅ Response formats match existing API conventions

### Service Compatibility
- ✅ Existing `PaymentService` methods maintain current behavior
- ✅ New methods added, not modified (except `initializeGateway` with conditional logic)
- ✅ `CommissionService` (fee rates) remains unchanged
- ✅ New services (`InstructorCommissionService`, `WithdrawalService`) are standalone

### UI Compatibility
- ✅ Existing admin/instructor pages unchanged
- ✅ New pages follow existing design system and patterns
- ✅ No changes to existing user workflows
- ✅ Mobile responsive design maintained

### Performance
- ✅ Database indexes prevent N+1 queries
- ✅ Eager loading used for relationships
- ✅ Caching strategy for commission balances (Redis)
- ✅ Minimal performance impact on existing payment flow

---

## Risk Mitigation

### Primary Risks

**Risk 1: Payment Flow Disruption**
- **Impact:** High - Could affect revenue if centralized routing fails
- **Mitigation:**
  - Feature flag per environment (opt-in only)
  - Fallback to environment gateway on centralized failure
  - Extensive testing with test gateway accounts before production
  - Gradual rollout (1 environment at a time)
- **Rollback:** Toggle `use_centralized_gateways = false` via admin UI

**Risk 2: Model Naming Confusion**
- **Impact:** Medium - Developers may confuse `Commission` (rates) with `InstructorCommission` (payouts)
- **Mitigation:**
  - Clear documentation in code comments
  - Different table names (`commissions` vs `instructor_commissions`)
  - Code review checklist item
  - Developer training session
- **Rollback:** N/A (naming issue, not runtime)

**Risk 3: Commission Calculation Errors**
- **Impact:** High - Incorrect instructor payouts could cause financial/legal issues
- **Mitigation:**
  - Comprehensive unit tests for calculation logic
  - Admin approval workflow before withdrawal processing
  - Audit logging of all commission/withdrawal operations
  - Manual verification for first 100 transactions
- **Rollback:** Hold all withdrawals, fix calculations, reprocess

**Risk 4: Data Migration Complexity**
- **Impact:** Medium - Backfilling historical commission records could be resource-intensive
- **Mitigation:**
  - Make backfill optional (not required for launch)
  - Batch processing with rate limiting
  - Test on staging data first
  - Run during off-peak hours
- **Rollback:** Delete backfilled records if issues found

### Rollback Plan

**Level 1: Environment-Level Rollback (No Code Deploy)**
- Admin sets `use_centralized_gateways = false` for affected environment
- Payment flow reverts to environment-specific gateways
- Time to rollback: < 5 minutes

**Level 2: Feature Disable (Code Deploy)**
- Deploy feature flag to disable all centralized payment features
- Existing payment flow unaffected
- Time to rollback: ~30 minutes (deploy time)

**Level 3: Full Rollback (Database Rollback)**
- Reverse migrations for new tables
- Remove new code
- Restore previous version
- Time to rollback: ~2 hours (emergency procedure)

---

## Definition of Done

### Epic-Level DOD

- ✅ All 8 stories completed with acceptance criteria met
- ✅ Zero breaking changes to existing payment functionality verified
- ✅ Existing test suite passes with no regressions
- ✅ New features have >90% test coverage
- ✅ API documentation updated (Swagger/OpenAPI)
- ✅ Admin and instructor user guides published
- ✅ Database migrations tested on staging
- ✅ Performance benchmarks meet targets (< 100ms overhead)
- ✅ Security audit completed
- ✅ Deployed to production with monitoring enabled
- ✅ First 100 transactions manually verified
- ✅ Stakeholder sign-off obtained

### Technical Acceptance Criteria

**Backend:**
- ✅ 3 new models with full relationships
- ✅ 3 new services with comprehensive methods
- ✅ 7 new controllers with all CRUD operations
- ✅ Modified `PaymentService::initializeGateway()` with conditional routing
- ✅ Modified `TransactionController::callbackSuccess()` creates commission records
- ✅ All services have unit tests (>90% coverage)
- ✅ Integration tests for payment flow (centralized vs. environment)
- ✅ Audit logging for all financial operations

**Frontend:**
- ✅ 4 admin pages fully functional (transactions, commissions, withdrawals, settings)
- ✅ 3 instructor pages fully functional (earnings, withdrawals, payment config)
- ✅ Dashboard widgets updated for instructors
- ✅ Responsive design (mobile, tablet, desktop)
- ✅ Loading states, error handling, success messages
- ✅ Export functionality (CSV) for transactions and commissions
- ✅ Bulk operations (approve multiple commissions)

**Database:**
- ✅ 3 new tables with proper indexes
- ✅ Foreign keys with appropriate constraints
- ✅ Default `EnvironmentPaymentConfig` seeded for all environments
- ✅ Migration rollback tested
- ✅ Database query performance acceptable (<100ms for list queries)

**Documentation:**
- ✅ API documentation updated with new endpoints
- ✅ Admin user guide (how to manage commissions/withdrawals)
- ✅ Instructor user guide (how to request withdrawals)
- ✅ Developer guide (model naming, integration points)
- ✅ Rollback procedures documented

**Deployment:**
- ✅ Staging deployment successful
- ✅ Production deployment plan reviewed
- ✅ Monitoring dashboards configured (Sentry, logs)
- ✅ Alerting rules set up (failed transactions, withdrawal errors)

---

## Dependencies & Constraints

### External Dependencies
- **Stripe API:** Existing integration, no changes needed
- **MonetBill API:** Existing integration, no changes needed
- **Lygos API:** Existing integration, no changes needed
- **PayPal API:** Existing integration, no changes needed

### Internal Dependencies
- **Environment 1 Gateway Configuration:** Must have active payment gateway settings
- **Admin User Roles:** Require super admin role for withdrawal approval
- **Instructor User Roles:** Require instructor role for earnings/withdrawal access

### Technical Constraints
- **Laravel Version:** 10.x (compatibility verified)
- **PHP Version:** 8.1+ (no changes needed)
- **MySQL Version:** 8.0+ (JSON field support required)
- **Redis:** Required for caching commission balances
- **Next.js Version:** 13+ for admin/instructor frontends

### Business Constraints
- **Commission Rate:** Default 15% for instructors (configurable per environment)
- **Minimum Withdrawal:** 50,000 XAF (configurable per environment)
- **Payment Terms:** NET 30 (configurable: NET_30, NET_60, Immediate)
- **Approval Workflow:** All withdrawals require super admin approval

---

## Timeline & Milestones

| Week | Milestone | Stories | Deliverable |
|------|-----------|---------|-------------|
| Week 1 | Database Foundation | Story 1 | Migrations, models, relationships tested |
| Week 2 | Payment Routing | Story 2 | Centralized gateway routing functional |
| Week 3 | Commission & Withdrawal Logic | Story 3 | Services with business logic complete |
| Week 4 | Admin APIs | Story 4 | Admin endpoints with Postman collection |
| Week 5 | Instructor APIs | Story 5 | Instructor endpoints documented |
| Week 6 | Admin UI | Story 6 | Admin dashboard pages live |
| Week 7 | Instructor UI | Story 7 | Instructor pages live |
| Week 8 | Testing & Launch | Story 8 | Production deployment complete |

---

## Open Questions & Decisions

### Decisions Needed Before Starting Story 1

1. **Commission Rate for Instructors**
   - **Question:** What should be the default commission rate for instructor earnings?
   - **Suggested:** 15% (instructor keeps 85% of gross amount)
   - **Impact:** Commission calculation logic in `InstructorCommissionService`
   - **Decision Deadline:** Before Story 1 starts

2. **Minimum Withdrawal Amount**
   - **Question:** What's the minimum amount an instructor can withdraw?
   - **Suggested:** 50,000 XAF (~$80 USD)
   - **Impact:** Validation logic in `WithdrawalService`
   - **Decision Deadline:** Before Story 3 starts

3. **Payment Terms**
   - **Question:** How long after approval should withdrawals be processed?
   - **Options:** NET 30 (30 days), NET 60 (60 days), Immediate
   - **Suggested:** NET 30 for cash flow management
   - **Impact:** Admin workflow and instructor expectations
   - **Decision Deadline:** Before Story 4 starts

4. **Historical Data Backfill**
   - **Question:** Should we create commission records for past transactions?
   - **Options:** Yes (complete history), No (start fresh), Partial (last 6 months)
   - **Suggested:** Optional, not required for launch
   - **Impact:** Data migration script complexity, instructor balance accuracy
   - **Decision Deadline:** Before Story 8 (deployment)

5. **Model Naming**
   - **Question:** Use `InstructorCommission` or `CommissionRecord` for payout tracking?
   - **Suggested:** `InstructorCommission` (clearer differentiation from `Commission`)
   - **Impact:** Model names, table names, variable names
   - **Decision Deadline:** Before Story 1 starts

6. **Gateway Fallback Behavior**
   - **Question:** If Environment 1 gateway fails, should we fall back to environment-specific gateway?
   - **Suggested:** Yes, with logging and alert
   - **Impact:** Error handling logic in `PaymentService`
   - **Decision Deadline:** Before Story 2 starts

---

## Success Metrics

### Launch Metrics (Week 1 Post-Launch)
- **Adoption Rate:** At least 1 environment opts into centralized gateways
- **Transaction Success Rate:** Maintain >95% success rate for centralized payments
- **Commission Accuracy:** 100% match between manual calculation and system calculation (sample of 100 transactions)
- **Zero Critical Bugs:** No P0/P1 bugs related to payment flow
- **Performance:** <100ms overhead for centralized routing vs. environment-specific

### 3-Month Post-Launch Metrics
- **Adoption Rate:** 50% of environments using centralized gateways
- **Withdrawal Processing Time:** Average <7 days from request to payment
- **Support Tickets:** <5 tickets per week related to commissions/withdrawals
- **Instructor Satisfaction:** >80% satisfaction rating (survey)
- **Payment Success Rate:** Maintain >98% success rate

### 6-Month Post-Launch Metrics
- **Adoption Rate:** 80% of environments using centralized gateways
- **Total Instructor Earnings Processed:** Track total payouts via withdrawal system
- **System Stability:** <1 hour downtime related to this feature
- **Cost Savings:** Measure gateway fee reduction from centralized processing

---

## Handoff to Story Manager

**Story Manager,**

Please develop detailed user stories for this brownfield epic. Key considerations:

**Existing System:**
- Technology: Laravel 10 API, Next.js frontends, MySQL database
- Payment gateways: Stripe, MonetBill, Lygos, PayPal (environment-specific)
- Key services: `PaymentService`, `CommissionService`, `TaxZoneService`
- Critical models: `PaymentGatewaySetting`, `Transaction`, `Commission`, `Order`

**Integration Points:**
- `PaymentService::initializeGateway()` - Add conditional routing logic
- `TransactionController::callbackSuccess()` - Add commission record creation
- `Environment` model - Add 3 new relationships
- `Transaction` model - Add `instructorCommission()` relationship

**Existing Patterns to Follow:**
- Service layer pattern (all business logic in services)
- API resource pattern (use Laravel API resources for responses)
- Form request validation (use Laravel form requests)
- Repository pattern for complex queries
- Audit logging via `AuditLog` model

**Critical Compatibility Requirements:**
- ZERO breaking changes to existing payment flow
- All new features must be opt-in (feature flags)
- Existing APIs remain unchanged (new endpoints only)
- Database changes are additive only (no ALTER on existing tables)
- Each story must include regression testing of existing functionality

**Naming Convention:**
- Use `InstructorCommission` (not `CommissionRecord`) to differentiate from `Commission` model
- Table names: `environment_payment_configs`, `instructor_commissions`, `withdrawal_requests`
- Services: `EnvironmentPaymentConfigService`, `InstructorCommissionService`, `WithdrawalService`

The epic should maintain complete system integrity while delivering opt-in centralized payment processing with instructor commission tracking and withdrawal management.

---

**Epic Created By:** John (Product Manager)
**Document Version:** 1.0
**Last Updated:** 2025-10-08
**Status:** Ready for Story Breakdown
