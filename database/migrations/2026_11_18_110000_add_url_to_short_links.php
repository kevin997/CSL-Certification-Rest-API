<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short links were order-specific. Password-set links need the same short,
 * branded resolver, so allow a generic destination URL and a null order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('short_links')) {
            return;
        }

        if (! MigrationHelper::columnExists('short_links', 'url')) {
            Schema::table('short_links', function (Blueprint $table) {
                $table->text('url')->nullable()->after('code');
            });
        }

        Schema::table('short_links', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (MigrationHelper::columnExists('short_links', 'url')) {
            Schema::table('short_links', function (Blueprint $table) {
                $table->dropColumn('url');
            });
        }
    }
};
