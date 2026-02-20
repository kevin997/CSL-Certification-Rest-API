<?php

namespace App\Console\Commands;

use App\Models\PurchasedTemplate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsumeKafkaEvents extends Command
{
    protected $signature = 'kafka:consume
        {--topic=marketplace.purchase.completed : Kafka topic to consume}
        {--group=csl-certification-consumer : Consumer group ID}
        {--timeout=30000 : Poll timeout in milliseconds}';

    protected $description = 'Consume Kafka events for marketplace purchase fulfillment';

    public function handle(): int
    {
        $topic = $this->option('topic');
        $groupId = $this->option('group');
        $timeout = (int) $this->option('timeout');

        $brokers = config('services.kafka.brokers', 'localhost:29092');

        $this->info("Connecting to Kafka brokers: {$brokers}");
        $this->info("Consuming topic: {$topic} (group: {$groupId})");

        if (extension_loaded('rdkafka')) {
            return $this->consumeWithRdKafka($brokers, $topic, $groupId, $timeout);
        }

        $this->warn('php-rdkafka extension not available. Falling back to socket consumer.');
        return $this->consumeWithSocket($brokers, $topic, $groupId, $timeout);
    }

    private function consumeWithRdKafka(string $brokers, string $topic, string $groupId, int $timeout): int
    {
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $groupId);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        $this->info('Listening for messages... (Ctrl+C to stop)');

        while (true) {
            $message = $consumer->consume($timeout);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->processMessage($message->payload);
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // No new messages, continue polling
                    break;
                default:
                    $this->error("Kafka error: {$message->errstr()}");
                    Log::error('Kafka consumer error', ['error' => $message->errstr()]);
                    break;
            }
        }

        return self::SUCCESS;
    }

    private function consumeWithSocket(string $brokers, string $topic, string $groupId, int $timeout): int
    {
        $this->warn('Socket-based Kafka consumer is a simplified fallback.');
        $this->warn('For production, install php-rdkafka extension.');
        $this->info('Polling for messages...');

        // Simple polling loop that checks the kafka_outbox table from Marketplace API
        // This is a fallback for dev environments without rdkafka
        while (true) {
            try {
                // Try to connect to Marketplace DB outbox as fallback
                $events = DB::connection('marketplace')
                    ->table('kafka_outbox')
                    ->where('topic', $topic)
                    ->where('status', 'pending')
                    ->orderBy('created_at')
                    ->limit(10)
                    ->get();

                foreach ($events as $event) {
                    $this->processMessage($event->payload);

                    DB::connection('marketplace')
                        ->table('kafka_outbox')
                        ->where('id', $event->id)
                        ->update(['status' => 'consumed', 'consumed_at' => now()]);
                }
            } catch (\Exception $e) {
                // Marketplace DB not available, skip silently
                Log::debug('Kafka socket fallback: marketplace DB not reachable', ['error' => $e->getMessage()]);
            }

            sleep($timeout / 1000);
        }

        return self::SUCCESS;
    }

    private function processMessage(string $payload): void
    {
        $this->info('Received message: ' . substr($payload, 0, 200));

        try {
            $data = json_decode($payload, true);

            if (!$data || !isset($data['event'])) {
                $this->warn('Invalid message format, skipping.');
                return;
            }

            match ($data['event']) {
                'purchase.completed' => $this->handlePurchaseCompleted($data),
                default => $this->info("Unhandled event type: {$data['event']}"),
            };
        } catch (\Exception $e) {
            $this->error("Failed to process message: {$e->getMessage()}");
            Log::error('Kafka message processing failed', [
                'payload' => $payload,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handlePurchaseCompleted(array $data): void
    {
        $orderData = $data['data'] ?? [];
        $userId = $orderData['user_id'] ?? null;
        $items = $orderData['items'] ?? [];
        $orderId = $orderData['order_id'] ?? null;

        if (!$userId || empty($items)) {
            $this->warn('Purchase event missing user_id or items.');
            return;
        }

        // Buyer is a teacher — find them by ID or email
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User #{$userId} not found in certification DB, trying by email.");
            $email = $orderData['user_email'] ?? null;
            if ($email) {
                $user = User::where('email', $email)->first();
            }
        }

        if (!$user) {
            $this->error("Cannot find user for purchase fulfillment. user_id={$userId}");
            Log::error('Kafka purchase.completed: user not found', $orderData);
            return;
        }

        $linkedCount = 0;

        foreach ($items as $item) {
            $templateId = $item['template_id'] ?? null;

            if (!$templateId) {
                $this->warn('Item missing template_id, skipping.');
                continue;
            }

            $template = Template::find($templateId);
            if (!$template) {
                $this->warn("Template #{$templateId} not found, skipping.");
                continue;
            }

            // Check if already purchased
            $existing = PurchasedTemplate::where('user_id', $user->id)
                ->where('template_id', $template->id)
                ->first();

            if ($existing) {
                $this->info("User #{$user->id} already owns template #{$template->id}");
                continue;
            }

            PurchasedTemplate::create([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'order_id' => $orderId,
                'source' => 'marketplace',
                'purchased_at' => now(),
            ]);

            $linkedCount++;
            $this->info("Linked template #{$template->id} ({$template->title}) to user #{$user->id}");
        }

        Log::info('Kafka purchase.completed fulfilled', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'templates_linked' => $linkedCount,
        ]);

        $this->info("Purchase fulfillment complete: {$linkedCount} template(s) linked to buyer");
    }
}
