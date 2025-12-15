<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (MigrationHelper::tableExists('product_subscription_reminders')) {
            echo "Table 'product_subscription_reminders' already exists, skipping...\n";
        } else {
            Schema::create('product_subscription_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_subscription_id')->constrained('product_subscriptions')->onDelete('cascade');
                $table->string('reminder_key');
                $table->timestamp('sent_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['product_subscription_id', 'reminder_key'], 'psr_sub_id_rem_key_unique');
                $table->index(['sent_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subscription_reminders');
    }
};
