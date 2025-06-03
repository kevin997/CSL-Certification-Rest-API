<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Types\Type;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Need to register the enum type with Doctrine
        if (!Type::hasType('enum')) {
            Type::addType('enum', 'Doctrine\DBAL\Types\StringType');
        }
        
        Schema::table('transactions', function (Blueprint $table) {
            // Modify the transaction_id column to be a string with sufficient length for UUID values
            // Check if column already exists

            if (!MigrationHelper::columnExists('transactions', 'transaction_id')) {

                // Check if column already exists


                if (!MigrationHelper::columnExists('transactions', 'transaction_id')) {


                    $table->string('transaction_id', 100)->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Revert back to original size if needed
            $table->string('transaction_id', 36)->change();
        });
    }
};