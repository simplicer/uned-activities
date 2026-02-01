<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\Middleware\WebTokenGateMiddleware;

#[CoversClass(WebTokenGateMiddleware::class)]
final class WebTokenGateMiddlewareTest extends TestCase
{
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test tokens in environment
        $_ENV['API_TOKENS'] = 'test-token-1,test-token-2';

        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->willReturnSelf();

        $this->handler->method('handle')->willReturn($response);
    }

    #[Test]
    #[TestDox('allows requests with valid token')]
    public function itAllowsWithValidToken(): void
    {
        // Arrange
        $middleware = new WebTokenGateMiddleware();
        $this->mockRequest('/activities', 'test-token-1');

        // Act
        $result = $middleware($this->request, $this->handler);

        // Assert
        $this->assertSame(200, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('blocks requests without token')]
    public function itBlocksWithoutToken(): void
    {
        // Arrange
        $middleware = new WebTokenGateMiddleware();
        $this->mockRequest('/activities', null);

        // Act
        $result = $middleware($this->request, $this->handler);

        // Assert
        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('blocks requests with invalid token')]
    public function itBlocksWithInvalidToken(): void
    {
        // Arrange
        $middleware = new WebTokenGateMiddleware();
        $this->mockRequest('/activities', 'invalid-token');

        // Act
        $result = $middleware($this->request, $this->handler);

        // Assert
        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('allows whitelisted paths without token')]
    public function itAllowsWhitelistedPaths(): void
    {
        // Arrange
        $middleware = new WebTokenGateMiddleware();
        $this->mockRequest('/status', null);

        // Act
        $result = $middleware($this->request, $this->handler);

        // Assert
        $this->assertSame(200, $result->getStatusCode());
    }

    #[Test]
    #[TestDox('accepts token from X-API-Token header')]
    public function itAcceptsTokenFromCustomHeader(): void
    {
        // Arrange
        $middleware = new WebTokenGateMiddleware();
        $this->mockRequest('/activities', 'test-token-1', useCustomHeader: true);

        // Act
        $result = $middleware($this->request, $this->handler);

        // Assert
        $this->assertSame(200, $result->getStatusCode());
    }

    private function mockRequest(string $path, ?string $token, bool $useCustomHeader = false): void
    {
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $this->request->method('getUri')->willReturn($uri);

        if ($token !== null) {
            if ($useCustomHeader) {
                $this->request->method('getHeaderLine')
                    ->willReturnMap([
                        ['X-API-Token', $token],
                        ['Authorization', ''],
                    ]);
            } else {
                $this->request->method('getHeaderLine')
                    ->willReturnMap([
                        ['X-API-Token', ''],
                        ['Authorization', 'Bearer ' . $token],
                    ]);
            }
        } else {
            $this->request->method('getHeaderLine')->willReturn('');
        }

        $this->request->method('getQueryParams')->willReturn([]);
    }
}
