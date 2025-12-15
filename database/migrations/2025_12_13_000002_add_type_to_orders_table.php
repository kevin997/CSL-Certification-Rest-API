<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!MigrationHelper::tableExists('orders')) {
            return;
        }

        if (!Schema::hasColumn('orders', 'type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('type')->default('storefront')->after('status');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        if (!MigrationHelper::tableExists('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};
