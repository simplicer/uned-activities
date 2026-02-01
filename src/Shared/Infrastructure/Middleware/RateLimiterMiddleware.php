<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Rate limiter middleware.
 *
 * Limits requests per IP address or API token.
 * Uses in-memory storage for development, Redis for production.
 */
final class RateLimiterMiddleware
{
    private const DEFAULT_LIMIT = 100; // requests per window
    private const DEFAULT_WINDOW = 60; // seconds
    private const STORAGE_KEY_PREFIX = 'rate_limit:';

    private array $memoryStore = [];

    public function __construct(
        private readonly int $requestsPerWindow = self::DEFAULT_LIMIT,
        private readonly int $windowSeconds = self::DEFAULT_WINDOW,
        private readonly ?\Redis $redis = null,
    ) {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $identifier = $this->getIdentifier($request);
        $key = self::STORAGE_KEY_PREFIX . $identifier;

        [$count, $resetAt] = $this->getRateLimitData($key);

        // Check if limit exceeded
        if ($count >= $this->requestsPerWindow) {
            return $this->createRateLimitResponse(
                $this->requestsPerWindow,
                $count,
                $resetAt
            );
        }

        // Increment counter
        $this->incrementCounter($key, $resetAt);

        // Add rate limit headers to response
        $response = $handler->handle($request);
        return $this->addRateLimitHeaders(
            $response,
            $this->requestsPerWindow,
            $count + 1,
            $resetAt
        );
    }

    /**
     * Get identifier for rate limiting (IP or token).
     */
    private function getIdentifier(Request $request): string
    {
        // Check for API token first
        $token = $this->extractToken($request);
        if ($token !== null) {
            return 'token:' . $token;
        }

        // Fall back to IP address
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR']
            ?? $serverParams['HTTP_X_FORWARDED_FOR']
            ?? 'unknown';

        // Handle multiple IPs (X-Forwarded-For)
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return 'ip:' . $ip;
    }

    /**
     * Extract API token from request.
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization header
        $auth = $request->getHeaderLine('Authorization');
        if (str_starts_with(strtolower($auth), 'bearer ')) {
            return substr($auth, 7);
        }

        // Check query parameter
        $params = $request->getQueryParams();
        return $params['token'] ?? $params['api_key'] ?? null;
    }

    /**
     * Get current rate limit data from storage.
     *
     * @return array{0: int, 1: int} [count, resetAt]
     */
    private function getRateLimitData(string $key): array
    {
        if ($this->redis !== null) {
            return $this->getFromRedis($key);
        }

        return $this->getFromMemory($key);
    }

    /**
     * Get data from Redis.
     *
     * @return array{0: int, 1: int}
     */
    private function getFromRedis(string $key): array
    {
        $data = $this->redis->hGetAll($key);

        if (empty($data)) {
            return [0, 0];
        }

        return [
            (int) $data['count'],
            (int) $data['reset_at'],
        ];
    }

    /**
     * Get data from memory.
     *
     * @return array{0: int, 1: int}
     */
    private function getFromMemory(string $key): array
    {
        if (!isset($this->memoryStore[$key])) {
            return [0, 0];
        }

        $data = $this->memoryStore[$key];

        // Check if window expired
        if (time() > $data['reset_at']) {
            unset($this->memoryStore[$key]);
            return [0, 0];
        }

        return [$data['count'], $data['reset_at']];
    }

    /**
     * Increment counter in storage.
     */
    private function incrementCounter(string $key, int $resetAt): void
    {
        if ($this->redis !== null) {
            $this->incrementInRedis($key, $resetAt);
            return;
        }

        $this->incrementInMemory($key, $resetAt);
    }

    private function incrementInRedis(string $key, int $resetAt): void
    {
        $pipe = $this->redis->multi();
        $pipe->hIncrBy($key, 'count', 1);
        $pipe->hSet($key, 'reset_at', $resetAt);
        $pipe->expire($key, $this->windowSeconds);
        $pipe->exec();
    }

    private function incrementInMemory(string $key, int $resetAt): void
    {
        if (!isset($this->memoryStore[$key])) {
            $this->memoryStore[$key] = [
                'count' => 0,
                'reset_at' => $resetAt > 0 ? $resetAt : time() + $this->windowSeconds
            ];
        }

        $this->memoryStore[$key]['count']++;
        if ($resetAt > 0) {
            $this->memoryStore[$key]['reset_at'] = $resetAt;
        }
    }

    /**
     * Create rate limit exceeded response.
     */
    private function createRateLimitResponse(int $limit, int $count, int $resetAt): Response
    {
        $response = new SlimResponse(429); // HTTP 429 Too Many Requests

        $response->getBody()->write(json_encode([
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retryAfter' => $resetAt - time(),
        ], JSON_THROW_ON_ERROR));

        return $this->addRateLimitHeaders($response, $limit, $count, $resetAt)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Add rate limit headers to response.
     */
    private function addRateLimitHeaders(Response $response, int $limit, int $count, int $resetAt): Response
    {
        $remaining = max(0, $limit - $count);
        $retryAfter = max(0, $resetAt - time());

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetAt)
            ->withHeader('Retry-After', (string) $retryAfter);
    }
}
