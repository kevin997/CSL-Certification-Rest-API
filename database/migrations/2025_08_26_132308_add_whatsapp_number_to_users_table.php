<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Helpers\MigrationHelper;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!MigrationHelper::tableExists('users')) {
            // Table doesn't exist, skip this migration
            return;
        }

        if (!MigrationHelper::columnExists('users', 'whatsapp_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('whatsapp_number')->nullable()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (MigrationHelper::columnExists('users', 'whatsapp_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('whatsapp_number');
            });
        }
    }
};
