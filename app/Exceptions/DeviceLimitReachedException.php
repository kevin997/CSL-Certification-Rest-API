<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The learner already uses every device slot in this academy.
 *
 * Rendered as a JSON 403 so the SPA can explain what to do (ask the
 * instructor to free a device) instead of showing a generic login failure.
 */
class DeviceLimitReachedException extends RuntimeException
{
    public const CODE = 'device_limit_reached';

    public function __construct(public readonly int $maxDevices)
    {
        parent::__construct(
            "This account already uses its {$maxDevices} learning devices. "
            .'Ask your instructor to remove one before signing in on a new device.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => self::CODE,
            'message' => $this->getMessage(),
            'max_devices' => $this->maxDevices,
        ], 403);
    }
}
