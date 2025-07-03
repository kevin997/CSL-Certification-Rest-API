<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('environment_id');
            $table->string('invoice_number')->unique();
            $table->date('month');
            $table->decimal('total_fee_amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('due_date');
            $table->string('payment_link')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->integer('transaction_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('environment_id')->references('id')->on('environments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};