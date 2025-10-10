# 8. Core Workflows

## 8.1 Workflow Overview

This section documents the critical user journeys and system workflows in the CSL platform. Each workflow is illustrated with sequence diagrams showing component interactions, error handling, and async operations.

**Key Workflows:**
1. User Authentication Flow
2. Course Checkout & Payment Flow (Existing)
3. Centralized Payment Gateway Routing (NEW - Payment Gateway Epic)
4. Commission Record Creation (NEW - Payment Gateway Epic)
5. Instructor Withdrawal Request Flow (NEW - Payment Gateway Epic)
6. Admin Withdrawal Approval Flow (NEW - Payment Gateway Epic)
7. Course Enrollment & Certificate Generation

---

## 8.2 User Authentication Flow

```mermaid
sequenceDiagram
    actor User
    participant Frontend as Next.js Frontend
    participant API as Laravel API
    participant DB as MySQL Database
    participant Redis as Redis Cache

    User->>Frontend: Enter email/password + environment_id
    Frontend->>API: POST /api/tokens
    API->>DB: SELECT * FROM users WHERE email = ?
    DB-->>API: User record
    API->>API: Verify password (bcrypt)

    alt Password Valid
        API->>DB: CREATE sanctum token with abilities
        DB-->>API: Token created
        API->>Redis: Cache user session (TTL: 1 hour)
        API-->>Frontend: 200 OK {token, user, environment_id}
        Frontend->>Frontend: Store token in localStorage
        Frontend->>Frontend: Redux: setAuth(user, token)
        Frontend-->>User: Redirect to /dashboard
    else Password Invalid
        API-->>Frontend: 401 Unauthorized {message: "Invalid credentials"}
        Frontend-->>User: Show error toast
    end

    Note over User,Redis: Subsequent Requests

    User->>Frontend: Navigate to /courses
    Frontend->>API: GET /api/courses<br/>Authorization: Bearer {token}
    API->>API: Verify token signature
    API->>Redis: Check cached user session

    alt Token Valid
        Redis-->>API: User session found
        API->>DB: SELECT * FROM courses WHERE environment_id = ?
        DB-->>API: Courses for environment
        API-->>Frontend: 200 OK {courses}
        Frontend-->>User: Display courses
    else Token Expired/Invalid
        API-->>Frontend: 401 Unauthorized
        Frontend->>Frontend: Clear localStorage
        Frontend-->>User: Redirect to /login
    end
```

**Key Points:**
- JWT tokens include `environment_id:{id}` ability for multi-tenant scoping
- Tokens cached in Redis for fast validation (TTL: 1 hour)
- Automatic logout on token expiry (frontend detects 401 and clears session)

---

## 8.3 Course Checkout & Payment Flow (Existing)

```mermaid
sequenceDiagram
    actor User
    participant Frontend as Next.js Frontend
    participant API as Laravel API
    participant PaymentService as PaymentService
    participant GatewayFactory as PaymentGatewayFactory
    participant Gateway as Payment Gateway<br/>(Stripe/PayPal/MonetBil)
    participant DB as MySQL Database

    User->>Frontend: Click "Checkout" on product
    Frontend->>API: POST /api/storefront/{envId}/checkout<br/>{cart_items, payment_method, billing}

    API->>API: Create Order record (status: pending)
    API->>DB: INSERT INTO orders
    DB-->>API: Order created

    API->>PaymentService: processPayment(order, paymentDetails)
    PaymentService->>PaymentService: initializeGateway(environmentId, gateway)
    PaymentService->>DB: SELECT * FROM payment_gateway_settings<br/>WHERE environment_id = ? AND is_active = true
    DB-->>PaymentService: Gateway settings

    PaymentService->>GatewayFactory: create(gatewayCode, settings)
    GatewayFactory-->>PaymentService: StripeGateway instance

    PaymentService->>Gateway: initiatePayment(order)
    Gateway->>Gateway: Create payment intent/order
    Gateway-->>PaymentService: {payment_url, transaction_ref}

    PaymentService->>DB: INSERT INTO transactions<br/>(status: pending, gateway_code, ref)
    DB-->>PaymentService: Transaction created

    PaymentService-->>API: {order_id, transaction_id, payment_url}
    API-->>Frontend: 201 Created {order, payment_url}

    Frontend-->>User: Redirect to payment_url

    Note over User,Gateway: User completes payment on gateway

    Gateway->>API: GET /api/payments/transactions/callback/success/{envId}<br/>?transaction_ref=xyz

    API->>PaymentService: handleCallback('stripe', 'success', data)
    PaymentService->>Gateway: verifyPayment(transaction_ref)
    Gateway-->>PaymentService: Payment verified

    PaymentService->>DB: UPDATE transactions SET status = 'completed'
    PaymentService->>DB: UPDATE orders SET status = 'completed'

    PaymentService->>PaymentService: Dispatch OrderPaymentProcessed event

    PaymentService-->>API: Success
    API-->>Gateway: 200 OK

    Note over API,DB: Async: Enroll user in course

    API-->>Frontend: Redirect to success page
    Frontend-->>User: Show order confirmation
```

