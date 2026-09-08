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
        Schema::create('marketing_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->foreignId('sales_form_submission_id')
                ->nullable()
                ->constrained('sales_form_submissions')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24);
            $table->string('status', 16);
            $table->string('source', 64);
            $table->string('terms_version', 64);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['environment_id', 'sales_form_submission_id', 'channel', 'created_at'],
                'marketing_consent_submission_state_index'
            );
            $table->index(
                ['environment_id', 'user_id', 'channel', 'created_at'],
                'marketing_consent_user_state_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_consents');
    }
};
