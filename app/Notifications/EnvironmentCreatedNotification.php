<?php

namespace App\Notifications;

use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EnvironmentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Environment $environment;
    private User $user;
    private string $adminEmail;
    private string $adminPassword;
    private TelegramService $telegramService;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Environment $environment,
        User $user,
        string $adminEmail,
        string $adminPassword,
        TelegramService $telegramService
    ) {
        $this->environment = $environment;
        $this->user = $user;
        $this->adminEmail = $adminEmail;
        $this->adminPassword = $adminPassword;
        $this->telegramService = $telegramService;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * Send the Telegram notification.
     */
    public function toTelegram($notifiable)
    {
        $chatId = $this->telegramService->getChatId();

        Log::info('Running EnvironmentCreatedNotification notification');

        if (!$chatId) {
            Log::error('Could not determine Telegram chat ID for environment creation notification');
            return null;
        }

        try {
            $message = $this->formatTelegramMessage();
            $loginUrl = $this->generateLoginUrl();
            
            $buttons = [
                'text' => 'Access Environment',
                'url' => $loginUrl
            ];

            $this->telegramService->sendMessage($chatId, $message, $buttons);

            Log::info('Environment created Telegram notification sent', [
                'environment_id' => $this->environment->id,
                'user_id' => $this->user->id,
                'environment_name' => $this->environment->name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send environment created Telegram notification: ' . $e->getMessage(), [
                'environment_id' => $this->environment->id,
                'user_id' => $this->user->id,
                'exception' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Format the Telegram message.
     */
    private function formatTelegramMessage(): string
    {
        $loginUrl = $this->generateLoginUrl();
        $domainType = $this->isSubdomain() ? 'Subdomain' : 'Custom Domain';
        
        // Escape special characters for MarkdownV2
        $environmentName = $this->telegramService->escapeMarkdownV2($this->environment->name);
        $userName = $this->telegramService->escapeMarkdownV2($this->user->name);
        $userEmail = $this->telegramService->escapeMarkdownV2($this->user->email);
        $adminEmail = $this->telegramService->escapeMarkdownV2($this->adminEmail);
        $adminPassword = $this->telegramService->escapeMarkdownV2($this->adminPassword);
        $domain = $this->telegramService->escapeMarkdownV2($this->environment->primary_domain);
        $escapedLoginUrl = $this->telegramService->escapeMarkdownV2($loginUrl);
        $createdAt = $this->telegramService->escapeMarkdownV2(now()->format('Y-m-d H:i:s'));

        return "🚀 *New Environment Created*\n\n" .
            "**Environment Details:**\n" .
            "Name: `{$environmentName}`\n" .
            "Type: `{$domainType}`\n" .
            "Domain: `{$domain}`\n" .
            "URL: [Access Environment]({$escapedLoginUrl})\n\n" .
            "**Owner Information:**\n" .
            "Name: `{$userName}`\n" .
            "Email: `{$userEmail}`\n\n" .
            "**Admin Credentials:**\n" .
            "Email: `{$adminEmail}`\n" .
            "Password: `{$adminPassword}`\n\n" .
            "**Created:** {$createdAt}\n\n" .
            "The environment is ready for use and setup instructions have been sent to the owner\.";
    }

    /**
     * Generate the login URL for the environment.
     * Always uses HTTPS protocol for security and Telegram compatibility.
     */
    private function generateLoginUrl(): string
    {
        // Always use HTTPS protocol for security and Telegram compatibility
        return "https://{$this->environment->primary_domain}/auth/login";
    }

    /**
     * Check if the environment uses a subdomain or custom domain.
     */
    private function isSubdomain(): bool
    {
        return str_contains($this->environment->primary_domain, '.cfpcsl.com');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'environment_id' => $this->environment->id,
            'environment_name' => $this->environment->name,
            'user_id' => $this->user->id,
            'domain' => $this->environment->primary_domain,
            'domain_type' => $this->isSubdomain() ? 'subdomain' : 'custom_domain',
        ];
    }
}
