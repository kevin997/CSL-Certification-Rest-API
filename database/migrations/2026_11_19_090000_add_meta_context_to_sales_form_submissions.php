<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the browser-side Meta identifiers and request context with the
 * submission so server-side Conversions API events (CompleteRegistration,
 * InitiateCheckout, Purchase) can carry fbp/fbc/IP/UA for matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('sales_form_submissions')) {
            return;
        }

        $columns = [
            'meta_fbp' => fn (Blueprint $table) => $table->string('meta_fbp', 255)->nullable(),
            'meta_fbc' => fn (Blueprint $table) => $table->string('meta_fbc', 255)->nullable(),
            'client_ip' => fn (Blueprint $table) => $table->string('client_ip', 45)->nullable(),
            'client_user_agent' => fn (Blueprint $table) => $table->string('client_user_agent', 1024)->nullable(),
            'referrer_url' => fn (Blueprint $table) => $table->string('referrer_url', 2048)->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! MigrationHelper::columnExists('sales_form_submissions', $name)) {
                Schema::table('sales_form_submissions', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['meta_fbp', 'meta_fbc', 'client_ip', 'client_user_agent', 'referrer_url'] as $column) {
            if (MigrationHelper::columnExists('sales_form_submissions', $column)) {
                Schema::table('sales_form_submissions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
