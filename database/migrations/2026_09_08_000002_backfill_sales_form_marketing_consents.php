<?php

use App\Models\MarketingConsent;
use App\Models\SalesFormSubmission;
use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TERMS_VERSION = 'legacy-sales-form-v1';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sales_form_submissions', 'marketing_terms_version')) {
            Schema::table('sales_form_submissions', function (Blueprint $table) {
                $table->string('marketing_terms_version', 64)->nullable()->after('phone');
            });
        }

        SalesFormSubmission::withoutGlobalScopes()
            ->with('environment')
            ->orderBy('id')
            ->chunkById(100, function ($submissions): void {
                foreach ($submissions as $submission) {
                    $this->grantIfMissing(
                        $submission,
                        'email',
                        filter_var($submission->email, FILTER_VALIDATE_EMAIL) !== false
                    );

                    $phone = PhoneNumber::normalize(
                        (string) $submission->phone,
                        $submission->environment?->country_code
                    );
                    $this->grantIfMissing(
                        $submission,
                        'whatsapp',
                        preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1
                    );
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_form_submissions', 'marketing_terms_version')) {
            Schema::table('sales_form_submissions', function (Blueprint $table) {
                $table->dropColumn('marketing_terms_version');
            });
        }
    }

    private function grantIfMissing(SalesFormSubmission $submission, string $channel, bool $hasValidCoordinate): void
    {
        if (! $hasValidCoordinate || MarketingConsent::query()
            ->where('environment_id', $submission->environment_id)
            ->where('sales_form_submission_id', $submission->id)
            ->where('channel', $channel)
            ->exists()) {
            return;
        }

        MarketingConsent::grant(
            $submission,
            $channel,
            'legacy_sales_form',
            self::LEGACY_TERMS_VERSION,
            $submission->created_at
        );
    }
};
