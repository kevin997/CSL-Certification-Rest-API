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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'purpose')) {
                $table->string('purpose')->nullable()->after('status');
            }

            if (!Schema::hasColumn('transactions', 'source_type')) {
                $table->string('source_type')->nullable()->after('purpose');
            }

            if (!Schema::hasColumn('transactions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (!Schema::hasColumn('transactions', 'parent_transaction_id')) {
                $table->string('parent_transaction_id', 100)->nullable()->after('source_id');
            }

            if (!Schema::hasColumn('transactions', 'provider_event_id')) {
                $table->unsignedBigInteger('provider_event_id')->nullable()->after('parent_transaction_id');
            }

            if (!Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('provider_event_id');
            }

            if (!Schema::hasColumn('transactions', 'expected_amount')) {
                $table->decimal('expected_amount', 10, 2)->nullable()->after('idempotency_key');
            }

            if (!Schema::hasColumn('transactions', 'expected_currency')) {
                $table->string('expected_currency', 3)->nullable()->after('expected_amount');
            }

            if (!Schema::hasColumn('transactions', 'platform_fee_amount')) {
                $table->decimal('platform_fee_amount', 10, 2)->default(0)->after('expected_currency');
            }

            if (!Schema::hasColumn('transactions', 'processor_fee_amount')) {
                $table->decimal('processor_fee_amount', 10, 2)->nullable()->after('platform_fee_amount');
            }

            if (!Schema::hasColumn('transactions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('processor_fee_amount');
            }

            if (!Schema::hasColumn('transactions', 'merchant_environment_id')) {
                $table->unsignedBigInteger('merchant_environment_id')->nullable()->after('verified_at');
            }

            if (!Schema::hasColumn('transactions', 'gateway_account_environment_id')) {
                $table->unsignedBigInteger('gateway_account_environment_id')->nullable()->after('merchant_environment_id');
            }
        });

        $this->addIndexSafely('transactions', 'transactions_purpose_index', function (Blueprint $table) {
            $table->index('purpose');
        });

        $this->addIndexSafely('transactions', 'transactions_source_type_source_id_index', function (Blueprint $table) {
            $table->index(['source_type', 'source_id']);
        });

        $this->addIndexSafely('transactions', 'transactions_idempotency_key_unique', function (Blueprint $table) {
            $table->unique('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexSafely('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_idempotency_key_unique');
        });

        $this->dropIndexSafely('transactions', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
        });

        $this->dropIndexSafely('transactions', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'purpose',
                'source_type',
                'source_id',
                'parent_transaction_id',
                'provider_event_id',
                'idempotency_key',
                'expected_amount',
                'expected_currency',
                'platform_fee_amount',
                'processor_fee_amount',
                'verified_at',
                'merchant_environment_id',
                'gateway_account_environment_id',
            ] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Add an index/unique constraint, tolerating a re-run where it already exists.
     *
     * Avoids a hard dependency on doctrine/dbal (not installed in this project)
     * for introspecting existing indexes, so we simply swallow the "duplicate
     * key name" error instead.
     */
    private function addIndexSafely(string $table, string $indexName, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Illuminate\Database\QueryException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }

    /**
     * Drop an index/unique constraint, tolerating it not existing (down() may
     * run against a database where up() partially failed).
     */
    private function dropIndexSafely(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Illuminate\Database\QueryException $e) {
            if (!str_contains($e->getMessage(), "check that column/key exists") && !str_contains($e->getMessage(), 'Unknown')) {
                throw $e;
            }
        }
    }
};
