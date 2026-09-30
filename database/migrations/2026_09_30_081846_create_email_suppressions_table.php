<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Addresses that must never be mailed again. Platform-wide rather than per
     * environment: a mailbox that does not exist does not exist for any tenant,
     * and every tenant sends through the same reputation.
     */
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason');
            $table->string('status_code', 16)->nullable();
            $table->text('diagnostic')->nullable();
            $table->string('source');
            $table->timestamp('suppressed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
