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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sales_form_submission_id')
                ->nullable()
                ->after('referral_id')
                ->constrained('sales_form_submissions')
                ->nullOnDelete();
            $table->index('sales_form_submission_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            // Provisional access: limited blocks/activities until payment is confirmed.
            $table->boolean('is_provisional')->default(false)->after('enrolled_by');
            $table->foreignId('sales_form_id')
                ->nullable()
                ->after('is_provisional')
                ->constrained('sales_forms')
                ->nullOnDelete();
            $table->index('is_provisional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_form_submission_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_form_id');
            $table->dropColumn('is_provisional');
        });
    }
};
