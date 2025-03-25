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
            $table->string('environment_email')->nullable()->after('permissions');
            $table->string('environment_password')->nullable()->after('environment_email');
            $table->timestamp('email_verified_at')->nullable()->after('environment_password');
            $table->boolean('use_environment_credentials')->default(false)->after('email_verified_at');
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
