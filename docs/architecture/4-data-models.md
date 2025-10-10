# 4. Data Models

## 4.1 Core Models

The CSL platform uses a relational data model with the following core entities. Models are defined in Laravel Eloquent (backend) and consumed via REST API by Next.js frontends.

### User
```typescript
interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: Date | null;
  password: string; // Hashed
  profile_photo_path: string | null;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environments: Environment[]; // Many-to-many via environment_users
  enrollments: Enrollment[];
  orders: Order[];
  instructorCommissions?: InstructorCommission[]; // NEW (Payment Gateway Epic)
}
```

### Environment
```typescript
interface Environment {
  id: number;
  name: string;
  primary_domain: string;
  additional_domains: string | null; // JSON array
  is_active: boolean;
  is_demo: boolean;
  demo_expires_at: Date | null;
  branding_data: object | null; // JSON
  created_at: Date;
  updated_at: Date;

  // Relationships
  users: User[]; // Many-to-many via environment_users
  courses: Course[];
  products: Product[];
  orders: Order[];
  transactions: Transaction[];
  paymentGatewaySettings: PaymentGatewaySetting[];
  paymentConfig?: EnvironmentPaymentConfig; // NEW (Payment Gateway Epic)
  instructorCommissions?: InstructorCommission[]; // NEW (Payment Gateway Epic)
  withdrawalRequests?: WithdrawalRequest[]; // NEW (Payment Gateway Epic)
}
```

### Course
```typescript
interface Course {
  id: number;
  environment_id: number;
  template_id: number | null;
  title: string;
  slug: string;
  description: string | null;
  status: 'draft' | 'published' | 'archived';
  thumbnail_url: string | null;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  sections: CourseSection[];
  enrollments: Enrollment[];
  products: Product[];
}
```

### Product
```typescript
interface Product {
  id: number;
  environment_id: number;
  course_id: number | null;
  category_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  price: number;
  currency: string;
  is_active: boolean;
  is_featured: boolean;
  image_url: string | null;
  product_type: 'course' | 'bundle' | 'subscription';
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  course?: Course;
  category?: ProductCategory;
  orders: Order[];
}
```

### Order
```typescript
interface Order {
  id: number;
  environment_id: number;
  user_id: number;
  order_number: string;
  total_amount: number;
  currency: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'refunded';
  payment_method: string;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  user: User;
  items: OrderItem[];
  transaction?: Transaction;
  instructorCommission?: InstructorCommission; // NEW (Payment Gateway Epic)
}
```

### Transaction
```typescript
interface Transaction {
  id: number;
  environment_id: number;
  order_id: number;
  gateway_code: string; // 'stripe', 'paypal', 'monetbil', 'lygos'
  transaction_reference: string;
  amount: number;
  fee_amount: number; // Platform fee
  tax_amount: number;
  total_amount: number;
  currency: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'refunded';
  gateway_response: object | null; // JSON
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  order: Order;
  instructorCommission?: InstructorCommission; // NEW (Payment Gateway Epic)
}
```

### PaymentGatewaySetting
```typescript
interface PaymentGatewaySetting {
  id: number;
  environment_id: number;
  gateway_code: string; // 'stripe', 'paypal', 'monetbil', 'lygos'
  gateway_name: string;
  api_key: string | null; // Encrypted
  secret_key: string | null; // Encrypted
  webhook_secret: string | null; // Encrypted
  is_active: boolean;
  is_default: boolean;
  configuration: object | null; // JSON - Gateway-specific config
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
}
```

### Commission (Platform Fee Rates)
```typescript
interface Commission {
  id: number;
  environment_id: number;
  commission_rate: number; // Decimal (e.g., 0.17 for 17%)
  is_active: boolean;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;

  // NOTE: This model is for PLATFORM FEE RATES, not instructor payouts
}
```

---

## 4.2 NEW Models (Payment Gateway Centralization Epic)

### EnvironmentPaymentConfig (NEW)
```typescript
interface EnvironmentPaymentConfig {
  id: number;
  environment_id: number; // FK to environments
  use_centralized_gateways: boolean; // Opt-in flag
  instructor_commission_rate: number; // Decimal (e.g., 0.15 for 15%)
  minimum_withdrawal_amount: number;
  payment_terms: 'NET_30' | 'NET_60' | 'Immediate';
  withdrawal_method_options: string[]; // JSON array ['bank_transfer', 'paypal', 'mobile_money']
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
}
```

### InstructorCommission (NEW - Instructor Payout Records)
```typescript
interface InstructorCommission {
  id: number;
  environment_id: number; // FK to environments
  transaction_id: number | null; // FK to transactions
  order_id: number | null; // FK to orders
  gross_amount: number; // Total order amount
  commission_rate: number; // Decimal (e.g., 0.15 for 15%)
  commission_amount: number; // Calculated: gross_amount * commission_rate
  net_amount: number; // Instructor's earnings: gross_amount - commission_amount
  currency: string;
  status: 'pending' | 'approved' | 'paid' | 'disputed';
  paid_at: Date | null;
  payment_reference: string | null;
  withdrawal_request_id: number | null; // FK to withdrawal_requests
  notes: string | null;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  transaction?: Transaction;
  order?: Order;
  withdrawalRequest?: WithdrawalRequest;

  // NOTE: This model is for INSTRUCTOR PAYOUT RECORDS, not platform fee rates
}
```

### WithdrawalRequest (NEW)
```typescript
interface WithdrawalRequest {
  id: number;
  environment_id: number; // FK to environments
  requested_amount: number;
  currency: string;
  withdrawal_method: 'bank_transfer' | 'paypal' | 'mobile_money';
  withdrawal_details: object; // JSON - Method-specific details (bank account, PayPal email, etc.)
  status: 'pending' | 'approved' | 'rejected' | 'processing' | 'completed';
  requested_at: Date;
  approved_at: Date | null;
  approved_by: number | null; // FK to users (admin)
  rejected_at: Date | null;
  rejection_reason: string | null;
  processed_at: Date | null;
  payment_reference: string | null;
  notes: string | null;
  created_at: Date;
  updated_at: Date;

  // Relationships
  environment: Environment;
  approver?: User;
  commissions: InstructorCommission[]; // Multiple commissions can be part of one withdrawal
}
```

---
