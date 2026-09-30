<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An address Kursa will not send to again, because mail to it hard-bounced.
 *
 * Repeatedly mailing a mailbox that does not exist is what gets a sending
 * domain throttled or blocklisted, and the weekly digests, retention mail and
 * campaigns would otherwise retry it forever.
 */
class EmailSuppression extends Model
{
    use HasFactory;

    public const REASON_HARD_BOUNCE = 'hard_bounce';

    protected $fillable = ['email', 'reason', 'status_code', 'diagnostic', 'source', 'suppressed_at'];

    protected function casts(): array
    {
        return ['suppressed_at' => 'datetime'];
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isSuppressed(string $email): bool
    {
        return static::query()->where('email', static::normalize($email))->exists();
    }

    /**
     * The subset of the given addresses that are suppressed, normalized.
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    public static function suppressedAmong(array $emails): array
    {
        $normalized = array_values(array_unique(array_map(static::normalize(...), $emails)));

        return $normalized === [] ? [] : static::query()->whereIn('email', $normalized)->pluck('email')->all();
    }

    /**
     * Record a hard bounce. The first bounce is kept as the evidence; a later
     * report for the same address does not overwrite it.
     */
    public static function recordHardBounce(string $email, ?string $statusCode, ?string $diagnostic, string $source, CarbonInterface $at): self
    {
        return static::query()->firstOrCreate(
            ['email' => static::normalize($email)],
            [
                'reason' => self::REASON_HARD_BOUNCE,
                'status_code' => $statusCode,
                'diagnostic' => $diagnostic === null ? null : mb_substr($diagnostic, 0, 1000),
                'source' => $source,
                'suppressed_at' => $at,
            ],
        );
    }
}