**Key Points:**
- Order created with 'pending' status before payment
- Transaction records payment attempt with gateway reference
- Payment processed on external gateway (redirect flow)
- Webhook callback verifies payment and updates status
- Laravel events trigger async operations (enrollment, email)

---

## 8.4 Centralized Payment Gateway Routing (NEW)

```mermaid
sequenceDiagram
    actor User
    participant Frontend as Next.js Frontend
    participant API as Laravel API
    participant PaymentService as PaymentService
    participant EnvConfigService as EnvironmentPaymentConfigService
    participant Redis as Redis Cache
    participant DB as MySQL Database
    participant Gateway as Payment Gateway

    User->>Frontend: Checkout (Environment 3)
    Frontend->>API: POST /api/storefront/3/checkout

    API->>PaymentService: processPayment(order, paymentDetails)
    PaymentService->>PaymentService: initializeGateway(environmentId: 3)

    PaymentService->>EnvConfigService: isCentralized(environmentId: 3)
    EnvConfigService->>Redis: GET env_payment_config:3

    alt Config Cached
        Redis-->>EnvConfigService: {use_centralized_gateways: true}
    else Cache Miss
        EnvConfigService->>DB: SELECT * FROM environment_payment_configs<br/>WHERE environment_id = 3
        DB-->>EnvConfigService: Config record
        EnvConfigService->>Redis: SET env_payment_config:3 {config} EX 3600
    end

    EnvConfigService-->>PaymentService: true (centralized)

    alt Centralized Gateway Enabled
        PaymentService->>DB: SELECT * FROM payment_gateway_settings<br/>WHERE environment_id = 1 AND is_active = true
        DB-->>PaymentService: Environment 1 gateway settings
        PaymentService->>PaymentService: Log: "Using centralized gateway for env 3"
    else Centralized Disabled
        PaymentService->>DB: SELECT * FROM payment_gateway_settings<br/>WHERE environment_id = 3 AND is_active = true
        DB-->>PaymentService: Environment 3 gateway settings
    end

    PaymentService->>Gateway: initiatePayment(order)
    Gateway-->>PaymentService: {payment_url}

    PaymentService-->>API: {payment_url}
    API-->>Frontend: 201 Created {payment_url}
    Frontend-->>User: Redirect to gateway
```

**Key Points:**
- **Opt-in logic**: Check `EnvironmentPaymentConfigService.isCentralized()`
- **Caching**: Config cached in Redis (TTL: 1 hour) to avoid DB queries
- **Routing decision**: If centralized, use Environment 1's gateway; else use environment's own gateway
- **Logging**: All centralized routing logged for auditing
- **Backward compatible**: Existing environments continue using own gateways unless opted-in

---

## 8.5 Commission Record Creation (NEW)

