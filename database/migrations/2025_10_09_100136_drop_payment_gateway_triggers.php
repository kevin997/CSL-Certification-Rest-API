<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the triggers that enforce single default payment gateway
        // This logic will be moved to the model level with proper validation
        DB::statement('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default');
        DB::statement('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default_update');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the triggers if needed (rollback scenario)
        DB::statement("
            CREATE TRIGGER trig_payment_gateway_single_default
            BEFORE INSERT ON payment_gateway_settings
            FOR EACH ROW
            BEGIN
                IF NEW.is_default = 1 THEN
                    UPDATE payment_gateway_settings
                    SET is_default = 0
                    WHERE environment_id = NEW.environment_id
                    AND is_default = 1;
                END IF;
            END
        ");

        DB::statement("
            CREATE TRIGGER trig_payment_gateway_single_default_update
            BEFORE UPDATE ON payment_gateway_settings
            FOR EACH ROW
            BEGIN
                IF NEW.is_default = 1 AND OLD.is_default = 0 THEN
                    UPDATE payment_gateway_settings
                    SET is_default = 0
                    WHERE environment_id = NEW.environment_id
                    AND id != NEW.id
                    AND is_default = 1;
                END IF;
            END
        ");
    }
};
