<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate content is created before a template is chosen.
 *
 * CertificateContentController::store() fills template_path only when the
 * request carries a certificate_template_id, but the column was created
 * NOT NULL with no default. Every certificate saved without a template died
 * on the insert with SQLSTATE[HY000] 1364 and surfaced as a 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_contents', function (Blueprint $table) {
            // 191, not the Blueprint default: AppServiceProvider sets
            // Schema::defaultStringLength(191) and the live column is
            // varchar(191). Changing a column redefines it wholesale, so the
            // length has to be restated or this quietly widens it.
            $table->string('template_path', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_contents', function (Blueprint $table) {
            $table->string('template_path', 191)->nullable(false)->change();
        });
    }
};
