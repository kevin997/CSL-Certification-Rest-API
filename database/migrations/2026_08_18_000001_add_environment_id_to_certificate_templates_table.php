<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificate templates were global: every environment listed, read,
     * re-defaulted and deleted every other environment's templates. This is the
     * column that makes them ownable.
     *
     * Nullable on purpose. Existing rows predate ownership and are attributed
     * separately by certificates:attribute-template-environments, which derives
     * the owner from the courses each template is actually used in.
     */
    public function up(): void
    {
        if (! MigrationHelper::tableExists('certificate_templates')) {
            echo "Table 'certificate_templates' does not exist, skipping migration...\n";

            return;
        }

        Schema::table('certificate_templates', function (Blueprint $table) {
            if (! MigrationHelper::columnExists('certificate_templates', 'environment_id')) {
                $table->foreignId('environment_id')->nullable()->after('id')
                    ->constrained('environments')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropColumn('environment_id');
        });
    }
};
