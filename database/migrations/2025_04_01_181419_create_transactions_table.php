<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('transactions')) {
            echo "Table 'transactions' already exists, skipping...\n";
        } else {
            Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id')->unique();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->foreignId('payment_gateway_setting_id')->nullable()->constrained('payment_gateway_settings');
            $table->string('order_id')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency')->default('USD');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, refunded, partially_refunded
            $table->string('payment_method')->nullable(); // credit_card, paypal, bank_transfer, mobile_money, etc.
            $table->string('payment_method_details')->nullable(); // card type, last 4 digits, etc.
            $table->string('gateway_transaction_id')->nullable(); // ID from the payment gateway
            $table->string('gateway_status')->nullable(); // Status from the payment gateway
            $table->json('gateway_response')->nullable(); // Full response from the payment gateway
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for faster queries
            $table->index('transaction_id');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
