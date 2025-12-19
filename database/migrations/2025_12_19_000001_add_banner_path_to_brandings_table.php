<?php

use App\Helpers\MigrationHelper;
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
        if (!MigrationHelper::tableExists('brandings')) {
            echo "Table 'brandings' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('brandings', function (Blueprint $table) {
            if (!MigrationHelper::columnExists('brandings', 'banner_path')) {
                $table->string('banner_path', 500)->nullable()->after('favicon_path');
                $table->index('banner_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!MigrationHelper::tableExists('brandings')) {
            return;
        }

        Schema::table('brandings', function (Blueprint $table) {
            if (MigrationHelper::columnExists('brandings', 'banner_path')) {
                $table->dropIndex(['banner_path']);
                $table->dropColumn('banner_path');
            }
        });
    }
};
