# 5. API Specification

## 5.1 API Overview

**API Type:** RESTful HTTP/JSON API
**Base URL:** `https://api.csl-platform.com/api` (production)
**Authentication:** Laravel Sanctum (JWT Bearer Tokens)
**API Version:** v1 (implicit in all endpoints)
**Documentation Format:** OpenAPI 3.0

## 5.2 Authentication

All authenticated endpoints require a Bearer token in the Authorization header:

```http
Authorization: Bearer {token}
```

**Token Creation:**
```http
POST /api/tokens
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123",
  "environment_id": 1
}

Response 200:
{
  "token": "1|abcdef123456...",
  "user": { ... },
  "environment_id": 1
}
```

**Token Abilities:**
- Tokens include `environment_id:{id}` ability for multi-tenant scoping
- All API endpoints automatically scope data by token's environment
- Super admin tokens can access cross-environment data

## 5.3 Core API Endpoints

### 5.3.1 Authentication Endpoints

```yaml
/api/register:
  post:
    summary: Register new user
    tags: [Authentication]
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              name: string
              email: string
              password: string
              environment_id: integer
    responses:
      201:
        description: User registered successfully
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/User'

/api/tokens:
  post:
    summary: Create authentication token (login)
    tags: [Authentication]
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              email: string
              password: string
              environment_id: integer
    responses:
      200:
        description: Token created successfully
        content:
          application/json:
            schema:
              type: object
              properties:
                token: string
                user: { $ref: '#/components/schemas/User' }
                environment_id: integer
  delete:
    summary: Revoke all user tokens (logout)
    tags: [Authentication]
    security:
      - bearerAuth: []
    responses:
      204:
        description: Tokens revoked successfully

/api/forgot-password:
  post:
    summary: Send password reset email
    tags: [Authentication]
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              email: string
    responses:
      200:
        description: Reset email sent

/api/reset-password:
  post:
    summary: Reset password with token
    tags: [Authentication]
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              token: string
              email: string
              password: string
              password_confirmation: string
    responses:
      200:
        description: Password reset successfully
```

### 5.3.2 Course Management Endpoints

```yaml
/api/courses:
  get:
    summary: List all courses (paginated)
    tags: [Courses]
    security:
      - bearerAuth: []
    parameters:
      - name: page
        in: query
        schema: { type: integer }
      - name: per_page
        in: query
        schema: { type: integer }
      - name: status
        in: query
        schema: { enum: [draft, published, archived] }
    responses:
      200:
        description: Courses retrieved successfully
        content:
          application/json:
            schema:
              type: object
              properties:
                data:
                  type: array
                  items: { $ref: '#/components/schemas/Course' }
                meta: { $ref: '#/components/schemas/PaginationMeta' }
  post:
    summary: Create new course
    tags: [Courses]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              title: string
              description: string
              thumbnail_url: string
    responses:
      201:
        description: Course created successfully

/api/courses/{id}:
  get:
    summary: Get course by ID
    tags: [Courses]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Course retrieved successfully
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Course'
  put:
    summary: Update course
    tags: [Courses]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              title: string
              description: string
              status: { enum: [draft, published, archived] }
    responses:
      200:
        description: Course updated successfully
  delete:
    summary: Delete course
    tags: [Courses]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      204:
        description: Course deleted successfully

/api/courses/{id}/publish:
  post:
    summary: Publish course
    tags: [Courses]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Course published successfully
```

### 5.3.3 Product & E-commerce Endpoints

```yaml
/api/products:
  get:
    summary: List all products
    tags: [Products]
    security:
      - bearerAuth: []
    parameters:
      - name: is_featured
        in: query
        schema: { type: boolean }
      - name: product_type
        in: query
        schema: { enum: [course, bundle, subscription] }
    responses:
      200:
        description: Products retrieved successfully
  post:
    summary: Create new product
    tags: [Products]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              name: string
              price: number
              currency: string
              course_id: integer
              product_type: { enum: [course, bundle, subscription] }
    responses:
      201:
        description: Product created successfully

/api/orders:
  get:
    summary: List all orders
    tags: [Orders]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Orders retrieved successfully
  post:
    summary: Create new order
    tags: [Orders]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              product_id: integer
              payment_method: string
    responses:
      201:
        description: Order created successfully

/api/my-orders:
  get:
    summary: Get current user's orders
    tags: [Orders]
    security:
      - bearerAuth: []
    responses:
      200:
        description: User orders retrieved successfully
```

### 5.3.4 Payment & Transaction Endpoints

