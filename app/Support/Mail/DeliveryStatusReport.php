<?php

namespace App\Support\Mail;

/**
 * Reads the hard bounces out of a bounce email (an RFC 3464 delivery status
 * notification, as MailChannels and every Postfix relay send them).
 *
 * Only the machine-readable message/delivery-status part is trusted; the
 * human text around it differs per relay and language. Only statuses meaning
 * "this address does not exist or is disabled" count as hard. A 5.7.x is a
 * policy or reputation refusal and a 5.2.2 a full mailbox — suppressing those
 * would silence real learners because of our own sending, not their address.
 */
final class DeliveryStatusReport
{
    /** Permanent "bad address" statuses: unknown mailbox, bad domain, disabled mailbox. */
    private const HARD_STATUS = '/^5\.(1\.\d+|2\.1)$/';

    /**
     * @return list<HardBounce>
     */
    public static function hardBounces(string $rawMessage): array
    {
        $part = self::deliveryStatusPart($rawMessage);
        if ($part === null) {
            return [];
        }

        $bounces = [];
        foreach (self::fieldGroups($part) as $fields) {
            $recipient = self::address($fields['final-recipient'] ?? $fields['original-recipient'] ?? '');
            $status = trim($fields['status'] ?? '');

            if ($recipient === null
                || strtolower(trim($fields['action'] ?? '')) !== 'failed'
                || preg_match(self::HARD_STATUS, $status) !== 1) {
                continue;
            }

            $bounces[] = new HardBounce($recipient, $status, isset($fields['diagnostic-code']) ? trim($fields['diagnostic-code']) : null);
        }

        return $bounces;
    }

    public static function isDeliveryStatusReport(string $rawMessage): bool
    {
        return self::deliveryStatusPart($rawMessage) !== null;
    }

    private static function deliveryStatusPart(string $rawMessage): ?string
    {
        $normalized = str_replace("\r\n", "\n", $rawMessage);

        if (preg_match('~^content-type:\s*message/delivery-status[^\n]*\n(?:[ \t][^\n]*\n)*(?:[a-z-]+:[^\n]*\n(?:[ \t][^\n]*\n)*)*\n(.*?)(?:\n--|\z)~ims', $normalized, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * The per-message group and each per-recipient group, as lower-cased
     * field name => unfolded value.
     *
     * @return list<array<string, string>>
     */
    private static function fieldGroups(string $part): array
    {
        $groups = [];
        foreach (preg_split('/\n\s*\n/', trim($part)) ?: [] as $block) {
            $unfolded = preg_replace('/\n[ \t]+/', ' ', $block) ?? $block;
            $fields = [];
            foreach (explode("\n", $unfolded) as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $fields[strtolower(trim($name))] = $value;
                }
            }
            $groups[] = $fields;
        }

        return $groups;
    }

    /**
     * "rfc822; someone@example.com" to the address, or null if it is not one.
     */
    private static function address(string $value): ?string
    {
        $address = trim(str_contains($value, ';') ? explode(';', $value, 2)[1] : $value, " \t<>");

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false ? mb_strtolower($address) : null;
    }
}
