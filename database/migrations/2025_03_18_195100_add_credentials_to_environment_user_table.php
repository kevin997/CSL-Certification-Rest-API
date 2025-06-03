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
        if (!MigrationHelper::tableExists('environment_user')) {
            echo "Table 'environment_user' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('environment_user', function (Blueprint $table) {
            // Check if column already exists
            if (!MigrationHelper::columnExists('environment_user', 'environment_email')) {

                $table->string('environment_email')->nullable()->after('permissions');
                // Check if column already exists
                if (!MigrationHelper::columnExists('environment_user', 'environment_password')) {

                    $table->string('environment_password')->nullable()->after('environment_email');
                    // Check if column already exists
                    if (!MigrationHelper::columnExists('environment_user', 'email_verified_at')) {

                        $table->timestamp('email_verified_at')->nullable()->after('environment_password');
                        // Check if column already exists
                        if (!MigrationHelper::columnExists('environment_user', 'use_environment_credentials')) {

                            $table->boolean('use_environment_credentials')->default(false)->after('email_verified_at');
                        }
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environment_user', function (Blueprint $table) {
            $table->dropColumn([
                'environment_email',
                'environment_password',
                'email_verified_at',
                'use_environment_credentials'
            ]);
        });
    }
};
