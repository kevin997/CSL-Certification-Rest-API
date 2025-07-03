<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\TelegramService;

class InvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $successCount;
    private $failCount;
    private $failedEnvironments;
    private TelegramService $telegramService;

    /**
     * Create a new notification instance.
     */
    public function __construct($successCount, $failCount, $failedEnvironments, TelegramService $telegramService)
    {
        $this->successCount = $successCount;
        $this->failCount = $failCount;
        $this->failedEnvironments = $failedEnvironments;
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
        if (!$chatId) return;
        $message = "📊 *Invoice Batch Summary*\n\n";
        $message .= "Successful: `{$this->successCount}`\n";
        $message .= "Failed: `{$this->failCount}`\n";
        if ($this->failCount > 0) {
            $failedList = implode(", ", array_map(fn($env) => $this->telegramService->escapeMarkdownV2($env), $this->failedEnvironments));
            $message .= "Failed Environments: {$failedList}\n";
        }
        $message .= "Time: " . $this->telegramService->escapeMarkdownV2(now()->format('Y-m-d H:i:s'));
        $this->telegramService->sendMessage($chatId, $message, []);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'success_count' => $this->successCount,
            'fail_count' => $this->failCount,
            'failed_environments' => $this->failedEnvironments,
        ];
    }
}
