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
        // Marketplace flow might require token generation validation
        // this file shouldn't execute anything, since nothing is strictly needed migration-wise for caching OTPs.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
