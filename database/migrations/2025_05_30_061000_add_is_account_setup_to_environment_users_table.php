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
        Schema::table('environment_user', function (Blueprint $table) {
            // Check if column already exists

            if (!MigrationHelper::columnExists('environment_user', 'is_account_setup')) {

                $table->boolean('is_account_setup')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environment_user', function (Blueprint $table) {
            $table->dropColumn('is_account_setup');
        });
    }
};
