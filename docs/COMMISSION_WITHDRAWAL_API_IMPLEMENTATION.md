# Commission and Withdrawal API Implementation

## Overview
This document describes the complete implementation of Admin and Instructor API endpoints for managing commissions, withdrawals, and payment configurations as outlined in Stories 4 and 5 of the Payment Gateway Centralization (PGC) project.

## Implementation Summary

### Story 4: Admin API Endpoints (PGC-004) ✅
Complete implementation of admin endpoints for managing commissions, withdrawals, and payment configurations.

### Story 5: Instructor API Endpoints (PGC-005) ✅
Complete implementation of instructor endpoints for viewing earnings and requesting withdrawals.

## Files Created

### Admin Controllers

#### 1. CommissionController (`app/Http/Controllers/Api/Admin/CommissionController.php`)
**Purpose**: Manage instructor commissions

**Routes**:
- `GET /api/admin/commissions` - List all instructor commissions with filters
- `GET /api/admin/commissions/{id}` - Get commission details
- `POST /api/admin/commissions/{id}/approve` - Approve a commission
- `POST /api/admin/commissions/bulk-approve` - Approve multiple commissions
- `GET /api/admin/commissions/stats` - Commission statistics
- `GET /api/admin/commissions/environment/{environmentId}` - Filter by environment

**Key Features**:
- Pagination support (default: 15 per page)
- Filters: environment_id, status, date range (start_date, end_date)
- Comprehensive stats: total, pending, approved, paid amounts and counts
- Bulk approval capability
- Super admin authorization required

**Statistics Response**:
```json
{
  "success": true,
  "data": {
    "total_commissions": 150,
    "total_amount": 15000.00,
    "pending_amount": 5000.00,
    "approved_amount": 7000.00,
    "paid_amount": 3000.00,
    "pending_count": 50,
    "approved_count": 70,
    "paid_count": 30
  }
}
```

#### 2. WithdrawalRequestController (`app/Http/Controllers/Api/Admin/WithdrawalRequestController.php`)
**Purpose**: Manage instructor withdrawal requests

**Routes**:
- `GET /api/admin/withdrawal-requests` - List all requests
- `GET /api/admin/withdrawal-requests/{id}` - Get request details
- `POST /api/admin/withdrawal-requests/{id}/approve` - Approve request
- `POST /api/admin/withdrawal-requests/{id}/reject` - Reject request
- `POST /api/admin/withdrawal-requests/{id}/process` - Mark as processed/paid
- `GET /api/admin/withdrawal-requests/stats` - Withdrawal stats

**Key Features**:
- Approval workflow: pending → approved → completed
- Rejection with reason (returns commissions to approved status)
- Processing with payment reference (updates commissions to paid)
- Database transactions for data integrity
- Super admin authorization required

**Approve Request Body**: None required

**Reject Request Body**:
```json
{
  "rejection_reason": "Insufficient documentation"
}
```

**Process Request Body**:
```json
{
  "payment_reference": "PAY-12345-BANK-TRANSFER"
}
```

#### 3. CentralizedTransactionController (`app/Http/Controllers/Api/Admin/CentralizedTransactionController.php`)
**Purpose**: View and analyze centralized payment transactions

**Routes**:
- `GET /api/admin/centralized-transactions` - All transactions
- `GET /api/admin/centralized-transactions/{id}` - Transaction details
- `GET /api/admin/centralized-transactions/stats` - Transaction stats
- `GET /api/admin/centralized-transactions/environment/{environmentId}` - Filter by environment
- `GET /api/admin/centralized-transactions/export` - Export to CSV

**Key Features**:
- Only shows transactions from environments using centralized gateways
- Filters: environment_id, status, gateway, date range
- Revenue analytics by gateway
- Success rate calculation
- CSV export with filters
- Super admin authorization required

**Statistics Response**:
```json
{
  "success": true,
  "data": {
    "total_transactions": 500,
    "total_revenue": 50000.00,
    "average_transaction": 100.00,
    "completed_count": 450,
    "pending_count": 30,
    "failed_count": 20,
    "success_rate": 95.74,
    "revenue_by_gateway": [
      {
        "gateway": "stripe",
        "total": 30000.00,
        "count": 300
      },
      {
        "gateway": "taramoney",
        "total": 20000.00,
        "count": 200
      }
    ]
  }
}
```

#### 4. EnvironmentPaymentConfigController (`app/Http/Controllers/Api/Admin/EnvironmentPaymentConfigController.php`)
**Purpose**: Manage environment payment configurations

