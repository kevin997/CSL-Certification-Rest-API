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
        Schema::table('third_party_services', function (Blueprint $table) {
            $table->foreignId('environment_id')
                ->nullable()
                ->after('id')
                ->constrained('environments')
                ->onDelete('cascade');

            $table->index(['environment_id', 'service_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('third_party_services', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropIndex(['environment_id', 'service_type']);
            $table->dropColumn('environment_id');
        });
    }
};
