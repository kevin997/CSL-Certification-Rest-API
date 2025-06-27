<?php

namespace App\Notifications;

use App\Models\Environment;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EnvironmentPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The reset token.
     *
     * @var string
     */
    protected $token;

    /**
     * The environment.
     *
     * @var \App\Models\Environment
     */
    protected $environment;

    /**
     * The environment email.
     *
     * @var string
     */
    protected $environmentEmail;

    /**
     * The user email.
     *
     * @var string
     */
    protected $userEmail;

    /**
     * The Telegram service.
     *
     * @var \App\Services\TelegramService
     */
    protected $telegramService;

    /**
     * Create a new notification instance.
     *
     * @param string $token
     * @param \App\Models\Environment $environment
     * @param string $environmentEmail
     * @param string $userEmail
     * @param \App\Services\TelegramService $telegramService
     * @return void
     */
    public function __construct(
        string $token,
        Environment $environment,
        string $environmentEmail,
        string $userEmail,
        TelegramService $telegramService
    ) {
        $this->token = $token;
        $this->environment = $environment;
        $this->environmentEmail = $environmentEmail;
        $this->userEmail = $userEmail;
        $this->telegramService = $telegramService;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(): array
    {
        return ['custom'];
    }

    /**
     * Send the notification.
     *
     * @return void
     */
    public function send(): void
    {
        try {
            $chatId = $this->telegramService->getChatId();
            if (!$chatId) {
                Log::warning('Telegram chat ID not configured for password reset notification');
                return;
            }

            $message = $this->formatTelegramMessage();
            $this->telegramService->sendMessage($chatId, $message, []);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset Telegram notification: ' . $e->getMessage(), [
                'environment_id' => $this->environment->id,
                'user_email' => $this->userEmail,
                'environment_email' => $this->environmentEmail
            ]);
        }
    }

    /**
     * Format the Telegram message.
     *
     * @return string
     */
    private function formatTelegramMessage(): string
    {
        // Generate the reset URL
        $resetUrl = $this->generateResetUrl();

        return "🔐 *Password Reset Requested*\n\n" .
               "Environment: *{$this->environment->name}*\n" .
               "User Email: *{$this->userEmail}*\n" .
               "Environment Email: *{$this->environmentEmail}*\n\n" .
               "Reset Password URL: [Click here to reset password]({$resetUrl})\n\n" .
               "This link will expire in " . config('auth.passwords.users.expire', 60) . " minutes.\n\n" .
               "If you did not request this password reset, please ignore this message.";
    }

    /**
     * Generate the reset URL.
     *
     * @return string
     */
    private function generateResetUrl(): string
    {
        // Default Laravel reset URL
        $resetUrl = "https://{$this->environment->primary_domain}/reset-password?token={$this->token}&email={$this->userEmail}";

        return $resetUrl;
    }
}
