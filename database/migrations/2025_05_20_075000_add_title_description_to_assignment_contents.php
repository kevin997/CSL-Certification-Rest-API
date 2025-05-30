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
        Schema::table('assignment_contents', function (Blueprint $table) {
            if (!Schema::hasColumn('assignment_contents', 'title')) {
                $table->string('title')->after('activity_id')->nullable();
            }
            if (!Schema::hasColumn('assignment_contents', 'description')) {
                $table->text('description')->after('title')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_contents', function (Blueprint $table) {
            if (Schema::hasColumn('assignment_contents', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('assignment_contents', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