**Routes**:
- `GET /api/admin/environment-payment-configs` - List all configs
- `GET /api/admin/environment-payment-configs/{environmentId}` - Get config
- `PUT /api/admin/environment-payment-configs/{environmentId}` - Update config
- `POST /api/admin/environment-payment-configs/{environmentId}/toggle` - Toggle centralized

**Key Features**:
- Update commission rates, minimum withdrawal amounts, payment terms
- Toggle centralized gateway usage per environment
- Validation for all inputs
- Super admin authorization required

**Update Request Body**:
```json
{
  "use_centralized_gateways": true,
  "instructor_commission_rate": 15.5,
  "minimum_withdrawal_amount": 50.00,
  "withdrawal_processing_days": 7,
  "payment_terms": "Net 30 days from approval"
}
```

### Instructor Controllers

#### 5. EarningsController (`app/Http/Controllers/Api/Instructor/EarningsController.php`)
**Purpose**: View instructor earnings and commissions

**Routes**:
- `GET /api/instructor/earnings` - List commissions
- `GET /api/instructor/earnings/stats` - Earnings statistics
- `GET /api/instructor/earnings/balance` - Available balance

**Key Features**:
- Scoped to instructor's environment (via ownedEnvironments)
- Filters: status, date range
- Detailed earnings breakdown
- Available balance calculation
- Sanctum authentication required

**Balance Response**:
```json
{
  "success": true,
  "data": {
    "available_balance": 1500.00,
    "pending_withdrawal": 500.00,
    "currency": "USD"
  }
}
```

**Stats Response**:
```json
{
  "success": true,
  "data": {
    "total_earned": 5000.00,
    "total_paid": 2000.00,
    "pending_amount": 1500.00,
    "approved_amount": 1500.00,
    "available_balance": 1500.00,
    "pending_withdrawal": 0.00,
    "total_commissions": 50,
    "paid_count": 20
  }
}
```

#### 6. WithdrawalController (`app/Http/Controllers/Api/Instructor/WithdrawalController.php`)
**Purpose**: Request and manage withdrawals

**Routes**:
- `GET /api/instructor/withdrawals` - List withdrawal requests
- `POST /api/instructor/withdrawals` - Create withdrawal request
- `GET /api/instructor/withdrawals/{id}` - Get withdrawal details

**Key Features**:
- Validation against minimum withdrawal amount
- Validation against available balance
- Method-specific detail validation (bank_transfer, paypal, mobile_money)
- Automatic commission attachment to withdrawal
- Database transactions for data integrity
- Scoped to instructor's environment
- Sanctum authentication required

**Create Request Body (Bank Transfer)**:
```json
{
  "amount": 500.00,
  "withdrawal_method": "bank_transfer",
  "withdrawal_details": {
    "account_name": "John Doe",
    "account_number": "1234567890",
    "bank_name": "First National Bank",
    "bank_code": "FNB001",
    "swift_code": "FNBBUS33"
  }
}
```

**Create Request Body (PayPal)**:
```json
{
  "amount": 500.00,
  "withdrawal_method": "paypal",
  "withdrawal_details": {
    "paypal_email": "instructor@example.com"
  }
}
```

**Create Request Body (Mobile Money)**:
```json
{
  "amount": 500.00,
  "withdrawal_method": "mobile_money",
  "withdrawal_details": {
    "phone_number": "+237670000000",
    "provider": "orange_money",
    "account_name": "John Doe"
  }
}
```

#### 7. PaymentConfigController (`app/Http/Controllers/Api/Instructor/PaymentConfigController.php`)
**Purpose**: Manage withdrawal method and account details

**Routes**:
- `GET /api/instructor/payment-config` - Get payment configuration
- `PUT /api/instructor/payment-config` - Update withdrawal method/details

**Key Features**:
- Stores withdrawal preferences in environment.payment_settings JSON column
- Returns available withdrawal methods with required fields
- Method-specific validation
- Scoped to instructor's environment
- Sanctum authentication required

**Get Response**:
```json
{
  "success": true,
  "data": {
    "withdrawal_method": "bank_transfer",
    "withdrawal_details": {
      "account_name": "John Doe",
      "account_number": "1234567890",
      "bank_name": "First National Bank"
    },
    "available_methods": {
      "bank_transfer": {
        "name": "Bank Transfer",
        "fields": {
          "account_name": "Account Holder Name",
          "account_number": "Account Number",
          "bank_name": "Bank Name",
          "bank_code": "Bank Code (Optional)",
          "swift_code": "SWIFT Code (Optional)"
        }
      },
      "paypal": { ... },
      "mobile_money": { ... }
    }
  }
}
```

## Database Changes

### Migration: `2025_10_09_111251_add_payment_settings_to_environments_table.php`