```mermaid
sequenceDiagram
    participant Gateway as Payment Gateway
    participant API as Laravel API
    participant TransactionController as TransactionController
    participant EnvConfigService as EnvironmentPaymentConfigService
    participant InstructorCommissionService as InstructorCommissionService
    participant DB as MySQL Database
    participant Queue as RabbitMQ Queue

    Gateway->>API: POST /api/payments/transactions/callback/success/3
    API->>TransactionController: callbackSuccess(environment_id: 3)

    TransactionController->>TransactionController: Verify webhook signature
    TransactionController->>DB: UPDATE transactions SET status = 'completed'
    TransactionController->>DB: UPDATE orders SET status = 'completed'

    TransactionController->>EnvConfigService: getConfig(environmentId: 3)
    EnvConfigService->>DB: SELECT * FROM environment_payment_configs<br/>WHERE environment_id = 3
    DB-->>EnvConfigService: {use_centralized_gateways: true, instructor_commission_rate: 0.15}
    EnvConfigService-->>TransactionController: Config record

    alt Centralized Gateway Enabled
        TransactionController->>InstructorCommissionService: createCommissionRecord(transaction)

        InstructorCommissionService->>DB: SELECT * FROM transactions WHERE id = ?
        DB-->>InstructorCommissionService: Transaction {total_amount: 10000, fee_amount: 1700}

        InstructorCommissionService->>InstructorCommissionService: Calculate commission:<br/>gross = 10000 - 1700 = 8300<br/>commission = 8300 * 0.15 = 1245<br/>net = 8300 - 1245 = 7055

        InstructorCommissionService->>DB: INSERT INTO instructor_commissions<br/>(environment_id, transaction_id, gross_amount: 8300,<br/>commission_rate: 0.15, commission_amount: 1245,<br/>net_amount: 7055, status: 'pending')
        DB-->>InstructorCommissionService: Commission record created

        InstructorCommissionService->>Queue: Dispatch CommissionCreated event
        Queue-->>InstructorCommissionService: Event queued

        InstructorCommissionService-->>TransactionController: Success
    else Centralized Gateway Disabled
        TransactionController->>TransactionController: Skip commission creation
    end

    TransactionController-->>API: 200 OK
    API-->>Gateway: Success
```

**Key Points:**
- Commission record ONLY created for environments using centralized gateways
- Calculation: `gross_amount = total - platform_fee`, `net_amount = gross - instructor_commission`
- Initial status: 'pending' (requires admin approval before withdrawal)
- Event dispatched for async notifications (email to instructor about earnings)

---

## 8.6 Instructor Withdrawal Request Flow (NEW)

```mermaid
sequenceDiagram
    actor Instructor
    participant Frontend as Next.js Frontend<br/>(Instructor)
    participant API as Laravel API
    participant WithdrawalController as Instructor/WithdrawalController
    participant WithdrawalService as WithdrawalService
    participant InstructorCommissionService as InstructorCommissionService
    participant DB as MySQL Database
    participant Queue as RabbitMQ Queue
    participant Telegram as Telegram Bot

    Instructor->>Frontend: Navigate to /instructor/withdrawals
    Frontend->>API: GET /api/instructor/earnings/balance
    API->>InstructorCommissionService: getAvailableBalance(environmentId)

    InstructorCommissionService->>DB: SELECT SUM(net_amount) FROM instructor_commissions<br/>WHERE environment_id = ? AND status = 'approved'<br/>AND withdrawal_request_id IS NULL
    DB-->>InstructorCommissionService: available_balance: 150000

    InstructorCommissionService-->>API: {available_balance: 150000, currency: 'XAF'}
    API-->>Frontend: 200 OK {balance: 150000}
    Frontend-->>Instructor: Display balance: 150,000 XAF

    Instructor->>Frontend: Enter withdrawal amount: 100000<br/>Select method: bank_transfer<br/>Enter bank details
    Frontend->>API: POST /api/instructor/withdrawals<br/>{amount: 100000, withdrawal_method: 'bank_transfer',<br/>withdrawal_details: {account_number, bank_name, etc.}}

    API->>WithdrawalController: store(request)
    WithdrawalController->>WithdrawalService: createWithdrawalRequest(environmentId, amount, details)

    WithdrawalService->>WithdrawalService: validateWithdrawalAmount(environmentId, 100000)
    WithdrawalService->>InstructorCommissionService: getAvailableBalance(environmentId)
    InstructorCommissionService-->>WithdrawalService: 150000

    alt Amount <= Available Balance
        WithdrawalService->>DB: SELECT minimum_withdrawal_amount FROM environment_payment_configs<br/>WHERE environment_id = ?
        DB-->>WithdrawalService: minimum: 50000

        alt Amount >= Minimum
            WithdrawalService->>DB: INSERT INTO withdrawal_requests<br/>(environment_id, requested_amount: 100000,<br/>withdrawal_method, withdrawal_details,<br/>status: 'pending', requested_at: NOW())
            DB-->>WithdrawalService: Withdrawal request created (id: 42)

            WithdrawalService->>Queue: Dispatch WithdrawalRequestCreated event
            Queue->>Telegram: Send notification to admin:<br/>"💰 New withdrawal request: 100,000 XAF<br/>Environment: 3, Request ID: 42"

            WithdrawalService-->>WithdrawalController: Success
            WithdrawalController-->>API: 201 Created {withdrawal_request}
            API-->>Frontend: 201 Created
            Frontend-->>Instructor: Success toast: "Withdrawal requested"
        else Amount < Minimum
            WithdrawalService-->>WithdrawalController: ValidationException<br/>"Minimum withdrawal: 50,000 XAF"
            WithdrawalController-->>API: 422 Unprocessable Entity
            API-->>Frontend: 422 {errors: {amount: ["Minimum 50,000"]}}
            Frontend-->>Instructor: Error toast
        end
    else Amount > Available Balance
        WithdrawalService-->>WithdrawalController: ValidationException<br/>"Insufficient balance"
        WithdrawalController-->>API: 422 Unprocessable Entity
        API-->>Frontend: 422 {errors: {amount: ["Insufficient balance"]}}
        Frontend-->>Instructor: Error toast
    end
```

