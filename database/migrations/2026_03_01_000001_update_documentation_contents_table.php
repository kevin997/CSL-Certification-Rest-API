<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_contents', function (Blueprint $table) {
            if (!MigrationHelper::columnExists('documentation_contents', 'activity_id')) {
                $table->foreignId('activity_id')->after('id')->constrained('activities')->onDelete('cascade');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'title')) {
                $table->string('title')->after('activity_id');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'format')) {
                $table->string('format', 20)->default('plain')->after('content');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'version')) {
                $table->string('version', 50)->nullable()->after('format');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'tags')) {
                $table->json('tags')->nullable()->after('version');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'attachments')) {
                $table->json('attachments')->nullable()->after('tags');
            }

            if (!MigrationHelper::columnExists('documentation_contents', 'related_links')) {
                $table->json('related_links')->nullable()->after('attachments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documentation_contents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn([
                'activity_id',
                'title',
                'description',
                'format',
                'version',
                'tags',
                'attachments',
                'related_links',
            ]);
        });
    }
};