**Purpose**: Add JSON column to store instructor payment preferences

**Changes**:
```php
Schema::table('environments', function (Blueprint $table) {
    $table->json('payment_settings')->nullable()->after('state_code');
});
```

**Environment Model Updates**:
- Added `payment_settings` to `$fillable`
- Added `payment_settings` to `$casts` as 'array'

## Authorization

### Admin Endpoints
All admin endpoints require:
- `auth:sanctum` middleware
- User role must be `super_admin`
- Returns 403 Unauthorized if not super admin

**Authorization Check**:
```php
if (!$request->user() || $request->user()->role->value !== 'super_admin') {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

### Instructor Endpoints
All instructor endpoints require:
- `auth:sanctum` middleware
- Access scoped to instructor's owned environment
- Returns 401 Unauthorized if not authenticated
- Returns 404 if no environment found

**Environment Scoping**:
```php
$environment = $request->user()->ownedEnvironments()->first();
```

## Validation Rules

### Commission Approval
- Commission must exist
- Status must be 'pending'
- Only one commission can be approved at a time (or use bulk approve)

### Withdrawal Request Creation
- Amount: required, numeric, >= minimum_withdrawal_amount, <= available_balance
- Withdrawal method: required, in ['bank_transfer', 'paypal', 'mobile_money']
- Withdrawal details: required, array, validated based on method

### Withdrawal Request Approval
- Request must exist
- Status must be 'pending'
- Sets approved_by and approved_at

### Withdrawal Request Rejection
- Request must exist
- Status must be 'pending' or 'approved'
- Rejection reason: required, string, max 500 chars
- Returns attached commissions to 'approved' status

### Withdrawal Request Processing
- Request must exist
- Status must be 'approved'
- Payment reference: required, string, max 255, unique
- Updates attached commissions to 'paid' status

### Payment Config Update
- Withdrawal method: required, in ['bank_transfer', 'paypal', 'mobile_money']
- Details validated based on method (see controller for specific rules)

## API Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Pagination Response
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [ ... ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 10,
    "last_page_url": "...",
    "next_page_url": "...",
    "path": "...",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 150
  }
}
```

## Testing

### Admin Endpoints Testing

#### Test Commission Approval
```bash
# Get pending commissions
curl -X GET "https://certification.csl-brands.com/api/admin/commissions?status=pending" \
  -H "Authorization: Bearer {super_admin_token}"

# Approve single commission
curl -X POST "https://certification.csl-brands.com/api/admin/commissions/1/approve" \
  -H "Authorization: Bearer {super_admin_token}"

# Bulk approve commissions
curl -X POST "https://certification.csl-brands.com/api/admin/commissions/bulk-approve" \
  -H "Authorization: Bearer {super_admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"commission_ids": [1, 2, 3]}'
```

#### Test Withdrawal Processing
```bash
# Get pending withdrawals
curl -X GET "https://certification.csl-brands.com/api/admin/withdrawal-requests?status=pending" \
  -H "Authorization: Bearer {super_admin_token}"

# Approve withdrawal
curl -X POST "https://certification.csl-brands.com/api/admin/withdrawal-requests/1/approve" \
  -H "Authorization: Bearer {super_admin_token}"

# Process withdrawal
curl -X POST "https://certification.csl-brands.com/api/admin/withdrawal-requests/1/process" \
  -H "Authorization: Bearer {super_admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"payment_reference": "PAY-12345"}'

# Reject withdrawal
curl -X POST "https://certification.csl-brands.com/api/admin/withdrawal-requests/1/reject" \
  -H "Authorization: Bearer {super_admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"rejection_reason": "Invalid account details"}'
```

#### Test Statistics
```bash
# Commission stats
curl -X GET "https://certification.csl-brands.com/api/admin/commissions/stats" \
  -H "Authorization: Bearer {super_admin_token}"

# Withdrawal stats
curl -X GET "https://certification.csl-brands.com/api/admin/withdrawal-requests/stats" \
  -H "Authorization: Bearer {super_admin_token}"

# Transaction stats
curl -X GET "https://certification.csl-brands.com/api/admin/centralized-transactions/stats" \
  -H "Authorization: Bearer {super_admin_token}"
```

### Instructor Endpoints Testing

#### Test Earnings
```bash
# Get earnings list
curl -X GET "https://certification.csl-brands.com/api/instructor/earnings" \
  -H "Authorization: Bearer {instructor_token}"

# Get earnings stats
curl -X GET "https://certification.csl-brands.com/api/instructor/earnings/stats" \
  -H "Authorization: Bearer {instructor_token}"

# Get available balance
curl -X GET "https://certification.csl-brands.com/api/instructor/earnings/balance" \
  -H "Authorization: Bearer {instructor_token}"
```

