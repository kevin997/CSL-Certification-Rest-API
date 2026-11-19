<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\LearnerDevice;
use App\Models\User;
use App\Services\Devices\DeviceRegistrar;
use App\Support\EffectiveAuthContext;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Device slots for a learner.
 *
 * Learners see their own list read-only. Instructors and academy owners can
 * list and revoke the devices of a learner in their own academy only.
 */
class LearnerDeviceController extends Controller
{
    public function __construct(private readonly DeviceRegistrar $devices) {}

    /**
     * The signed-in learner's own devices.
     */
    public function mine(Request $request): JsonResponse
    {
        $environmentId = $this->environmentId($request);
        abort_if($environmentId === null, 403, 'No academy selected.');

        return $this->payload(
            $environmentId,
            (int) $request->user()->id,
            $request->header((string) config('devices.header', 'X-Device-Id')),
        );
    }

    /**
     * Devices of one learner, for an instructor managing that academy.
     */
    public function index(Request $request, int $userId): JsonResponse
    {
        $environmentId = $this->authorizeManagement($request, $userId);

        return $this->payload($environmentId, $userId);
    }

    /**
     * Revoke one device so the learner can register another.
     */
    public function destroy(Request $request, int $userId, int $deviceId): JsonResponse
    {
        $environmentId = $this->authorizeManagement($request, $userId);

        $device = LearnerDevice::query()
            ->forSlot($environmentId, $userId)
            ->findOrFail($deviceId);

        $device = $this->devices->revoke($device, $request->user(), 'instructor');

        $this->audit($request, $environmentId, $userId, 'device.revoked', $device);

        return $this->payload($environmentId, $userId);
    }

    private function payload(int $environmentId, int $userId, ?string $currentUuid = null): JsonResponse
    {
        $devices = $this->devices->listFor($environmentId, $userId);

        if ($currentUuid !== null && $currentUuid !== '') {
            $current = LearnerDevice::query()
                ->forSlot($environmentId, $userId)
                ->where('uuid', $currentUuid)
                ->value('id');

            foreach ($devices as &$device) {
                $device['is_current'] = $current !== null && (int) $device['id'] === (int) $current;
            }
            unset($device);
        }

        return response()->json([
            'data' => $devices,
            'meta' => [
                'max_devices' => $this->devices->maxActive(),
                'active_devices' => $this->devices->activeCount($environmentId, $userId),
                'enforced' => $this->devices->enforced(),
            ],
        ]);
    }

    /**
     * @return int The resolved environment id.
     */
    private function authorizeManagement(Request $request, int $userId): int
    {
        $environmentId = $this->environmentId($request);
        abort_if($environmentId === null, 403, 'No academy selected.');

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $role = EffectiveAuthContext::for($actor, $environmentId)['role'];

        abort_unless(
            in_array($role, (array) config('devices.manager_roles', []), true),
            403,
            'You are not allowed to manage this learner.',
        );

        $environment = Environment::query()->find($environmentId);

        $belongs = $environment?->owner_id === $userId
            || EnvironmentUser::query()
                ->where('environment_id', $environmentId)
                ->where('user_id', $userId)
                ->exists();

        abort_unless($belongs, 404, 'Learner not found in this academy.');

        return (int) $environmentId;
    }

    private function environmentId(Request $request): ?int
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->environment) {
            return (int) $context->environment->id;
        }

        $sessionId = $request->hasSession() ? $request->session()->get('current_environment_id') : null;

        return $sessionId ? (int) $sessionId : null;
    }

    private function audit(Request $request, int $environmentId, int $userId, string $action, LearnerDevice $device): void
    {
        try {
            AuditLog::create([
                'log_type' => AuditLog::TYPE_API,
                'source' => 'devices',
                'action' => $action,
                'entity_type' => 'learner_device',
                'entity_id' => $device->id,
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'metadata' => [
                    'label' => $device->label,
                    'platform' => $device->platform,
                    'browser' => $device->browser,
                    'revoked_by' => $request->user()?->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 191),
                'status' => AuditLog::STATUS_SUCCESS,
            ]);
        } catch (\Throwable $exception) {
            // A failed audit write must never leave a device unrevoked.
            Log::warning('learner_device_audit_failed', [
                'action' => $action,
                'device_id' => $device->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
