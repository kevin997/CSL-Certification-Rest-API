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
        if (!Schema::hasColumn('orders', 'phone_number')) {
            Schema::table('orders', function (Blueprint $table) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('orders', 'phone_number')) {

                    $table->string('phone_number')->nullable()->after('billing_email');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'phone_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('phone_number');
            });
        }
    }
};
