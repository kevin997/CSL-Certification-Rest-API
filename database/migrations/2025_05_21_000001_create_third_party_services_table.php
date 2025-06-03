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
        if (!Schema::hasTable('third_party_services')) {
            // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('third_party_services')) {
            echo "Table 'third_party_services' already exists, skipping...\n";
        } else {
            Schema::create('third_party_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('base_url');
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->text('bearer_token')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('service_type')->index();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
            });
        }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('third_party_services')) {
            Schema::dropIfExists('third_party_services');
        }
    }
};
