<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user agent is not a bounded value, and the audit log must accept whatever
 * the client sends.
 *
 * audit_logs.user_agent was varchar(191). Facebook and Instagram in-app
 * browsers send 218-273 characters, so the insert failed with SQLSTATE 22001.
 * Because AuditedPaymentGateway writes the audit row inside the payment path,
 * that failure surfaced as "Payment processing failed" after TaraMoney had
 * already created the payment link: the customer saw an error instead of the
 * link. Widen the column and keep the model's write-side clamp (AuditLog)
 * plus the non-fatal audit wrapper as the actual guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('audit_logs')) {
            return;
        }

        if (! MigrationHelper::columnExists('audit_logs', 'user_agent')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deliberately not narrowed back: rows written since this ran may be
        // longer than 191, and MySQL would truncate them on the way down.
    }
};
