<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('payment_gateway_settings')) {
            echo "Table 'payment_gateway_settings' already exists, skipping...\n";
        } else {
            Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->string('gateway_name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->string('icon')->nullable();
            $table->string('display_name')->nullable();
            $table->decimal('transaction_fee_percentage', 5, 2)->default(0);
            $table->decimal('transaction_fee_fixed', 10, 2)->default(0);
            $table->string('webhook_url')->nullable();
            $table->string('api_version')->nullable();
            $table->string('mode')->default('sandbox'); // sandbox or live
            $table->integer('sort_order')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            });
        }
        // Create a composite index on environment_id and is_default
        // This doesn't enforce the constraint but will help with query performance
        DB::statement('CREATE INDEX idx_env_default ON payment_gateway_settings(environment_id, is_default)');
        
        // Create a trigger to ensure only one default gateway per environment
        DB::unprepared('CREATE TRIGGER trig_payment_gateway_single_default
            BEFORE INSERT ON payment_gateway_settings
            FOR EACH ROW
            BEGIN
                IF NEW.is_default = 1 THEN
                    UPDATE payment_gateway_settings 
                    SET is_default = 0 
                    WHERE environment_id = NEW.environment_id 
                    AND is_default = 1;
                END IF;
            END');
            
        DB::unprepared('CREATE TRIGGER trig_payment_gateway_single_default_update
            BEFORE UPDATE ON payment_gateway_settings
            FOR EACH ROW
            BEGIN
                IF NEW.is_default = 1 AND (OLD.is_default = 0 OR OLD.environment_id != NEW.environment_id) THEN
                    UPDATE payment_gateway_settings 
                    SET is_default = 0 
                    WHERE environment_id = NEW.environment_id 
                    AND id != NEW.id 
                    AND is_default = 1;
                END IF;
            END');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the triggers first
        DB::unprepared('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default');
        DB::unprepared('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default_update');
        
        Schema::dropIfExists('payment_gateway_settings');
    }
};
