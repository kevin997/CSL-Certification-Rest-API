<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->json('draft');
            $table->json('published')->nullable();
            $table->string('published_trigger', 32)->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('draft_version')->default(1);
            $table->boolean('active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('activated_at_us')->nullable();
            $table->timestamps();
            $table->index(['environment_id', 'active', 'published_trigger'], 'academy_workflow_event');
        });

        Schema::create('academy_workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_workflow_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 72);
            $table->unsignedInteger('revision');
            $table->string('channel', 16);
            $table->string('state', 24);
            $table->string('reason', 48)->nullable();
            $table->timestamps();
            $table->unique(['academy_workflow_id', 'event_key', 'channel'], 'academy_workflow_run_event');
        });

        Schema::create('academy_workflow_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 72);
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel', 16);
            $table->timestamp('created_at');
            $table->unique(['environment_id', 'event_key', 'recipient_id', 'channel'], 'academy_workflow_delivery_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_workflow_deliveries');
        Schema::dropIfExists('academy_workflow_runs');
        Schema::dropIfExists('academy_workflows');
    }
};