**Key Points:**
- Available balance = SUM of approved commissions NOT yet withdrawn
- Validation: Amount >= minimum_withdrawal_amount AND <= available_balance
- Status: 'pending' (awaits admin approval)
- Telegram notification sent to admin immediately (via queued job)
- Withdrawal details encrypted before storage (bank account numbers)

---

## 8.7 Admin Withdrawal Approval Flow (NEW)

```mermaid
sequenceDiagram
    actor Admin
    participant AdminFrontend as Next.js Admin Frontend
    participant API as Laravel API
    participant WithdrawalController as Admin/WithdrawalRequestController
    participant WithdrawalService as WithdrawalService
    participant InstructorCommissionService as InstructorCommissionService
    participant DB as MySQL Database
    participant Queue as RabbitMQ Queue
    participant Mail as Email Service

    Admin->>AdminFrontend: Navigate to /admin/withdrawals
    AdminFrontend->>API: GET /api/admin/withdrawal-requests?status=pending
    API->>DB: SELECT * FROM withdrawal_requests WHERE status = 'pending'
    DB-->>API: Withdrawal requests
    API-->>AdminFrontend: 200 OK {data: [...]}
    AdminFrontend-->>Admin: Display pending requests table

    Admin->>AdminFrontend: Click "View Details" on request ID 42
    AdminFrontend->>API: GET /api/admin/withdrawal-requests/42
    API->>DB: SELECT * FROM withdrawal_requests WHERE id = 42
    DB-->>API: Request details
    API->>DB: SELECT * FROM instructor_commissions<br/>WHERE environment_id = ? AND status = 'approved'<br/>AND withdrawal_request_id IS NULL
    DB-->>API: Available commissions
    API-->>AdminFrontend: 200 OK {request, available_commissions}
    AdminFrontend-->>Admin: Show modal with breakdown

    alt Admin Approves
        Admin->>AdminFrontend: Click "Approve" button
        AdminFrontend->>API: POST /api/admin/withdrawal-requests/42/approve

        API->>WithdrawalController: approve(id: 42)
        WithdrawalController->>WithdrawalService: approveWithdrawal(withdrawalRequest)

        WithdrawalService->>DB: BEGIN TRANSACTION

        WithdrawalService->>DB: UPDATE withdrawal_requests<br/>SET status = 'approved', approved_at = NOW(),<br/>approved_by = {admin_user_id}<br/>WHERE id = 42

        WithdrawalService->>InstructorCommissionService: getAvailableBalance(environmentId)
        InstructorCommissionService-->>WithdrawalService: balance: 150000

        alt Sufficient Balance
            WithdrawalService->>DB: UPDATE instructor_commissions<br/>SET withdrawal_request_id = 42<br/>WHERE environment_id = ? AND status = 'approved'<br/>AND withdrawal_request_id IS NULL<br/>LIMIT {amount / net_amount}

            WithdrawalService->>DB: COMMIT TRANSACTION

            WithdrawalService->>Queue: Dispatch WithdrawalApproved event
            Queue->>Mail: Send email to instructor:<br/>"Your withdrawal of 100,000 XAF has been approved"

            WithdrawalService-->>WithdrawalController: Success
            WithdrawalController-->>API: 200 OK {message: "Approved"}
            API-->>AdminFrontend: 200 OK
            AdminFrontend-->>Admin: Success toast: "Withdrawal approved"
        else Insufficient Balance
            WithdrawalService->>DB: ROLLBACK TRANSACTION
            WithdrawalService-->>WithdrawalController: Exception: "Insufficient balance"
            WithdrawalController-->>API: 422 Unprocessable Entity
            API-->>AdminFrontend: 422 {error}
            AdminFrontend-->>Admin: Error toast
        end

    else Admin Rejects
        Admin->>AdminFrontend: Click "Reject" button, enter reason
        AdminFrontend->>API: POST /api/admin/withdrawal-requests/42/reject<br/>{rejection_reason: "Incomplete bank details"}

        API->>WithdrawalController: reject(id: 42, reason)
        WithdrawalController->>WithdrawalService: rejectWithdrawal(request, reason)

        WithdrawalService->>DB: UPDATE withdrawal_requests<br/>SET status = 'rejected', rejected_at = NOW(),<br/>rejection_reason = 'Incomplete bank details'<br/>WHERE id = 42

        WithdrawalService->>Queue: Dispatch WithdrawalRejected event
        Queue->>Mail: Send email to instructor:<br/>"Your withdrawal was rejected: Incomplete bank details"

        WithdrawalService-->>WithdrawalController: Success
        WithdrawalController-->>API: 200 OK
        API-->>AdminFrontend: 200 OK
        AdminFrontend-->>Admin: Success toast: "Withdrawal rejected"
    end

    Note over Admin,Mail: Later: Admin processes payment manually

    Admin->>AdminFrontend: Click "Mark as Processed" on approved request
    AdminFrontend->>API: POST /api/admin/withdrawal-requests/42/process<br/>{payment_reference: "BANK-TXN-789"}

    API->>WithdrawalController: process(id: 42, reference)
    WithdrawalController->>WithdrawalService: processWithdrawal(request, reference)

    WithdrawalService->>DB: UPDATE withdrawal_requests<br/>SET status = 'completed', processed_at = NOW(),<br/>payment_reference = 'BANK-TXN-789'<br/>WHERE id = 42

    WithdrawalService->>DB: UPDATE instructor_commissions<br/>SET status = 'paid', paid_at = NOW(),<br/>payment_reference = 'BANK-TXN-789'<br/>WHERE withdrawal_request_id = 42

    WithdrawalService->>Queue: Dispatch WithdrawalCompleted event
    Queue->>Mail: Send email to instructor:<br/>"Payment processed: 100,000 XAF<br/>Reference: BANK-TXN-789"

    WithdrawalService-->>WithdrawalController: Success
    WithdrawalController-->>API: 200 OK
    API-->>AdminFrontend: 200 OK
    AdminFrontend-->>Admin: Success toast: "Withdrawal marked as completed"
```

