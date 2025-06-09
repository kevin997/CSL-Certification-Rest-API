<?php

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
        if (!Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('environment_id')->nullable()->index()->comment('Environment ID this commission applies to, null means global');
                $table->string('name')->comment('Name of the commission rule');
                $table->decimal('rate', 8, 2)->comment('Commission rate as a percentage (e.g., 17.00 for 17%)');
                $table->boolean('is_active')->default(true)->comment('Whether this commission rule is active');
                $table->text('description')->nullable()->comment('Description of the commission rule');
                $table->json('conditions')->nullable()->comment('JSON conditions for when this commission applies');
                $table->integer('priority')->default(0)->comment('Priority of this rule, higher numbers take precedence');
                $table->timestamp('valid_from')->nullable()->comment('When this commission rule becomes valid');
                $table->timestamp('valid_until')->nullable()->comment('When this commission rule expires');
                $table->timestamps();
                $table->softDeletes();

                // Add unique constraint to prevent duplicate commission rules for the same environment
                $table->unique(['environment_id', 'name'], 'unique_commission_per_environment');
            });

            // Insert the default global commission of 17%
            DB::table('commissions')->insert([
                'name' => 'Default Global Commission',
                'rate' => 17.00,
                'is_active' => true,
                'description' => 'Default commission rate applied to all environments',
                'priority' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commissions')) {
            Schema::dropIfExists('commissions');
        }
    }
};
