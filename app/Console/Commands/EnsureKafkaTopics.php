<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\Producer;

/**
 * Report whether Kafka is reachable and whether the topics this app uses exist.
 *
 * This used to build an RdKafka\Admin\Client and RdKafka\Admin\NewTopic.
 * Neither exists: php-rdkafka exposes no admin API at all -- 6.0.5 ships
 * Producer, Consumer, Conf, Metadata and friends, and nothing more. The absent
 * class raised an Error, which catch (\Exception) does not catch, so the
 * command failed on every invocation. The kafka-consumer entrypoint retries it
 * 30 times two seconds apart, so each consumer start spent a minute failing and
 * wrote 31 uncaught errors to the log before continuing anyway.
 *
 * Creating topics was never this command's job and never could be. The broker
 * runs with KAFKA_AUTO_CREATE_TOPICS_ENABLE=true, so a topic materialises on
 * first use. What the entrypoint actually needs -- and what it retries for --
 * is to know when Kafka is reachable. That is what this reports: a non-zero
 * exit means "broker not up yet, keep waiting", which is the only condition
 * retrying can fix.
 */
class EnsureKafkaTopics extends Command
{
    protected $signature = 'kafka:ensure-topics';

    protected $description = 'Verify Kafka is reachable and report on required topics';

    /**
     * Topics this application produces to or consumes from.
     *
     * @var list<string>
     */
    private array $requiredTopics = [
        'marketplace.purchase.completed',
        'template.publish_to_marketplace',
    ];

    /**
     * How long to wait for the broker to answer a metadata request.
     */
    private const METADATA_TIMEOUT_MS = 5000;

    public function handle(): int
    {
        if (! extension_loaded('rdkafka')) {
            $this->error('The rdkafka extension is not loaded; cannot talk to Kafka.');

            return self::FAILURE;
        }

        // Same source as KafkaProducerService and ConsumeKafkaEvents. This
        // command used to read env() directly, with a different default port,
        // so it could disagree with the consumer it runs in front of.
        $brokers = (string) config('services.kafka.brokers');

        if ($brokers === '') {
            $this->error('No Kafka brokers configured (services.kafka.brokers).');

            return self::FAILURE;
        }

        $this->info("Connecting to Kafka: {$brokers}");

        try {
            $topics = $this->fetchTopicNames($brokers);
        } catch (\Throwable $e) {
            // Reachability is the retryable condition, so this is the one exit
            // code the entrypoint's loop should keep waiting on.
            $this->error("Kafka is not reachable: {$e->getMessage()}");

            return self::FAILURE;
        }

        $missing = array_values(array_diff($this->requiredTopics, $topics));

        if ($missing === []) {
            $this->info('All required topics exist.');

            return self::SUCCESS;
        }

        // Auto-creation handles this on first produce, and nothing this command
        // can do would create them, so failing here would only make the
        // entrypoint wait out its retries for no gain.
        $this->warn('Topics not present yet, they will be created on first use: '.implode(', ', $missing));
        Log::warning('Kafka topics missing at startup', ['topics' => $missing, 'brokers' => $brokers]);

        return self::SUCCESS;
    }

    /**
     * The topic names the broker currently reports.
     *
     * @return list<string>
     */
    private function fetchTopicNames(string $brokers): array
    {
        $conf = new Conf;
        $conf->set('metadata.broker.list', $brokers);

        $metadata = (new Producer($conf))->getMetadata(true, null, self::METADATA_TIMEOUT_MS);

        $names = [];
        foreach ($metadata->getTopics() as $topic) {
            $names[] = $topic->getTopic();
        }

        return $names;
    }
}