```yaml
/api/storefront/{environmentId}/checkout:
  post:
    summary: Process checkout and create order with payment
    tags: [Payments]
    parameters:
      - name: environmentId
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              cart_items: array
              payment_method: string
              billing_details: object
    responses:
      201:
        description: Order created, payment initiated
        content:
          application/json:
            schema:
              type: object
              properties:
                order_id: integer
                transaction_id: integer
                payment_url: string

/api/payments/transactions/callback/success/{environment_id}:
  get:
    summary: Payment gateway success callback
    tags: [Payments]
    parameters:
      - name: environment_id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Payment processed successfully

/api/payments/transactions/callback/failure/{environment_id}:
  get:
    summary: Payment gateway failure callback
    tags: [Payments]
    parameters:
      - name: environment_id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Payment failure recorded

/api/payments/transactions/webhook/{gateway}/{environment_id}:
  post:
    summary: Payment gateway webhook for async notifications
    tags: [Payments]
    parameters:
      - name: gateway
        in: path
        required: true
        schema: { enum: [stripe, paypal, monetbil, lygos] }
      - name: environment_id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Webhook processed

/api/transactions:
  get:
    summary: List all transactions
    tags: [Transactions]
    security:
      - bearerAuth: []
    parameters:
      - name: status
        in: query
        schema: { enum: [pending, processing, completed, failed, refunded] }
    responses:
      200:
        description: Transactions retrieved successfully
```

### 5.3.5 Payment Gateway Management Endpoints

```yaml
/api/payment-gateways:
  get:
    summary: List payment gateway settings
    tags: [Payment Gateways]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Payment gateways retrieved
  post:
    summary: Create payment gateway setting
    tags: [Payment Gateways]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              gateway_code: { enum: [stripe, paypal, monetbil, lygos] }
              gateway_name: string
              api_key: string
              secret_key: string
              is_active: boolean
    responses:
      201:
        description: Payment gateway created

/api/payment-gateways/{id}:
  get:
    summary: Get payment gateway by ID
    tags: [Payment Gateways]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Payment gateway retrieved
  put:
    summary: Update payment gateway
    tags: [Payment Gateways]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              is_active: boolean
              is_default: boolean
    responses:
      200:
        description: Payment gateway updated
  delete:
    summary: Delete payment gateway
    tags: [Payment Gateways]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      204:
        description: Payment gateway deleted
```

---

## 5.4 NEW API Endpoints (Payment Gateway Centralization Epic)

### 5.4.1 Admin Commission Management Endpoints

```yaml
/api/admin/commissions:
  get:
    summary: List all instructor commissions (admin)
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    parameters:
      - name: environment_id
        in: query
        schema: { type: integer }
      - name: status
        in: query
        schema: { enum: [pending, approved, paid, disputed] }
      - name: from_date
        in: query
        schema: { type: string, format: date }
      - name: to_date
        in: query
        schema: { type: string, format: date }
    responses:
      200:
        description: Commissions retrieved successfully
        content:
          application/json:
            schema:
              type: object
              properties:
                data:
                  type: array
                  items: { $ref: '#/components/schemas/InstructorCommission' }
                meta: { $ref: '#/components/schemas/PaginationMeta' }

/api/admin/commissions/{id}:
  get:
    summary: Get commission details
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Commission retrieved successfully
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/InstructorCommission'

/api/admin/commissions/{id}/approve:
  post:
    summary: Approve instructor commission
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Commission approved successfully

/api/admin/commissions/bulk-approve:
  post:
    summary: Approve multiple commissions
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              commission_ids:
                type: array
                items: { type: integer }
    responses:
      200:
        description: Commissions approved successfully

/api/admin/commissions/stats:
  get:
    summary: Get commission statistics
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Statistics retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                total_owed: number
                total_paid: number
                pending_approval: number
                approved_unpaid: number

/api/admin/commissions/environment/{environmentId}:
  get:
    summary: Filter commissions by environment
    tags: [Admin - Commissions]
    security:
      - bearerAuth: []
    parameters:
      - name: environmentId
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Environment commissions retrieved
```

### 5.4.2 Admin Withdrawal Management Endpoints

```yaml
/api/admin/withdrawal-requests:
  get:
    summary: List all withdrawal requests
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: status
        in: query
        schema: { enum: [pending, approved, rejected, processing, completed] }
      - name: environment_id
        in: query
        schema: { type: integer }
    responses:
      200:
        description: Withdrawal requests retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                data:
                  type: array
                  items: { $ref: '#/components/schemas/WithdrawalRequest' }
                meta: { $ref: '#/components/schemas/PaginationMeta' }

/api/admin/withdrawal-requests/{id}:
  get:
    summary: Get withdrawal request details
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Withdrawal request retrieved
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/WithdrawalRequest'

/api/admin/withdrawal-requests/{id}/approve:
  post:
    summary: Approve withdrawal request
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Withdrawal request approved

/api/admin/withdrawal-requests/{id}/reject:
  post:
    summary: Reject withdrawal request
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              rejection_reason: string
    responses:
      200:
        description: Withdrawal request rejected

/api/admin/withdrawal-requests/{id}/process:
  post:
    summary: Mark withdrawal as processed/paid
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              payment_reference: string
    responses:
      200:
        description: Withdrawal marked as completed

/api/admin/withdrawal-requests/stats:
  get:
    summary: Get withdrawal statistics
    tags: [Admin - Withdrawals]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Statistics retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                pending_count: integer
                pending_amount: number
                approved_count: integer
                completed_amount: number
```