**Key Points:**
- Three-step approval process: pending → approved → processing → completed
- Database transaction ensures atomicity (approve + link commissions)
- Commission records linked to withdrawal request (prevents double-payout)
- Email notifications at each stage (approved, rejected, completed)
- Payment reference stored for audit trail (bank transaction ID)

---

## 8.8 Course Enrollment & Certificate Generation

```mermaid
sequenceDiagram
    actor Learner
    participant Frontend as Next.js Frontend
    participant API as Laravel API
    participant OrderService as OrderService
    participant EnrollmentService as EnrollmentService
    participant CertificateService as CertificateGenerationService
    participant DB as MySQL Database
    participant Queue as RabbitMQ Queue
    participant Mail as Email Service

    Note over Learner,Mail: After successful payment

    Queue->>OrderService: Handle OrderPaymentProcessed event
    OrderService->>DB: SELECT * FROM orders WHERE id = ?
    DB-->>OrderService: Order {status: 'completed', items: [...]}

    OrderService->>EnrollmentService: enrollUserInCourses(order)

    loop For each order item (course)
        EnrollmentService->>DB: INSERT INTO enrollments<br/>(user_id, course_id, environment_id,<br/>status: 'active', enrolled_at: NOW())
        DB-->>EnrollmentService: Enrollment created
    end

    EnrollmentService->>Queue: Dispatch EnrollmentCreated event
    Queue->>Mail: Send welcome email with course access link

    EnrollmentService-->>OrderService: Success

    Note over Learner,Mail: Learner completes course

    Learner->>Frontend: Complete final activity
    Frontend->>API: PUT /api/enrollments/{id}/activity-completions/{activityId}
    API->>DB: UPDATE activity_completions SET completed_at = NOW()

    API->>API: Check if all activities completed

    alt All Activities Completed
        API->>DB: UPDATE enrollments SET status = 'completed',<br/>completed_at = NOW()

        API->>CertificateService: issueCertificate(certificateContentId, enrollmentId)

        CertificateService->>DB: SELECT * FROM enrollments WHERE id = ?
        CertificateService->>DB: SELECT * FROM certificate_templates

        CertificateService->>CertificateService: Generate PDF with DomPDF:<br/>- Learner name<br/>- Course title<br/>- Completion date<br/>- Certificate code (UUID)

        CertificateService->>DB: INSERT INTO certificates<br/>(enrollment_id, certificate_code: UUID,<br/>issued_at: NOW(), file_path: '/storage/...')
        DB-->>CertificateService: Certificate created

        CertificateService->>Queue: Dispatch CertificateIssued event
        Queue->>Mail: Send email with PDF attachment

        CertificateService-->>API: {certificate}
        API-->>Frontend: 200 OK {enrollment, certificate}
        Frontend-->>Learner: Show congratulations + download button
    end
```

