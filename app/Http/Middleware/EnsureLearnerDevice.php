<?php

namespace App\Http\Middleware;

use App\Exceptions\DeviceLimitReachedException;
use App\Exceptions\DeviceRevokedException;
use App\Services\Devices\DeviceRegistrar;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the learner device slots on every learner request.
 *
 * Only learners consume slots; instructors and platform staff pass through.
 * A request without a device header is left to the controller (older clients,
 * service integrations and tests keep working); the web app always sends one.
 */
class EnsureLearnerDevice
{
    public function __construct(private readonly DeviceRegistrar $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->devices->enforced()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        $environmentId = $context instanceof EnvironmentContext && $context->environment
            ? (int) $context->environment->id
            : (int) ($request->hasSession() ? $request->session()->get('current_environment_id') : 0);

        if ($environmentId <= 0) {
            return $next($request);
        }

        try {
            $device = $this->devices->registerFromRequest($request, $user, $environmentId);
        } catch (DeviceLimitReachedException|DeviceRevokedException $exception) {
            return $exception->render($request);
        }

        if ($device) {
            $request->attributes->set('learner_device', $device);
        }

        return $next($request);
    }
}
