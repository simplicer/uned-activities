<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\Middleware\RateLimiterMiddleware;
use Slim\Psr7\Response;

#[CoversClass(RateLimiterMiddleware::class)]
final class RateLimiterBypassTest extends TestCase
{
    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200);
            }
        };
    }

    private function requestWithBearer(string $bearer): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '203.0.113.7']);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer ' . $bearer);

        return $request;
    }

    public function testRotatingBearerStringsCanNoLongerEvadeTheIpBucket(): void
    {
        // Regression (rate limit bypass via unverified bearer as bucket key):
        // each unique bearer used to open a fresh bucket, so an unauthenticated
        // caller rotating header bytes was never limited.
        $middleware = new RateLimiterMiddleware(requestsPerWindow: 5, windowSeconds: 60);
        $handler = $this->handler();

        $statuses = [];
        for ($i = 0; $i < 10; $i++) {
            $response = $middleware($this->requestWithBearer('forged-token-' . $i), $handler);
            $statuses[] = $response->getStatusCode();
        }

        self::assertContains(429, $statuses, 'IP bucket must cap requests even with rotating bearers');
        self::assertSame(5, count(array_filter($statuses, fn ($s) => $s === 200)), 'exactly limit requests pass');
    }

    public function testSameBearerIsStillLimited(): void
    {
        $middleware = new RateLimiterMiddleware(requestsPerWindow: 5, windowSeconds: 60);
        $handler = $this->handler();

        $statuses = [];
        for ($i = 0; $i < 7; $i++) {
            $response = $middleware($this->requestWithBearer('same-token'), $handler);
            $statuses[] = $response->getStatusCode();
        }

        self::assertSame([200, 200, 200, 200, 200, 429, 429], $statuses);
    }

    public function testDistinctIpsAreIndependent(): void
    {
        $middleware = new RateLimiterMiddleware(requestsPerWindow: 1, windowSeconds: 60);
        $handler = $this->handler();

        $first = $middleware($this->requestFrom('198.51.100.1'), $handler);
        $second = $middleware($this->requestFrom('198.51.100.2'), $handler);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
    }

    private function requestFrom(string $ip): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => $ip]);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('');

        return $request;
    }
}
