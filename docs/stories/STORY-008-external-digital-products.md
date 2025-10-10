# STORY-008: External Digital Products Support

## User Story

**As an** instructor
**I want** to sell digital products (files, external links, email-based content) without creating courses on the platform
**So that** I can monetize my existing content stored in Google Drive, Dropbox, or as downloadable files

**As a** customer
**I want** to receive immediate access to digital products after purchase
**So that** I can download files, access external links, or receive email instructions without waiting

---

## Business Context

### Problem Statement
Currently, the platform only supports the Template → Course → Product flow. Instructors who have:
- Content stored in Google Drive or Dropbox
- E-books (PDF, EPUB) they want to sell
- Email-based delivery (credentials, instructions, plain content)

...cannot use the platform without rebuilding their content as courses. This creates friction and limits market reach.

### Strategic Value
- **Market Expansion**: Attract instructors who sell digital products, not just courses
- **Revenue Diversification**: Enable e-book sales, resource bundles, and external content monetization
- **Competitive Advantage**: Support multiple content delivery models (courses + digital products)
- **Lower Barrier to Entry**: Instructors can start selling immediately without course creation

### Success Metrics
- 30% of new products created as digital products within 3 months
- 25% increase in instructor signups
- 90%+ successful digital product deliveries without support tickets
- Average time-to-market for instructors: <10 minutes (vs 2+ hours for course creation)

---

## Technical Context

### Existing Architecture Analysis

#### Backend (Laravel Multi-tenant)
- **Product Model**: Clean model with `fillable` fields, uses `BelongsToEnvironment` trait
  - Location: `/app/Models/Product.php`
  - Current fields: `name`, `slug`, `description`, `price`, `discount_price`, `is_subscription`, `status`, etc.
  - **Key Pattern**: Uses pivot table `product_courses` to link products to courses (many-to-many)

- **Order Processing**: Event-driven architecture
  - Location: `/app/Listeners/ProcessOrderItems.php`
  - `OrderCompleted` event triggers `ProcessOrderItems` listener (queued)
  - **Key Method**: `processProductCourses()` queries `product_courses` pivot table and creates `Enrollment` records
  - **Pattern to Mirror**: This same pattern will be used for digital product fulfillment

- **File Storage**: S3 and local storage configured via Laravel filesystems
  - Config: `/config/filesystems.php`
  - Already supports file uploads (used for course media, product thumbnails)

- **Email System**: Queue-based transactional emails
  - Uses Laravel Mailables with retry logic
  - Existing templates for orders, enrollments

- **Commission System**: Instructor commissions tracked via `instructor_commissions` table
  - Already integrated with order processing

#### Frontend (Next.js + TypeScript)
- **Checkout Flow**: Multi-step checkout working (payment gateways just integrated in Stories 1-7)
  - Location: `/app/checkout/[domain]/page.tsx`
  - Supports Stripe, PayPal, Lygos, MonetBill, TaraMoney

- **File Upload Components**: Reusable components exist
  - `/components/ui/file-upload.tsx`: Drag/drop, validation, preview, progress bar
  - `/components/ui/cloudinary-upload.tsx`: Cloud storage integration

- **Order Details Page**: Shows purchased items
  - Location: `/app/learners/orders/[id]/page.tsx`
  - **Critical Gap**: No download buttons or file access for digital products

- **API Service Layer**: Centralized API calls
  - Location: `/lib/services/storefront-service.ts`
  - Methods: `getAllProducts()`, `createOrder()`, `getOrderDetails()`, etc.

### Architectural Decision: Pivot Table Pattern

**Rationale**: Mirror the existing `product_courses` → `enrollments` pattern for consistency and maintainability.

**Before (Considered but Rejected)**:
```php
// Adding delivery_type directly on products table
products:
  - delivery_type: enum('course', 'file', 'external_link', 'email')
  - file_path, external_url, email_template (all nullable)
```
❌ **Problems**:
- Breaks single responsibility principle
- Products can't have BOTH courses AND files
- Not flexible for bundles (multiple files per product)
- Doesn't match existing codebase patterns

**After (Approved Architecture)**:
```php
// Pivot table pattern (mirrors product_courses)
product_assets:
  - Links products to deliverable assets (many-to-many relationship)

asset_deliveries:
  - Tracks access grants (mirrors enrollments pattern)
```
✅ **Benefits**:
- Follows existing codebase patterns → developers understand immediately
- Products can have courses AND/OR digital assets
- Minimal code changes to existing systems
- Future-proof for bundles and complex products
- Supports multiple files per product

---

## Database Schema Changes

### Migration 1: Extend Products Table

**File**: `database/migrations/2025_10_09_create_product_type_and_fulfillment.php`

```php
Schema::table('products', function (Blueprint $table) {
    $table->enum('product_type', ['course', 'digital', 'bundle'])
          ->default('course')
          ->after('status');

    $table->boolean('requires_fulfillment')
          ->default(false)
          ->after('product_type')
          ->comment('True if product needs digital delivery (files/links/email)');
});
```

**Rationale**:
- `product_type`: Distinguishes digital products from course-based products
- `requires_fulfillment`: Flag for ProcessOrderItems to trigger asset delivery
- `default('course')`: Backward compatible with existing products

---

### Migration 2: Create Product Assets Table

**File**: `database/migrations/2025_10_09_create_product_assets_table.php`

```php
Schema::create('product_assets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->onDelete('cascade');

    // Asset type and delivery method
    $table->enum('asset_type', ['file', 'external_link', 'email_content']);

    // File-based assets
    $table->string('file_path')->nullable();
    $table->string('file_name')->nullable();
    $table->integer('file_size')->nullable()->comment('Size in bytes');
    $table->string('file_type')->nullable()->comment('MIME type');

    // External link assets
    $table->text('external_url')->nullable();

    // Email content assets
    $table->text('email_template')->nullable();

    // Metadata
    $table->string('title');
    $table->text('description')->nullable();
    $table->integer('display_order')->default(0);
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index(['product_id', 'is_active']);
});
```

