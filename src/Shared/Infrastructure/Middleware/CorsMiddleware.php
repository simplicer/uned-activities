<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Basic CORS middleware.
 */
final readonly class CorsMiddleware
{
    /**
     * @param array<int, string> $allowedOrigins
     */
    public function __construct(
        private array $allowedOrigins,
    ) {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->resolveOrigin($origin);

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new SlimResponse(204);
            return $this->withCorsHeaders($response, $allowedOrigin);
        }

        $response = $handler->handle($request);
        return $this->withCorsHeaders($response, $allowedOrigin);
    }

    private function resolveOrigin(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        if ($this->allowedOrigins === ['*']) {
            return '*';
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }

    private function withCorsHeaders(Response $response, ?string $origin): Response
    {
        if ($origin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization,Content-Type')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
