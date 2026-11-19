<?php

namespace App\Exceptions;

use App\Models\LearnerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * This device slot was revoked by an instructor.
 *
 * The learner must ask their instructor to allow the device again; clearing
 * storage and retrying would only create a new slot (and hit the limit).
 */
class DeviceRevokedException extends RuntimeException
{
    public const CODE = 'device_revoked';

    public function __construct(public readonly ?int $deviceId = null)
    {
        parent::__construct(
            'This device was removed by your instructor. '
            .'Ask them to allow it again if you need to use it.'
        );
    }

    public static function for(LearnerDevice $device): self
    {
        return new self($device->id);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => self::CODE,
            'message' => $this->getMessage(),
        ], 401);
    }
}
