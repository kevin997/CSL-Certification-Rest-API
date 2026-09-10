<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyMarketingServiceSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.marketing_service.signing_secret');
        $timestamp = $request->header('X-Marketing-Timestamp');
        $nonce = $request->header('X-Marketing-Nonce');
        $providedSignature = $request->header('X-Marketing-Signature');

        if (! is_string($secret) || $secret === ''
            || ! is_string($timestamp) || ! ctype_digit($timestamp)
            || ! is_string($nonce) || $nonce === '' || strlen($nonce) > 128
            || ! is_string($providedSignature) || ! preg_match('/^[a-f0-9]{64}$/i', $providedSignature)) {
            return $this->unauthorized();
        }

        $timestamp = (int) $timestamp;
        $now = now()->getTimestamp();
        $clockSkew = max(1, (int) config('services.marketing_service.clock_skew_seconds', 300));

        if (abs($now - $timestamp) > $clockSkew) {
            return $this->unauthorized();
        }

        $canonical = implode("\n", [
            $request->getMethod(),
            $request->getPathInfo(),
            (string) $timestamp,
            $nonce,
            hash('sha256', $request->getContent()),
        ]);
        $expectedSignature = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return $this->unauthorized();
        }

        // Cache::add is atomic for Laravel's supported shared cache drivers. A
        // digest keeps a caller-supplied nonce out of cache keys and logs.
        $nonceKey = 'marketing-service:nonce:'.hash('sha256', $nonce);
        $ttl = max(1, ($timestamp + $clockSkew) - $now);

        if (! Cache::add($nonceKey, true, now()->addSeconds($ttl))) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
