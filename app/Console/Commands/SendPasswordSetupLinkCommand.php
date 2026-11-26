<?php

namespace App\Console\Commands;

use App\Jobs\SendPasswordSetupLinkJob;
use App\Models\Environment;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PasswordSetupLinkService;
use Illuminate\Console\Command;

/**
 * Re-sends the transactional set-password email to a learner who paid but
 * never received (or lost) the welcome mail. Not affected by the marketing
 * email kill switch.
 */
class SendPasswordSetupLinkCommand extends Command
{
    protected $signature = 'users:send-password-setup-link
        {email : Learner email address}
        {--environment= : Environment id (defaults to the latest paid order)}
        {--whatsapp : Also send the link on WhatsApp}
        {--retry-hours=0 : Queue a fresh-link email retry after N hours}
        {--show-url : Print the generated link for support}';

    protected $description = 'Re-send the welcome/password-set email to a learner (transactional)';

    public function handle(PasswordSetupLinkService $links): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found for {$email}.");

            return self::FAILURE;
        }

        $environment = $this->resolveEnvironment($user);

        if (! $environment) {
            $this->error('No academy/environment found for this user.');

            return self::FAILURE;
        }

        $url = $links->url($user, $environment);
        $failure = $links->sendEmailNow($user, $environment, $url);

        if ($failure === null) {
            $this->info("Password-set email sent to {$user->email} (academy: {$environment->name}).");
        } else {
            $this->error("Email delivery failed: {$failure}");
        }

        $whatsappSent = false;

        if ($this->option('whatsapp')) {
            $whatsappSent = $links->sendWhatsApp($user, $url);

            if ($whatsappSent) {
                $this->info('WhatsApp delivery queued.');
            } else {
                $this->warn('No WhatsApp/phone number on this account.');
            }
        }

        $retryHours = (int) $this->option('retry-hours');
        $retryQueued = false;

        if ($retryHours > 0) {
            SendPasswordSetupLinkJob::dispatch($user->id, $environment->id)
                ->delay(now()->addHours($retryHours));
            $retryQueued = true;
            $this->info("Queued a fresh-link email retry in {$retryHours} hour(s).");
        }

        if ($this->option('show-url')) {
            $this->line($url);
        }

        if ($failure !== null && ! $whatsappSent && ! $retryQueued) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveEnvironment(User $user): ?Environment
    {
        $environmentId = $this->option('environment');

        if ($environmentId !== null && $environmentId !== '') {
            return Environment::find((int) $environmentId);
        }

        $orderEnvironmentId = Order::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotNull('environment_id')
            ->orderByDesc('id')
            ->value('environment_id');

        if ($orderEnvironmentId) {
            return Environment::find((int) $orderEnvironmentId);
        }

        $membershipEnvironmentId = $user->environments()
            ->orderByDesc('environments.id')
            ->value('environments.id');

        return $membershipEnvironmentId ? Environment::find((int) $membershipEnvironmentId) : null;
    }
}
