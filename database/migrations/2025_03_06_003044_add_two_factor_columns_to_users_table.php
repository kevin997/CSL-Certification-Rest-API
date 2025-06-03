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
        // Ensure the table exists before modifying it
        if (!MigrationHelper::tableExists('users')) {
            echo "Table 'users' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Check if column already exists
        if (!MigrationHelper::columnExists('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')
                    ->after('password')
                    ->nullable();
            }

            // Check if column already exists
        if (!MigrationHelper::columnExists('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')
                    ->after('two_factor_secret')
                    ->nullable();
            }

            // Check if column already exists
        if (!MigrationHelper::columnExists('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')
                    ->after('two_factor_recovery_codes')
                    ->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!MigrationHelper::tableExists('users')) {
            return;
        }
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
