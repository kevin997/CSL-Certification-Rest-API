<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data migration: backfills the new `purpose` column on existing
     * transactions, mirroring the discriminator heuristic previously used
     * ad-hoc in TransactionController.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->whereNull('purpose')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $purpose = $this->resolvePurpose($row);

                    DB::table('transactions')
                        ->where('id', $row->id)
                        ->update(['purpose' => $purpose]);
                }
            });
    }

    /**
     * Resolve the purpose for a legacy transaction row.
     */
    private function resolvePurpose(object $row): string
    {
        $decoded = json_decode($row->payment_method_details ?: '[]', true) ?: [];

        $productName = $row->product_name ?? null;

        $isLicence = (($decoded['scope'] ?? null) === 'platform')
            || ($row->description && stripos($row->description, 'supported plan') !== false)
            || ((float) $row->total_amount === 177.00)
            || ($row->notes && stripos($row->notes, 'supported') !== false)
            || ($productName && stripos($productName, 'supported') !== false);

        if ($isLicence) {
            return 'environment_white_label_license';
        }

        if (in_array($row->status, ['refunded', 'partially_refunded'], true)) {
            return 'refund';
        }

        if (!empty($row->order_id)) {
            return 'course_sale';
        }

        if ((float) $row->amount === 0.0) {
            return 'free_enrollment';
        }

        return 'course_sale';
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible: this is a data backfill, not a destructive schema
     * change. Leaving `purpose` populated on down() is safer than wiping it.
     */
    public function down(): void
    {
        return;
    }
};
