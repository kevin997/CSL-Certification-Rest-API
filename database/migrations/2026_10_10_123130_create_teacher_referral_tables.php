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
        Schema::create('teacher_referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->unique()->constrained('users');
            $table->string('code', 32)->unique();
            $table->string('reward_type', 16);
            $table->decimal('reward_value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('teacher_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_referral_code_id')->constrained('teacher_referral_codes');
            $table->foreignId('referrer_id')->constrained('users');
            $table->foreignId('referred_user_id')->constrained('users');
            $table->foreignId('referred_environment_id')->unique()->constrained('environments');
            $table->foreignId('qualifying_checkout_id')->nullable()->unique()->constrained('licence_checkouts');
            $table->foreignId('qualifying_transaction_id')->nullable()->unique()->constrained('transactions');
            $table->string('status', 16)->default('pending');
            $table->string('reward_type', 16);
            $table->decimal('reward_value', 10, 2);
            $table->decimal('reward_amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['referrer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_referrals');
        Schema::dropIfExists('teacher_referral_codes');
    }
};
