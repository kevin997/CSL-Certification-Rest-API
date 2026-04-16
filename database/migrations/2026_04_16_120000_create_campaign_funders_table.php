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
        Schema::create('campaign_funders', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->index();
            $table->string('whatsapp_number');
            $table->string('locale', 8)->default('fr');
            $table->string('tier_id')->nullable()->index();
            $table->string('tier_name')->nullable();
            $table->unsignedInteger('amount_xaf');
            $table->string('currency', 3)->default('XAF');
            $table->text('note')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->string('source')->default('website');
            $table->string('payment_provider')->default('taramoney');
            $table->string('payment_status')->default('pending')->index();
            $table->string('tara_payment_id')->nullable()->index();
            $table->string('tara_collection_id')->nullable()->index();
            $table->string('tara_transaction_code')->nullable();
            $table->string('tara_mobile_operator')->nullable();
            $table->string('tara_phone_number')->nullable();
            $table->string('tara_customer_name')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('payment_links')->nullable();
            $table->json('meta')->nullable();
            $table->json('last_webhook_payload')->nullable();
            $table->timestamps();

            $table->index(['payment_provider', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_funders');
    }
};
