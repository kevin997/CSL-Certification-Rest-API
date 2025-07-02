<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quiz_question_options', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('feedback');
            $table->string('image_alt')->nullable()->after('image_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quiz_question_options', function (Blueprint $table) {
            $table->dropColumn('image_url');
            $table->dropColumn('image_alt');
        });
    }
}; 