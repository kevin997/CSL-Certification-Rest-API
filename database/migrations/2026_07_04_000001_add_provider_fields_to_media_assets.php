<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds first-class columns to media_assets for the external HLS provider
 * (Bunny Stream) and fixes the latent gap where the processing webhook wrote
 * `size` / `mime_type` to columns that never existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!MigrationHelper::tableExists('media_assets')) {
            return;
        }

        Schema::table('media_assets', function (Blueprint $table) {
            if (!MigrationHelper::columnExists('media_assets', 'provider')) {
                // e.g. 'bunny_stream' | 'media_service'
                $table->string('provider')->nullable()->after('media_service_id');
            }
            if (!MigrationHelper::columnExists('media_assets', 'provider_asset_id')) {
                // Bunny video GUID (or provider-specific id).
                $table->string('provider_asset_id')->nullable()->index()->after('provider');
            }
            if (!MigrationHelper::columnExists('media_assets', 'playback_url')) {
                $table->text('playback_url')->nullable()->after('provider_asset_id');
            }
            if (!MigrationHelper::columnExists('media_assets', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('type');
            }
            if (!MigrationHelper::columnExists('media_assets', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mime_type');
            }
            if (!MigrationHelper::columnExists('media_assets', 'duration')) {
                $table->unsignedInteger('duration')->nullable()->after('size');
            }
        });
    }

    public function down(): void
    {
        if (!MigrationHelper::tableExists('media_assets')) {
            return;
        }

        Schema::table('media_assets', function (Blueprint $table) {
            foreach (['provider', 'provider_asset_id', 'playback_url', 'mime_type', 'size', 'duration'] as $column) {
                if (MigrationHelper::columnExists('media_assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
