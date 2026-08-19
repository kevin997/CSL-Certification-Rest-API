<?php

namespace Tests\Feature\StaticAnalysis;

use Tests\TestCase;

/**
 * php-rdkafka has no admin API, in any version.
 *
 * The extension ships RdKafka, RdKafka\Producer, RdKafka\Consumer,
 * RdKafka\KafkaConsumer, RdKafka\Conf, RdKafka\TopicConf, RdKafka\Metadata and
 * their companions -- and nothing under RdKafka\Admin. EnsureKafkaTopics was
 * written against RdKafka\Admin\Client and RdKafka\Admin\NewTopic, so it raised
 * "Class not found" on every run. That is an Error, not an Exception, so the
 * command's own catch (\Exception) never caught it, and the kafka-consumer
 * entrypoint retried it thirty times before starting anyway.
 *
 * A reference to that namespace cannot be found by any test that needs a live
 * broker, because it fails before reaching one -- hence a source scan.
 */
class RdKafkaAdminApiTest extends TestCase
{
    public function test_no_code_references_the_nonexistent_rdkafka_admin_namespace(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Tokenised rather than grepped: comments are allowed to name the
            // namespace when explaining why it must not be used, and this file
            // and EnsureKafkaTopics both do.
            if ($this->referencesAdminNamespaceInCode(file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "php-rdkafka provides no RdKafka\\Admin namespace; these files would fatal at runtime:\n".implode("\n", $offenders)
        );
    }

    /**
     * Whether the source references the admin namespace outside of a comment.
     */
    private function referencesAdminNamespaceInCode(string $source): bool
    {
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = is_array($token) ? $token[1] : $token;

            if (str_contains($text, 'RdKafka\\Admin\\')) {
                return true;
            }
        }

        return false;
    }
}
