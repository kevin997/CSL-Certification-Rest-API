<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * email_campaign messages carry their copy in email_subject/email_html and
 * store no plain-text body — but body was created NOT NULL, so inserting an
 * email campaign row failed. Group tips and statuses keep using body.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
        });
    }
};