**Key Points:**
- Enrollment created asynchronously after payment (via queue)
- Certificate generated only when ALL activities completed
- Certificate code (UUID) for verification via public API
- PDF stored in `storage/app/public/certificates` or S3
- Email with certificate PDF attachment sent automatically

---

## 8.9 Error Handling Patterns

**Payment Gateway Failure:**
```mermaid
sequenceDiagram
    participant PaymentService
    participant Gateway as Stripe API
    participant DB as MySQL
    participant Telegram

    PaymentService->>Gateway: POST /v1/payment_intents
    Gateway-->>PaymentService: 500 Internal Server Error

    PaymentService->>PaymentService: Retry 1 (wait 2s)
    PaymentService->>Gateway: POST /v1/payment_intents
    Gateway-->>PaymentService: 500 Internal Server Error

    PaymentService->>PaymentService: Retry 2 (wait 4s)
    PaymentService->>Gateway: POST /v1/payment_intents
    Gateway-->>PaymentService: 500 Internal Server Error

    PaymentService->>PaymentService: Retry 3 (wait 8s)
    PaymentService->>Gateway: POST /v1/payment_intents
    Gateway-->>PaymentService: 500 Internal Server Error

    PaymentService->>DB: UPDATE transactions SET status = 'failed'
    PaymentService->>Telegram: Alert: "Payment gateway failure: Stripe"
    PaymentService-->>PaymentService: Throw PaymentGatewayException
```

**Fallback to Environment Gateway (Centralized Failure):**
```mermaid
sequenceDiagram
    participant PaymentService
    participant EnvConfigService
    participant Gateway as Gateway (Env 1)
    participant FallbackGateway as Gateway (Env 3)

    PaymentService->>EnvConfigService: isCentralized(environmentId: 3)
    EnvConfigService-->>PaymentService: true

    PaymentService->>PaymentService: Try Environment 1 gateway
    PaymentService->>Gateway: initiatePayment()
    Gateway-->>PaymentService: Exception (gateway down)

    PaymentService->>PaymentService: Log warning: "Centralized gateway failed"
    PaymentService->>PaymentService: Fallback to Environment 3 gateway

    PaymentService->>FallbackGateway: initiatePayment()
    FallbackGateway-->>PaymentService: Success

    PaymentService->>PaymentService: Log: "Fallback successful"
```

---

---
