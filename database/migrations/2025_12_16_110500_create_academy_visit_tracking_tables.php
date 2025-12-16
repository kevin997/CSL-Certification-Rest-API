<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academy_visitors')) {
            if (MigrationHelper::tableExists('academy_visitors')) {
                echo "Table 'academy_visitors' already exists, skipping...\n";
            } else {
                Schema::create('academy_visitors', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
                    $table->string('visit_hash');

                    $table->unsignedInteger('visits_count')->default(0);
                    $table->timestamp('first_seen_at')->nullable();
                    $table->timestamp('last_seen_at')->nullable();

                    $table->string('ip_hash')->nullable();
                    $table->string('user_agent')->nullable();
                    $table->string('accept_language')->nullable();

                    $table->string('country_code', 2)->nullable();
                    $table->string('country_name')->nullable();
                    $table->string('state_prov')->nullable();
                    $table->string('city')->nullable();
                    $table->string('isp')->nullable();

                    $table->json('geo_data')->nullable();

                    $table->timestamps();

                    $table->unique(['environment_id', 'visit_hash'], 'academy_visitors_env_hash_unique');
                    $table->index(['environment_id', 'last_seen_at']);
                    $table->index(['environment_id', 'country_code']);
                });
            }
        }

        if (!Schema::hasTable('academy_visit_events')) {
            if (MigrationHelper::tableExists('academy_visit_events')) {
                echo "Table 'academy_visit_events' already exists, skipping...\n";
            } else {
                Schema::create('academy_visit_events', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
                    $table->string('visit_hash');

                    $table->string('path')->nullable();
                    $table->string('referrer')->nullable();
                    $table->string('ip_hash')->nullable();
                    $table->string('user_agent')->nullable();

                    $table->timestamp('occurred_at');
                    $table->timestamps();

                    $table->index(['environment_id', 'occurred_at']);
                    $table->index(['environment_id', 'visit_hash', 'occurred_at']);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academy_visit_events')) {
            Schema::dropIfExists('academy_visit_events');
        }

        if (Schema::hasTable('academy_visitors')) {
            Schema::dropIfExists('academy_visitors');
        }
    }
};
