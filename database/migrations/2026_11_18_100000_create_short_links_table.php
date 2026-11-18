<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short, branded checkout links for order messages.
 *
 * WhatsApp shows the whole URL, and an encrypted checkout token turns a
 * payment reminder into a 350-character wall of text that recipients do not
 * trust. A short code maps back to the order and a fresh token is issued on
 * click; nothing sensitive is stored in the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (MigrationHelper::tableExists('short_links')) {
            return;
        }

        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->index(['order_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
