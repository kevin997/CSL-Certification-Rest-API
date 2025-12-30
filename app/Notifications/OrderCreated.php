<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private Order $order;
    private TelegramService $telegramService;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, TelegramService $telegramService)
    {
        $this->order = $order;
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

    public function toTelegram($notifiable)
    {
        $chatId = $this->telegramService->getChatId();

        Log::info('Running OrderCreated notification', ['order_id' => $this->order->id]);

        if (!$chatId) {
            Log::error('Could not determine Telegram chat ID');
            return null;
        }

        // Get environment details
        $environment = $this->order->environment;
        
        // Escape content
        $environmentName = $this->telegramService->escapeMarkdownV2($environment->name);
        $orderNumber = $this->telegramService->escapeMarkdownV2($this->order->order_number);
        $amount = $this->telegramService->escapeMarkdownV2(number_format($this->order->total_amount, 2));
        $currency = $this->telegramService->escapeMarkdownV2($this->order->currency);
        $customerName = $this->telegramService->escapeMarkdownV2($this->order->billing_name);
        $customerEmail = $this->telegramService->escapeMarkdownV2($this->order->billing_email);
        
        // Construct Continue Payment URL
        $protocol = app()->environment('production') ? 'https' : 'http';
        // Use environment's primary domain for the link
        $continuePaymentUrl = "{$protocol}://{$environment->primary_domain}/checkout/continue-payment/{$this->order->id}";
        $escapedUrl = $this->telegramService->escapeMarkdownV2($continuePaymentUrl);

        $message = "🛒 *New Order Created*\n\n";
        $message .= "Environment: `{$environmentName}`\n";
        $message .= "Order: `{$orderNumber}`\n";
        $message .= "Amount: `{$amount} {$currency}`\n";
        $message .= "Customer: `{$customerName}` ({$customerEmail})\n";
        $message .= "URL: [Continue Payment]({$escapedUrl})\n";

        $buttons = [
            'text' => 'Continue Payment',
            'url' => $continuePaymentUrl
        ];

        $this->telegramService->sendMessage(
            $chatId,
            $message,
            $buttons
        );
    }
}