### 5.4.3 Admin Environment Payment Config Endpoints

```yaml
/api/admin/environment-payment-configs:
  get:
    summary: List all environment payment configs
    tags: [Admin - Payment Config]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Configs retrieved
        content:
          application/json:
            schema:
              type: array
              items: { $ref: '#/components/schemas/EnvironmentPaymentConfig' }

/api/admin/environment-payment-configs/{environmentId}:
  get:
    summary: Get payment config for environment
    tags: [Admin - Payment Config]
    security:
      - bearerAuth: []
    parameters:
      - name: environmentId
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Config retrieved
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/EnvironmentPaymentConfig'
  put:
    summary: Update payment config
    tags: [Admin - Payment Config]
    security:
      - bearerAuth: []
    parameters:
      - name: environmentId
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              instructor_commission_rate: number
              minimum_withdrawal_amount: number
              payment_terms: { enum: [NET_30, NET_60, Immediate] }
    responses:
      200:
        description: Config updated

/api/admin/environment-payment-configs/{environmentId}/toggle:
  post:
    summary: Toggle centralized gateways on/off
    tags: [Admin - Payment Config]
    security:
      - bearerAuth: []
    parameters:
      - name: environmentId
        in: path
        required: true
        schema: { type: integer }
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              use_centralized_gateways: boolean
    responses:
      200:
        description: Config toggled successfully
```

### 5.4.4 Admin Centralized Transaction Endpoints

```yaml
/api/admin/centralized-transactions:
  get:
    summary: Get all transactions using centralized gateways
    tags: [Admin - Transactions]
    security:
      - bearerAuth: []
    parameters:
      - name: environment_id
        in: query
        schema: { type: integer }
      - name: from_date
        in: query
        schema: { type: string, format: date }
      - name: to_date
        in: query
        schema: { type: string, format: date }
    responses:
      200:
        description: Centralized transactions retrieved

/api/admin/centralized-transactions/{id}:
  get:
    summary: Get centralized transaction details
    tags: [Admin - Transactions]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Transaction retrieved

/api/admin/centralized-transactions/stats:
  get:
    summary: Get centralized transaction statistics
    tags: [Admin - Transactions]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Statistics retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                total_revenue: number
                average_transaction: number
                success_rate: number
                transaction_count: integer

/api/admin/centralized-transactions/environment/{id}:
  get:
    summary: Filter centralized transactions by environment
    tags: [Admin - Transactions]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Environment transactions retrieved

/api/admin/centralized-transactions/export:
  get:
    summary: Export transactions to CSV
    tags: [Admin - Transactions]
    security:
      - bearerAuth: []
    parameters:
      - name: format
        in: query
        schema: { enum: [csv, excel] }
      - name: from_date
        in: query
        schema: { type: string, format: date }
      - name: to_date
        in: query
        schema: { type: string, format: date }
    responses:
      200:
        description: CSV file download
        content:
          text/csv:
            schema:
              type: string
```

### 5.4.5 Instructor Earnings Endpoints

```yaml
/api/instructor/earnings:
  get:
    summary: List instructor's commissions
    tags: [Instructor - Earnings]
    security:
      - bearerAuth: []
    parameters:
      - name: status
        in: query
        schema: { enum: [pending, approved, paid] }
      - name: from_date
        in: query
        schema: { type: string, format: date }
      - name: to_date
        in: query
        schema: { type: string, format: date }
    responses:
      200:
        description: Commissions retrieved
        content:
          application/json:
            schema:
              type: array
              items: { $ref: '#/components/schemas/InstructorCommission' }

/api/instructor/earnings/stats:
  get:
    summary: Get earnings statistics
    tags: [Instructor - Earnings]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Statistics retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                total_earned: number
                total_paid: number
                pending_amount: number
                available_balance: number

/api/instructor/earnings/balance:
  get:
    summary: Get available balance for withdrawal
    tags: [Instructor - Earnings]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Balance retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                available_balance: number
                currency: string
```

### 5.4.6 Instructor Withdrawal Endpoints

