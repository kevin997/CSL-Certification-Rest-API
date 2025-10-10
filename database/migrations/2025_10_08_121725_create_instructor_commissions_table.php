<?php

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
        Schema::create('instructor_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('gross_amount', 10, 2)->nullable(false);
            $table->decimal('commission_rate', 5, 4)->nullable(false);
            $table->decimal('commission_amount', 10, 2)->nullable(false);
            $table->decimal('net_amount', 10, 2)->nullable(false);
            $table->string('currency', 3)->default('XAF');
            $table->enum('status', ['pending', 'approved', 'paid', 'disputed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->unsignedBigInteger('withdrawal_request_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['environment_id', 'status']);
            $table->index('created_at');
            $table->index('status');
            $table->index('transaction_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_commissions');
    }
};
