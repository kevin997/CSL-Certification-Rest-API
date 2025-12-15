<?php

namespace App\Console\Commands;

use App\Mail\ProductSubscriptionExpiringReminder;
use App\Models\ProductSubscription;
use App\Models\ProductSubscriptionReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendProductSubscriptionReminders extends Command
{
    protected $signature = 'product-subscriptions:send-reminders
                            {--days=* : Days before expiry to remind (can be passed multiple times)}
                            {--limit=500 : Max number of subscriptions to process per run}';

    protected $description = 'Send reminder emails for expiring product subscriptions (deduped)';

    public function handle(): int
    {
        $daysList = $this->option('days');
        if (empty($daysList)) {
            $daysList = [7, 3, 1, 0];
        }

        $daysList = collect($daysList)
            ->map(fn($d) => (int) $d)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $limit = (int) $this->option('limit');

        $now = now();
        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($daysList as $days) {
            $windowStart = $now->copy()->addDays($days)->startOfDay();
            $windowEnd = $now->copy()->addDays($days)->endOfDay();

            $subscriptions = ProductSubscription::query()
                ->with(['user', 'environment', 'product'])
                ->whereIn('status', ['active', 'cancel_pending'])
                ->whereNull('paused_at')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [$windowStart, $windowEnd])
                ->limit($limit)
                ->get();

            foreach ($subscriptions as $subscription) {
                $email = $subscription->user?->email;
                if (!$email) {
                    $skipped++;
                    continue;
                }

                $reminderKey = $days <= 0 ? 'expires_today' : 'expires_in_' . $days;

                $reminder = ProductSubscriptionReminder::firstOrCreate(
                    [
                        'product_subscription_id' => $subscription->id,
                        'reminder_key' => $reminderKey,
                    ],
                    [
                        'sent_at' => null,
                        'meta' => null,
                    ]
                );

                if ($reminder->sent_at) {
                    $skipped++;
                    continue;
                }

                if (!$reminder->wasRecentlyCreated) {
                    $skipped++;
                    continue;
                }

                try {
                    Mail::to($email)->send(new ProductSubscriptionExpiringReminder($subscription, $days));

                    $reminder->update([
                        'sent_at' => now(),
                        'meta' => [
                            'email' => $email,
                            'ends_at' => $subscription->ends_at?->toIso8601String(),
                            'days' => $days,
                        ],
                    ]);

                    $sent++;
                } catch (\Throwable $e) {
                    $errors++;

                    Log::error('Failed to send product subscription reminder', [
                        'product_subscription_id' => $subscription->id,
                        'reminder_key' => $reminderKey,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info('Product subscription reminders run finished.');
        $this->info('Sent: ' . $sent);
        $this->info('Skipped: ' . $skipped);
        $this->info('Errors: ' . $errors);

        return self::SUCCESS;
    }
}
