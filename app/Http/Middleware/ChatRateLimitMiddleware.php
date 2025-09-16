<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ChatRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'messages'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Define rate limits based on type
        $limits = [
            'messages' => [
                'max_attempts' => 60, // 60 messages per minute
                'decay_minutes' => 1,
            ],
            'typing' => [
                'max_attempts' => 30, // 30 typing events per minute
                'decay_minutes' => 1,
            ],
        ];

        $limit = $limits[$type] ?? $limits['messages'];
        $key = "chat_{$type}:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, $limit['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'Too many chat requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        RateLimiter::hit($key, $limit['decay_minutes'] * 60);

        return $next($request);
    }
}
