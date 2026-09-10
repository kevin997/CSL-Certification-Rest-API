<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingConsent extends Model
{
    use BelongsToEnvironment;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'environment_id',
        'sales_form_submission_id',
        'user_id',
        'channel',
        'status',
        'source',
        'terms_version',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function salesFormSubmission(): BelongsTo
    {
        return $this->belongsTo(SalesFormSubmission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists && $this->isDirty()) {
            throw new \LogicException('Marketing consent events are immutable. Append a new event instead.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        if ($this->exists) {
            throw new \LogicException('Marketing consent events cannot be deleted.');
        }

        return parent::delete();
    }

    public static function grant(
        SalesFormSubmission $submission,
        string $channel,
        string $source,
        string $termsVersion,
        CarbonInterface $at
    ): self {
        return static::record($submission, $channel, self::STATUS_GRANTED, $source, $termsVersion, $at);
    }

    public static function revoke(
        SalesFormSubmission $submission,
        string $channel,
        string $source,
        string $termsVersion,
        CarbonInterface $at
    ): self {
        return static::record($submission, $channel, self::STATUS_REVOKED, $source, $termsVersion, $at);
    }

    public static function latestStateFor(SalesFormSubmission $submission, string $channel): ?self
    {
        return static::query()
            ->where('environment_id', $submission->environment_id)
            ->where('sales_form_submission_id', $submission->id)
            ->where('channel', $channel)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function isGranted(SalesFormSubmission $submission, string $channel): bool
    {
        return static::latestStateFor($submission, $channel)?->status === self::STATUS_GRANTED;
    }

    private static function record(
        SalesFormSubmission $submission,
        string $channel,
        string $status,
        string $source,
        string $termsVersion,
        CarbonInterface $at
    ): self {
        $recordedAt = CarbonImmutable::instance($at)->utc();

        return static::create([
            'environment_id' => $submission->environment_id,
            'sales_form_submission_id' => $submission->id,
            'user_id' => $submission->user_id,
            'channel' => $channel,
            'status' => $status,
            'source' => $source,
            'terms_version' => $termsVersion,
            'granted_at' => $status === self::STATUS_GRANTED ? $recordedAt : null,
            'revoked_at' => $status === self::STATUS_REVOKED ? $recordedAt : null,
        ]);
    }
}
