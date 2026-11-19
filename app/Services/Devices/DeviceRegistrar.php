<?php

namespace App\Services\Devices;

use App\Enums\UserRole;
use App\Exceptions\DeviceLimitReachedException;
use App\Exceptions\DeviceRevokedException;
use App\Models\EnvironmentUser;
use App\Models\LearnerDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place that decides whether a device may use a learner account.
 *
 * Slots are scoped per (environment, user): a learner enrolled in three
 * academies legitimately has two devices in each one, and one academy's
 * instructor cannot revoke another academy's device.
 */
class DeviceRegistrar
{
    public function maxActive(): int
    {
        return max(1, (int) config('devices.max_active', 2));
    }

    public function enforced(): bool
    {
        return (bool) config('devices.enforced', true);
    }

    /**
     * Devices that currently occupy a slot. A device idle for longer than the
     * configured window stops counting, so a lost phone frees its slot.
     */
    public function activeQuery(int $environmentId, int $userId)
    {
        $cutoff = now()->subDays(max(1, (int) config('devices.idle_expiry_days', 180)));

        return LearnerDevice::query()
            ->forSlot($environmentId, $userId)
            ->active()
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '>=', $cutoff);
            });
    }

    public function activeCount(int $environmentId, int $userId): int
    {
        return $this->activeQuery($environmentId, $userId)->count();
    }

    /**
     * Register or refresh the device carried by the request.
     *
     * Returns null when the header is absent or malformed, or when the user is
     * not a learner in this academy. Missing headers are tolerated for older
     * clients and service integrations; every request from the web app sends
     * one.
     *
     * @throws DeviceLimitReachedException
     * @throws DeviceRevokedException
     */
    public function registerFromRequest(Request $request, User $user, int $environmentId): ?LearnerDevice
    {
        if (! $this->enforced()) {
            return null;
        }

        $uuid = $this->deviceUuid($request);

        if ($uuid === null) {
            return null;
        }

        $role = $this->role($user, $environmentId);

        if (! in_array($role, (array) config('devices.learner_roles', []), true)) {
            return null;
        }

        return $this->registerOrTouch(
            $user,
            $environmentId,
            $uuid,
            $request->header('X-Device-Name'),
            $request->userAgent(),
            $request->ip(),
        );
    }

    /**
     * @throws DeviceLimitReachedException
     * @throws DeviceRevokedException
     */
    public function registerOrTouch(
        User $user,
        int $environmentId,
        string $uuid,
        ?string $label,
        ?string $userAgent,
        ?string $ip,
    ): LearnerDevice {
        if (! Str::isUuid($uuid)) {
            throw new DeviceRevokedException;
        }

        $lock = Cache::lock("learner-device:{$environmentId}:{$user->id}", 10);

        return $lock->block(5, function () use ($user, $environmentId, $uuid, $label, $userAgent, $ip) {
            return DB::transaction(function () use ($user, $environmentId, $uuid, $label, $userAgent, $ip) {
                $device = LearnerDevice::query()
                    ->forSlot($environmentId, $user->id)
                    ->where('uuid', $uuid)
                    ->lockForUpdate()
                    ->first();

                $now = now();
                $meta = $this->describe($userAgent, $label);
                $attributes = [
                    'label' => $meta['label'],
                    'platform' => $meta['platform'],
                    'browser' => $meta['browser'],
                    'device_type' => $meta['device_type'],
                    'last_seen_at' => $now,
                    'last_ip_hash' => $this->hashIp($ip),
                    'last_user_agent' => Str::limit((string) $userAgent, 1000, ''),
                ];

                if ($device) {
                    if ($device->status === LearnerDevice::STATUS_REVOKED) {
                        throw DeviceRevokedException::for($device);
                    }

                    $cutoff = now()->subDays(max(1, (int) config('devices.idle_expiry_days', 180)));
                    $currentlyActive = $device->last_seen_at === null
                        || $device->last_seen_at->gte($cutoff);

                    // A device already using a slot is always allowed back in.
                    // Only a device that fell out of the active window must
                    // compete for a free slot.
                    if (! $currentlyActive) {
                        $otherActive = $this->activeQuery($environmentId, $user->id)
                            ->whereKeyNot($device->id)
                            ->count();

                        if ($otherActive >= $this->maxActive()) {
                            throw new DeviceLimitReachedException($this->maxActive());
                        }
                    }

                    $device->fill([...$attributes, 'status' => LearnerDevice::STATUS_ACTIVE])->save();

                    return $device;
                }

                if ($this->activeQuery($environmentId, $user->id)->count() >= $this->maxActive()) {
                    throw new DeviceLimitReachedException($this->maxActive());
                }

                return LearnerDevice::create([
                    ...$attributes,
                    'environment_id' => $environmentId,
                    'user_id' => $user->id,
                    'uuid' => $uuid,
                    'status' => LearnerDevice::STATUS_ACTIVE,
                    'first_seen_at' => $now,
                ]);
            });
        });
    }

    /**
     * Free a slot. The row is kept so the instructor still sees the history.
     */
    public function revoke(LearnerDevice $device, ?User $actor, string $reason = 'instructor'): LearnerDevice
    {
        if ($device->status !== LearnerDevice::STATUS_REVOKED) {
            $device->forceFill([
                'status' => LearnerDevice::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $actor?->id,
                'revoke_reason' => $reason,
            ])->save();
        }

        return $device->refresh();
    }

    /**
     * Devices for one learner, newest activity first, without secrets.
     */
    public function listFor(int $environmentId, int $userId, bool $includeRevoked = true): array
    {
        $cutoff = now()->subDays(max(1, (int) config('devices.idle_expiry_days', 180)));

        return LearnerDevice::query()
            ->forSlot($environmentId, $userId)
            ->when(! $includeRevoked, fn ($query) => $query->active())
            ->orderByRaw('COALESCE(last_seen_at, first_seen_at) DESC')
            ->get()
            ->map(fn (LearnerDevice $device) => [
                'id' => $device->id,
                'label' => $device->label,
                'platform' => $device->platform,
                'browser' => $device->browser,
                'device_type' => $device->device_type,
                'status' => $device->status,
                'first_seen_at' => $device->first_seen_at?->toIso8601String(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'revoked_at' => $device->revoked_at?->toIso8601String(),
                'revoke_reason' => $device->revoke_reason,
                'is_idle' => $device->status === LearnerDevice::STATUS_ACTIVE
                    && ($device->last_seen_at === null || $device->last_seen_at->lt($cutoff)),
            ])
            ->values()
            ->all();
    }

    private function deviceUuid(Request $request): ?string
    {
        $header = (string) config('devices.header', 'X-Device-Id');
        $value = trim((string) $request->header($header, ''));

        return $value !== '' ? $value : null;
    }

    private function role(User $user, int $environmentId): string
    {
        $role = $user->role;

        $userRole = $role instanceof UserRole ? $role->value : (string) $role;

        $environmentRole = EnvironmentUser::query()
            ->where('environment_id', $environmentId)
            ->where('user_id', $user->id)
            ->value('role');

        if ($environmentRole instanceof UserRole) {
            return $environmentRole->value;
        }

        return is_string($environmentRole) && $environmentRole !== ''
            ? $environmentRole
            : $userRole;
    }

    /**
     * Turn a user agent into a label a learner and an instructor recognise.
     *
     * Deliberately coarse: we never try to fingerprint the machine.
     *
     * @return array{label: string, platform: string, browser: string, device_type: string}
     */
    private function describe(?string $userAgent, ?string $label): array
    {
        $ua = (string) $userAgent;

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad'), str_contains($ua, 'iPod') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X'), str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };

        $deviceType = match (true) {
            str_contains($ua, 'iPad'), str_contains($ua, 'Tablet') => LearnerDevice::TYPE_TABLET,
            str_contains($ua, 'Mobi'), str_contains($ua, 'Android'), str_contains($ua, 'iPhone') => LearnerDevice::TYPE_MOBILE,
            $platform !== 'Unknown' => LearnerDevice::TYPE_DESKTOP,
            default => LearnerDevice::TYPE_UNKNOWN,
        };

        $cleanLabel = trim((string) $label);

        return [
            'label' => $cleanLabel !== '' ? Str::limit($cleanLabel, 120, '') : "{$browser} on {$platform}",
            'platform' => $platform,
            'browser' => $browser,
            'device_type' => $deviceType,
        ];
    }

    private function hashIp(?string $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
