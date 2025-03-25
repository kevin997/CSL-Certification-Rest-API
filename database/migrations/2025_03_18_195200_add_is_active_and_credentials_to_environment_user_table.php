<?php

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
            $table->boolean('is_active')->default(true)->after('joined_at');
            $table->json('credentials')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environment_user', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'credentials',
            ]);
        });
    }
};
