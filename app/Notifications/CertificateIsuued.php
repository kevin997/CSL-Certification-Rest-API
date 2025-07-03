<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Environment;

class CertificateIsuued extends Notification implements ShouldQueue
{
    use Queueable;

    private User $user;
    private Environment $environment;
    private TelegramService $telegramService;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, Environment $environment, TelegramService $telegramService)
    {
        $this->user = $user;
        $this->environment = $environment;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }


    public function toTelegram($notifiable)
    {
        $chatId = $this->telegramService->getChatId();

        Log::info('Running EnvironmentAccountCreated notification');

        if (!$chatId) {
            Log::error('Could not determine Telegram chat ID');
            return null;
        }
        
        // Escape special characters for MarkdownV2
        $environmentName = $this->telegramService->escapeMarkdownV2($this->environment->name);
        $userEmail = $this->telegramService->escapeMarkdownV2($this->user->email);
        $createdAt = $this->telegramService->escapeMarkdownV2(now()->format('Y-m-d H:i:s'));
        
        // Generate login URL with appropriate protocol
        $protocol = app()->environment('production') ? 'https' : 'http';
        $loginUrl = "{$protocol}://{$this->environment->primary_domain}/auth/login";
        $escapedLoginUrl = $this->telegramService->escapeMarkdownV2($loginUrl);

        $message = "🆕 *Certificate Issued*\n\n";
        $message .= "Environment: `{$environmentName}`\n";
        $message .= "URL: [Certificate]({$escapedLoginUrl})\n";
        $message .= "User: `{$userEmail}`\n";
        $message .= "Created at: {$createdAt}\n";

        $buttons = [
            'text' => 'Go to Certificate',
            'url' => $loginUrl
        ];

        $this->telegramService->sendMessage(
            $chatId,
            $message,
            $buttons
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