**Rationale**:
- **Mirrors `product_courses` pattern**: Pivot table linking products to deliverable assets
- **Single table for all asset types**: Avoids multiple tables (file_assets, link_assets, etc.)
- **Conditional fields**: `file_path` used only for files, `external_url` only for links, etc.
- **Flexible**: Products can have multiple assets (e.g., PDF + video + external link)
- **Display order**: Supports prioritized delivery (primary file first)

---

### Migration 3: Create Asset Deliveries Table

**File**: `database/migrations/2025_10_09_create_asset_deliveries_table.php`

```php
Schema::create('asset_deliveries', function (Blueprint $table) {
    $table->id();

    // Order tracking
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_asset_id')->constrained('product_assets')->onDelete('cascade');

    // User and environment (multi-tenant)
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('environment_id')->constrained()->onDelete('cascade');

    // Access control
    $table->uuid('download_token')->unique();
    $table->text('secure_url')->nullable()->comment('Signed URL for downloads');
    $table->timestamp('access_granted_at')->nullable();
    $table->timestamp('expires_at')->nullable();

    // Download tracking
    $table->integer('access_count')->default(0);
    $table->integer('max_access_count')->default(10)->comment('Download limit');
    $table->timestamp('last_accessed_at')->nullable();

    // Audit trail
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();

    // Status
    $table->enum('status', ['active', 'expired', 'revoked'])->default('active');

    $table->timestamps();

    $table->index(['download_token', 'status']);
    $table->index(['user_id', 'product_asset_id']);
    $table->index(['environment_id', 'status']);
});
```

**Rationale**:
- **Mirrors `enrollments` pattern**: Tracks access grants per user per asset
- **Security**: UUID tokens prevent guessing, signed URLs for time-limited access
- **Download limits**: Prevent abuse with `max_access_count`
- **Audit trail**: IP and user agent for security monitoring
- **Expiration**: Time-limited access (e.g., 30 days from purchase)
- **Multi-tenant**: Scoped to environment_id like all other tables

---

## Backend Implementation

### 1. Models

#### ProductAsset Model