```yaml
/api/instructor/withdrawals:
  get:
    summary: List instructor's withdrawal requests
    tags: [Instructor - Withdrawals]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Withdrawal requests retrieved
        content:
          application/json:
            schema:
              type: array
              items: { $ref: '#/components/schemas/WithdrawalRequest' }
  post:
    summary: Create new withdrawal request
    tags: [Instructor - Withdrawals]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              amount: number
              withdrawal_method: { enum: [bank_transfer, paypal, mobile_money] }
              withdrawal_details: object
    responses:
      201:
        description: Withdrawal request created

/api/instructor/withdrawals/{id}:
  get:
    summary: Get withdrawal request details
    tags: [Instructor - Withdrawals]
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema: { type: integer }
    responses:
      200:
        description: Withdrawal request retrieved
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/WithdrawalRequest'
```

### 5.4.7 Instructor Payment Config Endpoints

```yaml
/api/instructor/payment-config:
  get:
    summary: Get instructor's payment configuration
    tags: [Instructor - Payment Config]
    security:
      - bearerAuth: []
    responses:
      200:
        description: Payment config retrieved
        content:
          application/json:
            schema:
              type: object
              properties:
                withdrawal_method: { enum: [bank_transfer, paypal, mobile_money] }
                withdrawal_details: object
  put:
    summary: Update withdrawal method and account details
    tags: [Instructor - Payment Config]
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              withdrawal_method: { enum: [bank_transfer, paypal, mobile_money] }
              withdrawal_details:
                oneOf:
                  - type: object # Bank transfer
                    properties:
                      account_number: string
                      bank_name: string
                      account_holder_name: string
                      swift_code: string
                  - type: object # PayPal
                    properties:
                      paypal_email: string
                  - type: object # Mobile money
                    properties:
                      phone_number: string
                      provider: { enum: [mtn, orange, moov] }
    responses:
      200:
        description: Payment config updated
```

---

## 5.5 Example Requests & Responses

### Example 1: Create Withdrawal Request (Instructor)

**Request:**
```http
POST /api/instructor/withdrawals
Authorization: Bearer 1|abcdef123456...
Content-Type: application/json

{
  "amount": 100000,
  "withdrawal_method": "bank_transfer",
  "withdrawal_details": {
    "account_number": "0123456789",
    "bank_name": "Afriland First Bank",
    "account_holder_name": "John Doe",
    "swift_code": "CCBACMCX"
  }
}
```

**Response:**
```http
HTTP/1.1 201 Created
Content-Type: application/json

{
  "id": 42,
  "environment_id": 3,
  "requested_amount": 100000,
  "currency": "XAF",
  "withdrawal_method": "bank_transfer",
  "withdrawal_details": {
    "account_number": "0123456789",
    "bank_name": "Afriland First Bank",
    "account_holder_name": "John Doe",
    "swift_code": "CCBACMCX"
  },
  "status": "pending",
  "requested_at": "2025-10-08T14:30:00Z",
  "created_at": "2025-10-08T14:30:00Z",
  "updated_at": "2025-10-08T14:30:00Z"
}
```

### Example 2: Approve Withdrawal (Admin)

**Request:**
```http
POST /api/admin/withdrawal-requests/42/approve
Authorization: Bearer 1|admin_token...
Content-Type: application/json
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "id": 42,
  "environment_id": 3,
  "requested_amount": 100000,
  "currency": "XAF",
  "status": "approved",
  "approved_at": "2025-10-08T15:00:00Z",
  "approved_by": 1,
  "message": "Withdrawal request approved successfully"
}
```

### Example 3: Toggle Centralized Gateway (Admin)

**Request:**
```http
POST /api/admin/environment-payment-configs/3/toggle
Authorization: Bearer 1|admin_token...
Content-Type: application/json

{
  "use_centralized_gateways": true
}
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "id": 3,
  "environment_id": 3,
  "use_centralized_gateways": true,
  "instructor_commission_rate": 0.15,
  "minimum_withdrawal_amount": 50000,
  "payment_terms": "NET_30",
  "updated_at": "2025-10-08T16:00:00Z",
  "message": "Centralized gateways enabled for environment 3"
}
```

---

## 5.6 Error Responses

All API endpoints follow consistent error response formats:

**Validation Error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount must be at least 50000."],
    "withdrawal_method": ["The selected withdrawal method is invalid."]
  }
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Forbidden (403):**
```json
{
  "message": "This action is unauthorized."
}
```

**Not Found (404):**
```json
{
  "message": "Resource not found."
}
```

**Server Error (500):**
```json
{
  "message": "Server error occurred.",
  "error": "Detailed error message (only in development mode)"
}
```

---

---
