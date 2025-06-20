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
        if (!Schema::hasTable('audit_logs')) {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_type')->index()->comment('Type of log (webhook, callback, etc)');
            $table->string('source')->index()->nullable()->comment('Source of the log (gateway name, service, etc)');
            $table->string('action')->index()->nullable()->comment('Action being performed');
            $table->string('entity_type')->index()->nullable()->comment('Type of entity being acted upon (e.g., Transaction)');
            $table->string('entity_id')->nullable()->comment('ID of the entity');
            $table->unsignedBigInteger('environment_id')->nullable()->index()->comment('Environment ID if applicable');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('User ID if applicable');
            $table->json('request_data')->nullable()->comment('Full request data as JSON');
            $table->json('response_data')->nullable()->comment('Response data as JSON');
            $table->json('metadata')->nullable()->comment('Additional metadata as JSON');
            $table->text('notes')->nullable()->comment('Additional notes or error messages');
            $table->ipAddress('ip_address')->nullable()->comment('IP address of the request');
            $table->string('user_agent')->nullable()->comment('User agent of the request');
            $table->string('status')->nullable()->comment('Status of the audit (success, failure, etc)');
            $table->timestamps();
        });
    }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::hasTable('audit_logs')) {

            Schema::dropIfExists('audit_logs');
        }
    }
};
