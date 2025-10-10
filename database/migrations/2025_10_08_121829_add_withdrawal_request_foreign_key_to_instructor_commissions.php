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
        Schema::table('instructor_commissions', function (Blueprint $table) {
            $table->foreign('withdrawal_request_id')
                  ->references('id')
                  ->on('withdrawal_requests')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_commissions', function (Blueprint $table) {
            $table->dropForeign(['withdrawal_request_id']);
        });
    }
};
