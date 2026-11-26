<?php

namespace App\Jobs;

use App\Models\Environment;
use App\Models\User;
use App\Services\Auth\PasswordSetupLinkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Delayed retry for transactional password-setup emails.
 *
 * Each attempt regenerates the broker token, so the retry email contains a
 * fresh working link (the previous token may have expired while the mailbox
 * was rate-limited).
 */
class SendPasswordSetupLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 900, 3600];

    public function __construct(
        public int $userId,
        public int $environmentId,
    ) {}

    public function handle(PasswordSetupLinkService $links): void
    {
        $user = User::find($this->userId);
        $environment = Environment::find($this->environmentId);

        if (! $user || ! $environment) {
            return;
        }

        $failure = $links->sendEmailNow($user, $environment);

        if ($failure !== null) {
            throw new RuntimeException('Password setup email failed: '.$failure);
        }
    }
}