#### Test Withdrawals
```bash
# Create withdrawal request
curl -X POST "https://certification.csl-brands.com/api/instructor/withdrawals" \
  -H "Authorization: Bearer {instructor_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500.00,
    "withdrawal_method": "bank_transfer",
    "withdrawal_details": {
      "account_name": "John Doe",
      "account_number": "1234567890",
      "bank_name": "First National Bank"
    }
  }'

# Get withdrawal requests
curl -X GET "https://certification.csl-brands.com/api/instructor/withdrawals" \
  -H "Authorization: Bearer {instructor_token}"
```

#### Test Payment Config
```bash
# Get payment config
curl -X GET "https://certification.csl-brands.com/api/instructor/payment-config" \
  -H "Authorization: Bearer {instructor_token}"

# Update payment config
curl -X PUT "https://certification.csl-brands.com/api/instructor/payment-config" \
  -H "Authorization: Bearer {instructor_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "withdrawal_method": "paypal",
    "withdrawal_details": {
      "paypal_email": "instructor@example.com"
    }
  }'
```

## Workflow Examples

### Instructor Withdrawal Workflow

1. **Instructor checks available balance**
   ```
   GET /api/instructor/earnings/balance
   → Returns available_balance: $1500
   ```

2. **Instructor sets up payment config (one-time)**
   ```
   PUT /api/instructor/payment-config
   → Saves bank account details
   ```

3. **Instructor requests withdrawal**
   ```
   POST /api/instructor/withdrawals
   → Creates withdrawal request (status: pending)
   → Attaches approved commissions to request
   ```

4. **Admin reviews and approves**
   ```
   POST /api/admin/withdrawal-requests/1/approve
   → Updates status to 'approved'
   → Sets approved_by and approved_at
   ```

5. **Admin processes payment**
   ```
   POST /api/admin/withdrawal-requests/1/process
   → Updates status to 'completed'
   → Updates attached commissions to 'paid'
   → Saves payment reference
   ```

### Commission Approval Workflow

1. **Transaction completed**
   - System creates InstructorCommission (status: pending)

2. **Admin reviews commissions**
   ```
   GET /api/admin/commissions?status=pending
   → Lists all pending commissions
   ```

3. **Admin approves commissions**
   ```
   POST /api/admin/commissions/bulk-approve
   → Updates status to 'approved'
   → Sets approved_at timestamp
   ```

4. **Instructor sees available balance increase**
   ```
   GET /api/instructor/earnings/balance
   → available_balance includes newly approved commissions
   ```

## Error Handling

### Common Error Scenarios

1. **Insufficient Balance**
   ```json
   {
     "success": false,
     "message": "Validation failed",
     "errors": {
       "amount": ["The amount must not be greater than 1500.00"]
     }
   }
   ```

2. **Below Minimum Withdrawal**
   ```json
   {
     "success": false,
     "message": "Validation failed",
     "errors": {
       "amount": ["The amount must be at least 50.00"]
     }
   }
   ```

3. **Invalid Status Transition**
   ```json
   {
     "success": false,
     "message": "Only pending commissions can be approved"
   }
   ```

4. **Unauthorized Access**
   ```json
   {
     "message": "Unauthorized"
   }
   ```

5. **Environment Not Found**
   ```json
   {
     "success": false,
     "message": "No environment found for this instructor"
   }
   ```

## Security Considerations

1. **Authentication**: All endpoints require valid Sanctum tokens
2. **Authorization**: Role-based access (super_admin vs instructor)
3. **Scope**: Instructors can only access their own environment data
4. **Validation**: All inputs validated before processing
5. **Database Transactions**: Critical operations use DB transactions
6. **Payment References**: Unique constraint prevents duplicate processing

## Next Steps

1. **Frontend Integration**: Build admin dashboard for commission/withdrawal management
2. **Notifications**: Email/SMS notifications for withdrawal status updates
3. **Reporting**: Enhanced analytics and reporting features
4. **Audit Logs**: Track all admin actions for compliance
5. **Batch Processing**: Bulk payment processing features
6. **Documentation**: OpenAPI/Swagger documentation
7. **Testing**: Unit and integration tests for all endpoints

## Related Documentation

- [Payment Gateway Centralization Guide](./PAYMENT_GATEWAY_CENTRALIZATION.md)
- [TaraMoney Integration Guide](./TARAMONEY_INTEGRATION.md)
- [Stories 3-8 Summary](./stories/STORIES-03-08-SUMMARY.md)
