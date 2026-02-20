<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KafkaProducerService
{
    private string $brokers;

    public function __construct()
    {
        $this->brokers = config('services.kafka.brokers', 'localhost:29092');
    }

    /**
     * Publish a message to a Kafka topic.
     */
    public function publish(string $topic, array $message, ?string $key = null): bool
    {
        $payload = json_encode($message);

        if (extension_loaded('rdkafka')) {
            return $this->publishViaRdKafka($topic, $payload, $key);
        }

        return $this->publishViaOutbox($topic, $payload, $key);
    }

    private function publishViaRdKafka(string $topic, string $payload, ?string $key): bool
    {
        try {
            $conf = new \RdKafka\Conf();
            $conf->set('metadata.broker.list', $this->brokers);
            $conf->set('socket.timeout.ms', '5000');
            $conf->set('queue.buffering.max.ms', '100');

            $producer = new \RdKafka\Producer($conf);
            $topicObj = $producer->newTopic($topic);
            $topicObj->produce(RD_KAFKA_PARTITION_UA, 0, $payload, $key);
            $producer->poll(0);

            $result = $producer->flush(5000);
            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                Log::error("Kafka flush failed for topic {$topic}");
                return false;
            }

            Log::info("Kafka message published to {$topic}", ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            Log::error("Kafka publish failed: {$e->getMessage()}", ['topic' => $topic]);
            return false;
        }
    }

    /**
     * Fallback: store to outbox table for cron-based relay.
     */
    private function publishViaOutbox(string $topic, string $payload, ?string $key): bool
    {
        Log::info("Kafka event (outbox)", ['topic' => $topic, 'key' => $key]);

        try {
            DB::table('kafka_outbox')->insert([
                'topic' => $topic,
                'message_key' => $key,
                'payload' => $payload,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Kafka outbox insert failed: {$e->getMessage()}");
        }

        return true;
    }

    /**
     * Publish a template.publish_to_marketplace event.
     */
    public function publishTemplateToMarketplace(array $data): bool
    {
        return $this->publish('template.publish_to_marketplace', [
            'event' => 'template.publish_to_marketplace',
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ], (string) ($data['template_id'] ?? ''));
    }
}
