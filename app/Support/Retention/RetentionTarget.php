<?php

namespace App\Support\Retention;

/**
 * One eligible recipient for one retention scenario. Plain data — phone/email
 * are raw here (phone gets normalised to E.164 at send time). `context` holds
 * values for message placeholders (e.g. name, course, progress) plus routing
 * hints (`environment_id`, `environment_domain`) used by RetentionLinks and by
 * the job to target push notifications / resolve email branding.
 *
 * Ported from shopikat's RetentionTarget. Unlike shopikat (WhatsApp-only,
 * phone required), KURSA gives every user an email address, so phone is
 * optional and `email` is a first-class channel. `pushUserId` optionally
 * routes a best-effort web push alongside WhatsApp/email.
 */
class RetentionTarget
{
    /**
     * @param  array<string, string|int|float|null>  $context
     */
    public function __construct(
        public string $recipientType,
        public string $recipientId,
        public ?string $phone,
        public ?string $email,
        public string $name,
        public ?int $pushUserId = null,
        public array $context = [],
    ) {}
}
