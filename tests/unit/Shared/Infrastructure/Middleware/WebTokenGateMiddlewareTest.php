<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Firebase\JWT\JWT;
use Shared\Infrastructure\Middleware\WebTokenGateMiddleware;

#[CoversClass(WebTokenGateMiddleware::class)]
final class WebTokenGateMiddlewareTest extends TestCase
{
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->willReturnSelf();

        $this->handler->method('handle')->willReturn($response);
    }

    #[Test]
    #[TestDox('allows protected requests with valid JWT')]
    public function itAllowsWithValidToken(): void
    {
        $middleware = new WebTokenGateMiddleware('test-secret');
        $token = JWT::encode(['sub' => 'user-1', 'exp' => time() + 3600], 'test-secret', 'HS256');
        $this->mockRequest('/v1/profile', 'POST', $token);

        $result = $middleware($this->request, $this->handler);

        $this->assertSame(200, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('blocks protected requests without token')]
    public function itBlocksWithoutToken(): void
    {
        $middleware = new WebTokenGateMiddleware('test-secret');
        $this->mockRequest('/v1/profile', 'GET', null);

        $result = $middleware($this->request, $this->handler);

        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('blocks protected requests with invalid token')]
    public function itBlocksWithInvalidToken(): void
    {
        $middleware = new WebTokenGateMiddleware('test-secret');
        $this->mockRequest('/v1/profile', 'GET', 'invalid-token');

        $result = $middleware($this->request, $this->handler);

        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('allows public GET routes without token')]
    public function itAllowsWhitelistedPaths(): void
    {
        $middleware = new WebTokenGateMiddleware('test-secret');
        $this->mockRequest('/v1/activities', 'GET', null);

        $result = $middleware($this->request, $this->handler);

        $this->assertSame(200, $result->getStatusCode());
    }

    private function mockRequest(string $path, string $method, ?string $token): void
    {
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $this->request->method('getUri')->willReturn($uri);
        $this->request->method('getMethod')->willReturn($method);

        $this->request->method('getHeaderLine')->willReturn(
            $token !== null ? 'Bearer ' . $token : ''
        );
    }
}
