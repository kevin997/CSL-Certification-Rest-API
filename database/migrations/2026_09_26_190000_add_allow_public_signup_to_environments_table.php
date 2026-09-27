<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->boolean('allow_public_signup')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropColumn('allow_public_signup');
        });
    }
};
