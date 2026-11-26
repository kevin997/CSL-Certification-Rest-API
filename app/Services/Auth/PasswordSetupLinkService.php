<?php

namespace App\Services\Auth;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\WelcomeToEnvironment;
use App\Models\Environment;
use App\Models\User;
use App\Services\Sales\ShortLinkService;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Issues the one-time "set your password" link for a checkout-created account.
 *
 * This is transactional (not marketing): it must keep working when marketing
 * email is disabled. A fresh broker token is created every time, so a retried
 * email still opens a working link.
 */
class PasswordSetupLinkService
{
    public function __construct(private readonly ShortLinkService $shortLinks) {}

    public function url(User $user, Environment $environment): string
    {
        $token = Password::broker()->createToken($user);

        $url = TenantUrl::to($environment, '/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'environment_id' => $environment->id,
        ]);

        return $this->shortLinks->forUrl($url, $environment, 1);
    }

    /**
     * Attempt delivery immediately and report the failure message, if any.
     */
    public function sendEmailNow(User $user, Environment $environment, ?string $url = null): ?string
    {
        try {
            Mail::to($user->email)->sendNow(
                new WelcomeToEnvironment($user, $environment, null, $url ?? $this->url($user, $environment)),
            );

            return null;
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * Queue the email and let the queue worker handle transport retries.
     */
    public function sendEmailQueued(User $user, Environment $environment, ?string $url = null): void
    {
        Mail::to($user->email)->queue(
            new WelcomeToEnvironment($user, $environment, null, $url ?? $this->url($user, $environment)),
        );
    }

    public function sendWhatsApp(User $user, string $url): bool
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($user->whatsapp_number ?: $user->phone_number));

        if ($phone === '') {
            return false;
        }

        $message = "Bonjour {$user->name}, voici le lien pour définir le mot de passe de votre compte et accéder à vos cours : {$url}";

        SendWhatsAppNotification::dispatch($phone, $message);

        return true;
    }
}
