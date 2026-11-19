<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device slots for learners.
 *
 * A "device" here is a credential the browser presents (a random uuid kept in
 * tenant-scoped storage), not a piece of hardware. Browsers expose no MAC
 * address and modern phones randomise the one they do have, so hardware
 * identity cannot be used. Each row is one device slot per learner per
 * academy; an instructor can revoke it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (MigrationHelper::tableExists('learner_devices')) {
            return;
        }

        Schema::create('learner_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('uuid');
            $table->string('label', 120);
            $table->string('platform', 60)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('device_type', 20)->default('unknown');
            $table->string('status', 20)->default('active');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip_hash', 64)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->string('revoke_reason', 60)->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'user_id', 'uuid'], 'learner_devices_slot_unique');
            $table->index(['environment_id', 'user_id', 'status'], 'learner_devices_slot_status');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_devices');
    }
};
