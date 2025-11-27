<?php

namespace App\Notifications;

use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;


class EnvironmentAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private User $user;
    private Environment $environment;
    private ?string $plainPassword;
    private TelegramService $telegramService;

    /**
     * Create a new notification instance.
     * 
     * IDENTITY UNIFICATION: Password is now optional.
     * When null, indicates user uses their existing global password.
     */
    public function __construct(User $user, Environment $environment, ?string $plainPassword, TelegramService $telegramService)
    {
        $this->user = $user;
        $this->environment = $environment;
        $this->plainPassword = $plainPassword;
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

        $message = "🆕 *Environment Account Created*\n\n";
        $message .= "Environment: `{$environmentName}`\n";
        $message .= "URL: [Login URL]({$escapedLoginUrl})\n";
        $message .= "User: `{$userEmail}`\n";

        // IDENTITY UNIFICATION: Handle optional password
        if ($this->plainPassword) {
            $plainPassword = $this->telegramService->escapeMarkdownV2($this->plainPassword);
            $message .= "Password: `{$plainPassword}`\n";
        } else {
            $message .= "Password: _Uses existing account password_\n";
        }

        $message .= "Created at: {$createdAt}\n";

        $buttons = [
            'text' => 'Go to Login',
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
            'environment_id' => $this->environment->id,
            'user_id'        => $this->user->id,
        ];
    }
}
