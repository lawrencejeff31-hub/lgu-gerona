<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Responses\ApiResponse;
use App\Services\AuditLogger;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1')
    {
        $key = $this->resolveRequestSignature($request);
        
        // Check if rate limit is exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->logRateLimitExceeded($request, $key);
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $key, $maxAttempts);
    }

    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            // Authenticated users: rate limit per user
            return 'rate_limit:user:' . $user->id . ':' . $request->route()->getName();
        }
        
        // Unauthenticated users: rate limit per IP
        return 'rate_limit:ip:' . $request->ip() . ':' . $request->route()->getName();
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts)
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        return ApiResponse::error(
            'Too many requests. Please try again later.',
            429,
            [
                'retry_after' => $retryAfter,
                'max_attempts' => $maxAttempts,
            ]
        )->header('Retry-After', $retryAfter)
         ->header('X-RateLimit-Limit', $maxAttempts)
         ->header('X-RateLimit-Remaining', 0);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders($response, string $key, int $maxAttempts)
    {
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $retryAfter = RateLimiter::availableIn($key);

        return $response->header('X-RateLimit-Limit', $maxAttempts)
                       ->header('X-RateLimit-Remaining', max(0, $remaining))
                       ->header('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
    }

    /**
     * Log rate limit exceeded event
     */
    protected function logRateLimitExceeded(Request $request, string $key): void
    {
        $user = $request->user();
        
        AuditLogger::logSecurityEvent('rate_limit_exceeded', null, $user, [
            'rate_limit_key' => $key,
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);
    }
}