**File**: `app/Models/ProductAsset.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAsset extends Model
{
    protected $fillable = [
        'product_id',
        'asset_type',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'external_url',
        'email_template',
        'title',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AssetDelivery::class);
    }

    /**
     * Get the full URL for file assets
     */
    public function getFileUrl(): ?string
    {
        if ($this->asset_type !== 'file' || !$this->file_path) {
            return null;
        }

        return \Storage::disk('s3')->url($this->file_path);
    }

    /**
     * Scope for active assets only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

---

#### AssetDelivery Model

**File**: `app/Models/AssetDelivery.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssetDelivery extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_asset_id',
        'user_id',
        'environment_id',
        'download_token',
        'secure_url',
        'access_granted_at',
        'expires_at',
        'access_count',
        'max_access_count',
        'last_accessed_at',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'access_granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'max_access_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            if (empty($delivery->download_token)) {
                $delivery->download_token = Str::uuid();
            }
            if (empty($delivery->access_granted_at)) {
                $delivery->access_granted_at = now();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productAsset(): BelongsTo
    {
        return $this->belongsTo(ProductAsset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if delivery is still valid
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (!$this->expires_at || $this->expires_at->isFuture())
            && ($this->max_access_count === null || $this->access_count < $this->max_access_count);
    }

    /**
     * Increment access count and update timestamp
     */
    public function recordAccess(string $ipAddress = null, string $userAgent = null): void
    {
        $this->increment('access_count');
        $this->update([
            'last_accessed_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Auto-expire if max access count reached
        if ($this->max_access_count && $this->access_count >= $this->max_access_count) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }
}
```

---

#### Update Product Model

**File**: `app/Models/Product.php` (add relationships)

```php
// Add to existing Product model

use Illuminate\Database\Eloquent\Relations\HasMany;

public function productAssets(): HasMany
{
    return $this->hasMany(ProductAsset::class);
}

/**
 * Scope for digital products
 */
public function scopeDigital($query)
{
    return $query->where('product_type', 'digital');
}

/**
 * Check if product requires digital fulfillment
 */
public function requiresFulfillment(): bool
{
    return $this->requires_fulfillment;
}
```

---

### 2. Extend ProcessOrderItems Listener

**File**: `app/Listeners/ProcessOrderItems.php` (add new method)

```php
// Add to existing ProcessOrderItems listener

use App\Models\ProductAsset;
use App\Models\AssetDelivery;
use App\Mail\DigitalProductDelivery;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

public function handle(OrderCompleted $event): void
{
    $order = $event->order;
    $order->status = Order::STATUS_COMPLETED;
    $order->save();

    $orderItems = DB::table('order_items')->where('order_id', $order->id)->get();

    foreach ($orderItems as $item) {
        $product = Product::find($item->product_id);

        if (!$product) {
            Log::warning("Product not found for order item {$item->id}");
            continue;
        }

        // Handle course enrollments (existing code)
        if ($product instanceof \App\Models\Product) {
            $this->processProductCourses($product, $order);
        }

        // NEW: Handle digital product fulfillment
        if ($product->requiresFulfillment()) {
            $this->processProductAssets($product, $order, $item);
        }

        // Handle subscriptions (existing commented code)
        if ($product->is_subscription) {
            //$this->processSubscription($product, $order);
        }
    }
}

/**
 * NEW METHOD: Process digital product assets (mirrors processProductCourses pattern)
 */
private function processProductAssets($product, $order, $orderItem): void
{
    $productAssets = ProductAsset::where('product_id', $product->id)
        ->where('is_active', true)
        ->orderBy('display_order')
        ->get();

    if ($productAssets->isEmpty()) {
        Log::warning("No active assets found for digital product {$product->id}");
        return;
    }

    $deliveries = [];

    foreach ($productAssets as $asset) {
        $expiresAt = now()->addDays(30); // TODO: Make configurable per product

        $delivery = AssetDelivery::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_asset_id' => $asset->id,
            'user_id' => $order->user_id,
            'environment_id' => $order->environment_id,
            'download_token' => Str::uuid(),
            'access_granted_at' => now(),
            'expires_at' => $expiresAt,
            'max_access_count' => 10, // TODO: Make configurable
            'status' => AssetDelivery::STATUS_ACTIVE,
        ]);

        // Generate signed URL for secure downloads
        if ($asset->asset_type === 'file') {
            $delivery->secure_url = \URL::temporarySignedRoute(
                'digital-products.download',
                now()->addDays(30),
                ['token' => $delivery->download_token]
            );
            $delivery->save();
        }

        $deliveries[] = $delivery;
    }

    // Send delivery email
    try {
        Mail::to($order->billing_email)->send(
            new DigitalProductDelivery($order, $product, $deliveries)
        );
    } catch (\Exception $e) {
        Log::error("Failed to send digital product delivery email for order {$order->id}: {$e->getMessage()}");
        // Don't fail the entire order processing if email fails
    }
}
```

---

### 3. Download Controller

**File**: `app/Http/Controllers/Api/DigitalProductController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetDelivery;
use App\Models\ProductAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DigitalProductController extends Controller
{
    /**
     * Get user's purchased digital products
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $environmentId = $request->header('X-Environment-Id');

        $deliveries = AssetDelivery::with(['productAsset.product', 'order'])
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deliveries->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'product_name' => $delivery->productAsset->product->name,
                    'asset_title' => $delivery->productAsset->title,
                    'asset_type' => $delivery->productAsset->asset_type,
                    'order_id' => $delivery->order_id,
                    'download_token' => $delivery->download_token,
                    'is_valid' => $delivery->isValid(),
                    'access_count' => $delivery->access_count,
                    'max_access_count' => $delivery->max_access_count,
                    'expires_at' => $delivery->expires_at,
                    'purchased_at' => $delivery->access_granted_at,
                ];
            }),
        ]);
    }

    /**
     * Download digital product file
     */
    public function download(Request $request, string $token)
    {
        $environmentId = $request->header('X-Environment-Id');

        $delivery = AssetDelivery::with('productAsset')
            ->where('download_token', $token)
            ->where('environment_id', $environmentId)
            ->firstOrFail();

        // Validate access
        if (!$delivery->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Download link has expired or reached maximum downloads',
            ], 403);
        }

        $asset = $delivery->productAsset;

        // Handle different asset types
        switch ($asset->asset_type) {
            case 'file':
                return $this->downloadFile($delivery, $asset, $request);

            case 'external_link':
                return response()->json([
                    'success' => true,
                    'redirect_url' => $asset->external_url,
                ]);

            case 'email_content':
                return response()->json([
                    'success' => true,
                    'message' => 'Content has been sent to your email',
                ]);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid asset type',
                ], 400);
        }
    }

    /**
     * Handle file downloads
     */
    private function downloadFile(AssetDelivery $delivery, ProductAsset $asset, Request $request)
    {
        if (!Storage::disk('s3')->exists($asset->file_path)) {
            Log::error("File not found for asset {$asset->id}: {$asset->file_path}");
            return response()->json([
                'success' => false,
                'message' => 'File not found. Please contact support.',
            ], 404);
        }

        // Record access
        $delivery->recordAccess(
            $request->ip(),
            $request->userAgent()
        );

        // Stream file from S3
        return response()->stream(
            function () use ($asset) {
                $stream = Storage::disk('s3')->readStream($asset->file_path);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $asset->file_type,
                'Content-Disposition' => 'attachment; filename="' . $asset->file_name . '"',
                'Content-Length' => $asset->file_size,
            ]
        );
    }
}
```

---

### 4. File Upload Endpoint for Instructors

**File**: `app/Http/Controllers/Api/InstructorProductController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class InstructorProductController extends Controller
{
    /**
     * Upload digital asset to product
     */
    public function uploadAsset(Request $request, Product $product)
    {
        // Authorization: ensure user owns the product
        $this->authorize('update', $product);

        $validator = Validator::make($request->all(), [
            'asset_type' => 'required|in:file,external_link,email_content',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',

            // File upload (for asset_type=file)
            'file' => 'required_if:asset_type,file|file|max:512000', // 500MB max

            // External link (for asset_type=external_link)
            'external_url' => 'required_if:asset_type,external_link|url',

            // Email content (for asset_type=email_content)
            'email_template' => 'required_if:asset_type,email_content|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $assetData = [
            'product_id' => $product->id,
            'asset_type' => $request->asset_type,
            'title' => $request->title,
            'description' => $request->description,
            'display_order' => $request->display_order ?? 0,
        ];

        // Handle file uploads
        if ($request->asset_type === 'file' && $request->hasFile('file')) {
            $file = $request->file('file');

            // Generate unique file path
            $path = "products/{$product->id}/assets/" . uniqid() . '_' . $file->getClientOriginalName();

            // Upload to S3
            Storage::disk('s3')->put($path, file_get_contents($file), 'private');

            $assetData['file_path'] = $path;
            $assetData['file_name'] = $file->getClientOriginalName();
            $assetData['file_size'] = $file->getSize();
            $assetData['file_type'] = $file->getMimeType();
        }

        // Handle external links
        if ($request->asset_type === 'external_link') {
            $assetData['external_url'] = $request->external_url;
        }

        // Handle email content
        if ($request->asset_type === 'email_content') {
            $assetData['email_template'] = $request->email_template;
        }

        $asset = ProductAsset::create($assetData);

        // Update product to require fulfillment
        $product->update(['requires_fulfillment' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Asset uploaded successfully',
            'data' => $asset,
        ]);
    }

    /**
     * Delete digital asset
     */
    public function deleteAsset(Request $request, Product $product, ProductAsset $asset)
    {
        $this->authorize('update', $product);

        // Delete file from S3 if it's a file asset
        if ($asset->asset_type === 'file' && $asset->file_path) {
            Storage::disk('s3')->delete($asset->file_path);
        }

        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset deleted successfully',
        ]);
    }
}
```

---

### 5. Email Mailable

**File**: `app/Mail/DigitalProductDelivery.php`

```php
<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DigitalProductDelivery extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $product;
    public $deliveries;

    public function __construct(Order $order, Product $product, $deliveries)
    {
        $this->order = $order;
        $this->product = $product;
        $this->deliveries = $deliveries;
    }

    public function build()
    {
        return $this->subject("Your digital product: {$this->product->name}")
                    ->view('emails.digital-product-delivery');
    }
}
```

**File**: `resources/views/emails/digital-product-delivery.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .download-link { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Digital Product is Ready!</h1>
        </div>

        <div class="content">
            <h2>Thank you for your purchase!</h2>
            <p>Hi {{ $order->customer_name }},</p>
            <p>Your order #{{ $order->order_number }} has been completed. You can now access your digital product:</p>

            <h3>{{ $product->name }}</h3>

            @foreach($deliveries as $delivery)
                @php
                    $asset = $delivery->productAsset;
                @endphp

                <div style="margin: 20px 0; padding: 15px; background: white; border-left: 4px solid #4CAF50;">
                    <h4>{{ $asset->title }}</h4>
                    @if($asset->description)
                        <p>{{ $asset->description }}</p>
                    @endif

                    @if($asset->asset_type === 'file')
                        <a href="{{ route('digital-products.download', ['token' => $delivery->download_token]) }}" class="download-link">
                            Download Now
                        </a>
                        <p><small>Downloads allowed: {{ $delivery->max_access_count }} | Expires: {{ $delivery->expires_at->format('F j, Y') }}</small></p>
                    @elseif($asset->asset_type === 'external_link')
                        <a href="{{ $asset->external_url }}" class="download-link">
                            Access Content
                        </a>
                    @elseif($asset->asset_type === 'email_content')
                        <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                            {!! nl2br(e($asset->email_template)) !!}
                        </div>
                    @endif
                </div>
            @endforeach

            <p><strong>Important:</strong></p>
            <ul>
                <li>Download links expire in 30 days</li>
                <li>You can access your purchases anytime from your account dashboard</li>
                <li>If you have any issues, please contact support</li>
            </ul>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
```

---

### 6. Routes

**File**: `routes/api.php` (add routes)

```php
// Digital Products (Authenticated)
Route::middleware(['auth:sanctum', 'environment.scope'])->group(function () {
    // Customer routes
    Route::get('/digital-products', [DigitalProductController::class, 'index']);
    Route::get('/digital-products/download/{token}', [DigitalProductController::class, 'download'])
        ->name('digital-products.download');

    // Instructor routes
    Route::post('/instructor/products/{product}/assets', [InstructorProductController::class, 'uploadAsset']);
    Route::delete('/instructor/products/{product}/assets/{asset}', [InstructorProductController::class, 'deleteAsset']);
});
```

---

## Frontend Implementation

### 1. API Service Extensions

**File**: `/lib/services/storefront-service.ts` (add methods)

```typescript
// Add to existing StorefrontService

/**
 * Get user's purchased digital products
 */
export async function getDigitalProducts(): Promise<any> {
  return apiRequest('GET', '/digital-products');
}

/**
 * Get download URL for a digital product
 */
export function getDownloadUrl(token: string): string {
  return `${process.env.NEXT_PUBLIC_API_URL}/digital-products/download/${token}`;
}
```

---

**File**: `/lib/instructor-product-api.ts` (new file)

```typescript
import { apiRequest } from './api';

export interface ProductAsset {
  id: number;
  asset_type: 'file' | 'external_link' | 'email_content';
  title: string;
  description?: string;
  display_order: number;
  file_name?: string;
  file_size?: number;
  external_url?: string;
  email_template?: string;
}

/**
 * Upload digital asset to product
 */
export async function uploadProductAsset(
  productId: number,
  data: {
    asset_type: 'file' | 'external_link' | 'email_content';
    title: string;
    description?: string;
    file?: File;
    external_url?: string;
    email_template?: string;
  }
): Promise<any> {
  const formData = new FormData();
  formData.append('asset_type', data.asset_type);
  formData.append('title', data.title);
  if (data.description) formData.append('description', data.description);
  if (data.file) formData.append('file', data.file);
  if (data.external_url) formData.append('external_url', data.external_url);
  if (data.email_template) formData.append('email_template', data.email_template);

  return apiRequest('POST', `/instructor/products/${productId}/assets`, {
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
}

/**
 * Delete product asset
 */
export async function deleteProductAsset(productId: number, assetId: number): Promise<any> {
  return apiRequest('DELETE', `/instructor/products/${productId}/assets/${assetId}`);
}
```

---

### 2. Customer Download Page

**File**: `/app/learners/digital-products/page.tsx` (new file)

```typescript
"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Download, ExternalLink, Mail, AlertCircle } from "lucide-react"
import { toast } from "sonner"
import { getDigitalProducts, getDownloadUrl } from "@/lib/services/storefront-service"
import { Skeleton } from "@/components/ui/skeleton"
import { Alert, AlertDescription } from "@/components/ui/alert"

export default function DigitalProductsPage() {
  const [products, setProducts] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchProducts()
  }, [])

  const fetchProducts = async () => {
    try {
      setLoading(true)
      const response = await getDigitalProducts()
      setProducts(response.data || [])
    } catch (error: any) {
      console.error("Error fetching digital products:", error)
      toast.error("Failed to load digital products")
    } finally {
      setLoading(false)
    }
  }

  const handleDownload = (token: string) => {
    const url = getDownloadUrl(token)
    window.open(url, '_blank')
  }

  const getAssetIcon = (type: string) => {
    switch (type) {
      case 'file':
        return <Download className="h-5 w-5" />
      case 'external_link':
        return <ExternalLink className="h-5 w-5" />
      case 'email_content':
        return <Mail className="h-5 w-5" />
      default:
        return null
    }
  }

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-3xl font-bold">My Digital Products</h1>
        <p className="text-muted-foreground">Access your purchased digital content</p>
      </div>

      {loading ? (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-6 w-64" />
                <Skeleton className="h-4 w-48" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-10 w-32" />
              </CardContent>
            </Card>
          ))}
        </div>
      ) : products.length === 0 ? (
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            You haven't purchased any digital products yet.
          </AlertDescription>
        </Alert>
      ) : (
        <div className="space-y-4">
          {products.map((product) => (
            <Card key={product.id}>
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div>
                    <CardTitle className="flex items-center gap-2">
                      {getAssetIcon(product.asset_type)}
                      {product.product_name}
                    </CardTitle>
                    <CardDescription>{product.asset_title}</CardDescription>
                  </div>
                  <Badge variant={product.is_valid ? "default" : "secondary"}>
                    {product.is_valid ? "Active" : "Expired"}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                <div className="flex items-center justify-between">
                  <div className="text-sm text-muted-foreground">
                    <p>Downloads: {product.access_count} / {product.max_access_count}</p>
                    <p>Expires: {new Date(product.expires_at).toLocaleDateString()}</p>
                    <p>Purchased: {new Date(product.purchased_at).toLocaleDateString()}</p>
                  </div>
                  {product.is_valid && (
                    <Button onClick={() => handleDownload(product.download_token)}>
                      {product.asset_type === 'file' && (
                        <>
                          <Download className="mr-2 h-4 w-4" />
                          Download
                        </>
                      )}
                      {product.asset_type === 'external_link' && (
                        <>
                          <ExternalLink className="mr-2 h-4 w-4" />
                          Access Content
                        </>
                      )}
                      {product.asset_type === 'email_content' && (
                        <>
                          <Mail className="mr-2 h-4 w-4" />
                          View Content
                        </>
                      )}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
```

---

### 3. Instructor Asset Upload Form

**File**: `/components/instructor/ProductAssetUpload.tsx` (new component)

```typescript
"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Upload, X } from "lucide-react"
import { toast } from "sonner"
import { uploadProductAsset } from "@/lib/instructor-product-api"
import { FileUpload } from "@/components/ui/file-upload"

interface ProductAssetUploadProps {
  productId: number
  onUploadSuccess?: () => void
}

export function ProductAssetUpload({ productId, onUploadSuccess }: ProductAssetUploadProps) {
  const [assetType, setAssetType] = useState<'file' | 'external_link' | 'email_content'>('file')
  const [title, setTitle] = useState("")
  const [description, setDescription] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [externalUrl, setExternalUrl] = useState("")
  const [emailTemplate, setEmailTemplate] = useState("")
  const [uploading, setUploading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!title) {
      toast.error("Please enter a title")
      return
    }

    if (assetType === 'file' && !file) {
      toast.error("Please select a file")
      return
    }

    if (assetType === 'external_link' && !externalUrl) {
      toast.error("Please enter a URL")
      return
    }

    if (assetType === 'email_content' && !emailTemplate) {
      toast.error("Please enter email content")
      return
    }

    try {
      setUploading(true)
      await uploadProductAsset(productId, {
        asset_type: assetType,
        title,
        description,
        file: file || undefined,
        external_url: externalUrl || undefined,
        email_template: emailTemplate || undefined,
      })

      toast.success("Asset uploaded successfully")

      // Reset form
      setTitle("")
      setDescription("")
      setFile(null)
      setExternalUrl("")
      setEmailTemplate("")

      onUploadSuccess?.()
    } catch (error: any) {
      console.error("Upload error:", error)
      toast.error("Failed to upload asset")
    } finally {
      setUploading(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Upload Digital Asset</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Asset Type */}
          <div className="space-y-2">
            <Label>Asset Type</Label>
            <Select value={assetType} onValueChange={(value: any) => setAssetType(value)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="file">Downloadable File</SelectItem>
                <SelectItem value="external_link">External Link (Google Drive, etc.)</SelectItem>
                <SelectItem value="email_content">Email Content</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Title */}
          <div className="space-y-2">
            <Label>Title</Label>
            <Input
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="e.g., Course Workbook PDF"
            />
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label>Description (optional)</Label>
            <Textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Brief description of this asset"
              rows={3}
            />
          </div>

          {/* Conditional Fields */}
          {assetType === 'file' && (
            <div className="space-y-2">
              <Label>File</Label>
              <FileUpload
                accept=".pdf,.epub,.zip,.mp4,.mp3"
                maxSize={500 * 1024 * 1024} // 500MB
                onFileSelect={(selectedFile) => setFile(selectedFile)}
                onFileRemove={() => setFile(null)}
              />
              <p className="text-xs text-muted-foreground">
                Supported: PDF, EPUB, ZIP, MP4, MP3 (max 500MB)
              </p>
            </div>
          )}

          {assetType === 'external_link' && (
            <div className="space-y-2">
              <Label>External URL</Label>
              <Input
                type="url"
                value={externalUrl}
                onChange={(e) => setExternalUrl(e.target.value)}
                placeholder="https://drive.google.com/..."
              />
              <p className="text-xs text-muted-foreground">
                Link to Google Drive, Dropbox, or any public URL
              </p>
            </div>
          )}

          {assetType === 'email_content' && (
            <div className="space-y-2">
              <Label>Email Content</Label>
              <Textarea
                value={emailTemplate}
                onChange={(e) => setEmailTemplate(e.target.value)}
                placeholder="This content will be sent via email to customers..."
                rows={8}
              />
              <p className="text-xs text-muted-foreground">
                This content will be included in the delivery email
              </p>
            </div>
          )}

          <Button type="submit" disabled={uploading} className="w-full">
            <Upload className="mr-2 h-4 w-4" />
            {uploading ? "Uploading..." : "Upload Asset"}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
```

---

### 4. Update Order Details Page

**File**: `/app/learners/orders/[id]/page.tsx` (add digital products section)

```typescript
// Add this section after existing order items display

{/* Digital Products Section */}
{orderData.order_items?.some((item: any) => item.product?.product_type === 'digital') && (
  <Card>
    <CardHeader>
      <CardTitle className="flex items-center gap-2">
        <Download className="h-5 w-5" />
        Digital Products
      </CardTitle>
      <CardDescription>
        Download your digital products
      </CardDescription>
    </CardHeader>
    <CardContent>
      <div className="space-y-3">
        {orderData.order_items
          .filter((item: any) => item.product?.product_type === 'digital')
          .map((item: any) => (
            <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-md">
              <div>
                <p className="font-medium">{item.product_name}</p>
                <p className="text-sm text-muted-foreground">Ready to download</p>
              </div>
              <Button asChild>
                <Link href="/learners/digital-products">
                  View Downloads
                </Link>
              </Button>
            </div>
          ))}
      </div>
    </CardContent>
  </Card>
)}
```

---

## Acceptance Criteria

### Phase 1: Foundation (Week 1)

- [ ] **Database migrations** created and executed successfully
  - `products` table has `product_type` and `requires_fulfillment` columns
  - `product_assets` table exists with all fields
  - `asset_deliveries` table exists with all fields

- [ ] **Models** created with relationships
  - `ProductAsset` model with `product()`, `deliveries()` relationships
  - `AssetDelivery` model with `order()`, `productAsset()`, `user()` relationships
  - `Product` model has `productAssets()` relationship

- [ ] **Seeds** created for testing
  - Sample digital products with various asset types
  - Test orders with digital products
  - Asset deliveries with valid tokens

### Phase 2: File Uploads (Week 2)

- [ ] **Backend upload endpoint** working
  - Instructors can upload files up to 500MB
  - Files stored in S3 with unique paths
  - Validation for file types (PDF, EPUB, ZIP, MP4, MP3)
  - Product automatically marked as `requires_fulfillment`

- [ ] **Frontend upload UI** implemented
  - Reusable `ProductAssetUpload` component
  - File selection with drag/drop
  - Upload progress indicator
  - Success/error notifications

- [ ] **Asset management** working
  - Instructors can view uploaded assets
  - Instructors can delete assets
  - Files removed from S3 when assets deleted

### Phase 3: Order Fulfillment (Week 2)

- [ ] **ProcessOrderItems listener** extended
  - `processProductAssets()` method implemented
  - Creates `AssetDelivery` records for each asset
  - Generates UUID tokens
  - Creates signed URLs for file downloads
  - Handles errors gracefully (doesn't break order processing)

- [ ] **Email delivery** working
  - `DigitalProductDelivery` mailable sends after order completion
  - Email includes download links for file assets
  - Email includes external links for link assets
  - Email includes content for email_content assets
  - Email template is mobile-responsive

### Phase 4: Download Access (Week 3)

- [ ] **Download controller** implemented
  - Token-based access control
  - Validates expiration and download limits
  - Records access (IP, user agent, timestamp)
  - Streams files from S3 efficiently
  - Returns proper Content-Type and Content-Disposition headers

- [ ] **Customer downloads page** created
  - Shows all purchased digital products
  - Displays download limits and expiration dates
  - Shows access count per product
  - Download buttons work correctly
  - Expired products clearly marked

- [ ] **Order details enhancement**
  - Order details page shows digital products
  - Link to downloads page for digital orders
  - Clear indication of product type (course vs digital)

### Phase 5: External Links Support (Week 3)

- [ ] **External link assets** working
  - Instructors can add Google Drive links
  - Instructors can add Dropbox links
  - Instructors can add any public URL
  - Links displayed in delivery email
  - Links accessible from downloads page

- [ ] **Email content assets** working
  - Instructors can write email content
  - Content sent in delivery email
  - Content accessible from downloads page
  - Supports plain text and formatted content

### Phase 6: Security & Polish (Week 4)

- [ ] **Security measures** implemented
  - Download rate limiting (max 5 per minute)
  - IP address logging for audit trail
  - User agent tracking
  - File type validation (prevent malicious files)
  - S3 bucket configured with private ACL

- [ ] **Monitoring** setup
  - Failed downloads logged
  - Email delivery failures logged
  - S3 upload errors logged
  - Admin dashboard shows delivery stats

- [ ] **Testing** complete
  - Unit tests for models
  - Integration tests for order fulfillment
  - E2E tests for download flow
  - Load testing for concurrent downloads

- [ ] **Documentation** complete
  - API endpoints documented
  - Instructor guide for uploading assets
  - Customer guide for accessing downloads
  - Admin guide for managing digital products

---

## Testing Scenarios

### Backend Testing

#### Unit Tests

**File**: `tests/Unit/Models/ProductAssetTest.php`

```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductAssetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_product()
    {
        $product = Product::factory()->create();
        $asset = ProductAsset::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $asset->product);
        $this->assertEquals($product->id, $asset->product->id);
    }

    /** @test */
    public function it_generates_file_url_for_file_assets()
    {
        $asset = ProductAsset::factory()->create([
            'asset_type' => 'file',
            'file_path' => 'products/1/test.pdf'
        ]);

        $url = $asset->getFileUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('test.pdf', $url);
    }

    /** @test */
    public function it_returns_null_for_non_file_assets()
    {
        $asset = ProductAsset::factory()->create([
            'asset_type' => 'external_link',
            'external_url' => 'https://example.com'
        ]);

        $this->assertNull($asset->getFileUrl());
    }

    /** @test */
    public function active_scope_filters_inactive_assets()
    {
        ProductAsset::factory()->create(['is_active' => true]);
        ProductAsset::factory()->create(['is_active' => false]);

        $activeAssets = ProductAsset::active()->get();
        $this->assertCount(1, $activeAssets);
    }
}
```

---

**File**: `tests/Unit/Models/AssetDeliveryTest.php`

```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\AssetDelivery;
use App\Models\ProductAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_auto_generates_uuid_token_on_creation()
    {
        $delivery = AssetDelivery::factory()->create();

        $this->assertNotNull($delivery->download_token);
        $this->assertEquals(36, strlen($delivery->download_token)); // UUID format
    }

    /** @test */
    public function is_valid_returns_true_for_active_unexpired_delivery()
    {
        $delivery = AssetDelivery::factory()->create([
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'access_count' => 2,
            'max_access_count' => 10
        ]);

        $this->assertTrue($delivery->isValid());
    }

    /** @test */
    public function is_valid_returns_false_for_expired_delivery()
    {
        $delivery = AssetDelivery::factory()->create([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($delivery->isValid());
    }

    /** @test */
    public function is_valid_returns_false_when_max_downloads_reached()
    {
        $delivery = AssetDelivery::factory()->create([
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'access_count' => 10,
            'max_access_count' => 10
        ]);

        $this->assertFalse($delivery->isValid());
    }

    /** @test */
    public function record_access_increments_count_and_updates_timestamp()
    {
        $delivery = AssetDelivery::factory()->create(['access_count' => 0]);

        $delivery->recordAccess('127.0.0.1', 'Mozilla/5.0');

        $this->assertEquals(1, $delivery->access_count);
        $this->assertNotNull($delivery->last_accessed_at);
        $this->assertEquals('127.0.0.1', $delivery->ip_address);
    }

    /** @test */
    public function record_access_auto_expires_when_max_reached()
    {
        $delivery = AssetDelivery::factory()->create([
            'access_count' => 9,
            'max_access_count' => 10,
            'status' => 'active'
        ]);

        $delivery->recordAccess();

        $this->assertEquals(10, $delivery->access_count);
        $this->assertEquals('expired', $delivery->status);
    }
}
```

---

#### Integration Tests

**File**: `tests/Feature/DigitalProductFulfillmentTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductAsset;
use App\Models\Order;
use App\Events\OrderCompleted;
use App\Models\AssetDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\DigitalProductDelivery;

class DigitalProductFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function order_completion_creates_asset_deliveries()
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'product_type' => 'digital',
            'requires_fulfillment' => true
        ]);

        $asset = ProductAsset::factory()->create([
            'product_id' => $product->id,
            'asset_type' => 'file'
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        $orderItem = DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Trigger order completion
        event(new OrderCompleted($order));

        // Assert delivery created
        $this->assertDatabaseHas('asset_deliveries', [
            'order_id' => $order->id,
            'product_asset_id' => $asset->id,
            'user_id' => $user->id,
            'status' => 'active'
        ]);

        // Assert email sent
        Mail::assertSent(DigitalProductDelivery::class);
    }

    /** @test */
    public function multiple_assets_create_multiple_deliveries()
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'product_type' => 'digital',
            'requires_fulfillment' => true
        ]);

        // Create 3 assets
        ProductAsset::factory()->count(3)->create([
            'product_id' => $product->id
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        event(new OrderCompleted($order));

        // Assert 3 deliveries created
        $this->assertCount(3, AssetDelivery::where('order_id', $order->id)->get());
    }
}
```

---

**File**: `tests/Feature/DigitalProductDownloadTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AssetDelivery;
use App\Models\ProductAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class DigitalProductDownloadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_download_valid_asset()
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $asset = ProductAsset::factory()->create([
            'asset_type' => 'file',
            'file_path' => 'test.pdf',
            'file_name' => 'test.pdf'
        ]);

        Storage::disk('s3')->put($asset->file_path, 'fake content');

        $delivery = AssetDelivery::factory()->create([
            'user_id' => $user->id,
            'product_asset_id' => $asset->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'access_count' => 0,
            'max_access_count' => 10
        ]);

        $response = $this->actingAs($user)
                         ->getJson("/api/digital-products/download/{$delivery->download_token}");

        $response->assertOk();

        // Assert access recorded
        $this->assertEquals(1, $delivery->fresh()->access_count);
    }

    /** @test */
    public function expired_delivery_cannot_be_downloaded()
    {
        $user = User::factory()->create();
        $delivery = AssetDelivery::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->subDay()
        ]);

        $response = $this->actingAs($user)
                         ->getJson("/api/digital-products/download/{$delivery->download_token}");

        $response->assertStatus(403);
    }

    /** @test */
    public function max_downloads_prevents_further_access()
    {
        $user = User::factory()->create();
        $delivery = AssetDelivery::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'access_count' => 10,
            'max_access_count' => 10
        ]);

        $response = $this->actingAs($user)
                         ->getJson("/api/digital-products/download/{$delivery->download_token}");

        $response->assertStatus(403);
    }
}
```

---

### Frontend Testing

**Manual Test Scenarios**:

1. **Instructor File Upload**
   - Navigate to product edit page
   - Click "Add Digital Asset"
   - Select file type
   - Upload PDF file (test file > 500MB for validation)
   - Verify success notification
   - Verify file appears in asset list

2. **Customer Purchase Flow**
   - Add digital product to cart
   - Complete checkout with test payment
   - Verify order completion email received
   - Verify download links in email work
   - Click "View Downloads" from order details
   - Download file and verify content

3. **Download Limits**
   - Download a file 10 times (max limit)
   - Verify 11th download attempt shows error
   - Verify asset status shows "Expired"

4. **External Links**
   - Create product with Google Drive link
   - Purchase product
   - Verify email contains clickable link
   - Verify link opens Google Drive in new tab

5. **Mobile Responsiveness**
   - Test file upload on mobile device
   - Test downloads page on mobile
   - Verify email renders correctly on mobile

---

## Security Considerations

### 1. File Security
- **S3 ACL**: All uploaded files stored with `private` ACL
- **Signed URLs**: Time-limited URLs (30 days) prevent direct S3 access
- **File Validation**: Server-side MIME type checking
- **Malware Scanning**: (TODO Phase 6) Integrate ClamAV or similar

### 2. Access Control
- **UUID Tokens**: Unpredictable download tokens (not sequential IDs)
- **Environment Scoping**: Multi-tenant isolation via `environment_id`
- **Download Limits**: Configurable `max_access_count` prevents abuse
- **Expiration**: Time-limited access with automatic expiration

### 3. Rate Limiting
- **Download Endpoint**: Max 5 downloads per minute per IP
- **Upload Endpoint**: Max 10 uploads per hour per instructor
- **API Throttling**: Laravel's throttle middleware on all routes

### 4. Audit Trail
- **Access Logging**: Every download records IP, user agent, timestamp
- **Failed Attempts**: Log all 403/404 download attempts
- **Admin Dashboard**: View suspicious activity patterns

### 5. Data Privacy
- **GDPR Compliance**: User can request deletion of delivery records
- **Data Retention**: Asset deliveries deleted after 1 year of order date
- **PII Protection**: No sensitive data stored in asset metadata

---

## Performance Considerations

### 1. File Streaming
- **Chunked Downloads**: Stream files from S3 using Laravel's `response()->stream()`
- **CDN Integration**: (Future) Use CloudFront for S3 distribution
- **Bandwidth Optimization**: Compress files before upload (instructor responsibility)

### 2. Database Optimization
- **Indexes**:
  - `product_assets(product_id, is_active)`
  - `asset_deliveries(download_token, status)`
  - `asset_deliveries(user_id, product_asset_id)`
- **Query Optimization**: Use eager loading (`with()`) for deliveries page
- **Pagination**: Paginate downloads page for users with many purchases

### 3. Queue Processing
- **Email Queue**: Use `queue` connection for delivery emails
- **Failed Jobs**: Retry failed email deliveries up to 3 times
- **Horizon**: Monitor queue health for order fulfillment

### 4. Caching
- **User Downloads**: Cache user's digital products list for 5 minutes
- **Product Assets**: Cache product assets query for 10 minutes
- **Expiration Check**: Cache `isValid()` result for 1 minute

---

## Monitoring & Observability

### Metrics to Track
- **Delivery Success Rate**: % of orders with successful digital delivery
- **Email Delivery Rate**: % of delivery emails sent vs failed
- **Download Completion Rate**: % of started downloads that complete
- **Average Download Size**: Monitor S3 bandwidth costs
- **Failed Downloads**: Count of 403/404 errors per day
- **Storage Usage**: Total S3 storage per environment

### Alerts
- Delivery email failure rate > 5%
- Download endpoint error rate > 2%
- S3 upload failures > 10 per hour
- Average download time > 30 seconds

---

## Phased Implementation Timeline

| Phase | Duration | Tasks | Dependencies |
|-------|----------|-------|--------------|
| **Phase 1: Foundation** | Week 1 | Migrations, Models, Seeds | None |
| **Phase 2: File Uploads** | Week 2 | Backend upload, S3, Frontend UI | Phase 1 |
| **Phase 3: Order Fulfillment** | Week 2 | Extend ProcessOrderItems, Email | Phase 1 |
| **Phase 4: Download Access** | Week 3 | Download controller, Customer page | Phase 2, 3 |
| **Phase 5: External Links** | Week 3 | Link/email asset support | Phase 3 |
| **Phase 6: Security & Polish** | Week 4 | Validation, monitoring, testing | Phase 4, 5 |

**Total Estimated Time**: 5 weeks (with 1 week buffer)

---

## Open Questions / Future Enhancements

### Open Questions
1. **Download Limits**: Should limits be configurable per product or per asset?
2. **Expiration**: Should expiration be configurable per product? (30 days default)
3. **S3 Configuration**: Which AWS account/bucket for production?
4. **Email Templates**: Should instructors customize delivery email templates?
5. **Refunds**: How to handle refunds for digital products? Revoke access?

### Future Enhancements (Not in MVP)
- **Watermarking**: Add user-specific watermarks to PDF files
- **DRM Protection**: Integrate DRM for video/audio files
- **Analytics**: Track which assets are downloaded most
- **Instructor Dashboard**: Show download stats per product
- **Bundles**: Products with both courses AND digital assets
- **License Keys**: Generate software license keys for downloads
- **Conditional Access**: Time-delayed releases (unlock after 7 days)
- **Version Control**: Allow instructors to upload new versions of files

---

## Dependencies

### Backend
- Laravel 10+
- AWS S3 or compatible storage
- Queue worker (for email delivery)
- Mailtrap/SendGrid/SES for transactional emails

### Frontend
- Next.js 14+
- shadcn/ui components
- sonner for notifications
- Existing API service layer

### Third-Party Services
- AWS S3 (file storage)
- Email service (SendGrid/SES)
- (Future) CloudFront CDN
- (Future) ClamAV or VirusTotal for malware scanning

---

## Story Metadata

- **Story ID**: STORY-008
- **Story Type**: Feature (Brownfield)
- **Complexity**: High
- **Estimated Effort**: 5 weeks (200 hours)
- **Priority**: High
- **Dependencies**: Payment Gateway Integration (Stories 1-7 completed)
- **Stakeholders**: Instructors, Customers, Platform Admin
- **Success Metrics**:
  - 30% of products created as digital within 3 months
  - 90%+ successful deliveries
  - 25% increase in instructor signups

---

## Approval & Sign-off

**Product Owner**: _____________________
**Technical Lead**: _____________________
**Date**: _____________________

---

## Notes

- This story follows the existing `product_courses` → `enrollments` pattern for architectural consistency
- All database changes are backward compatible (existing products default to `product_type='course'`)
- S3 credentials must be configured before Phase 2 deployment
- Email templates need design approval before Phase 3 deployment
- Security audit recommended after Phase 6 completion

---

**End of Story Document**
