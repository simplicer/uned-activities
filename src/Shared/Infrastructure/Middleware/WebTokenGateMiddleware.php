<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Web token gate middleware.
 *
 * Requires valid JWT for protected endpoints.
 */
final class WebTokenGateMiddleware
{
    private const array PUBLIC_PATHS = [
        '/status',
        '/version',
        '/health',
        '/v1/status',
        '/v1/version',
        '/v1/health',
        '/v1/activities',
        '/v1/auth/request',
        '/v1/auth/verify',
    ];

    public function __construct(
        private readonly string $jwtSecret,
        private readonly ?string $jwtIssuer = null,
        private readonly ?string $jwtAudience = null,
    ) {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $this->normalizePath($request->getUri()->getPath());
        $method = strtoupper($request->getMethod());

        if ($this->isPublicRoute($method, $path)) {
            return $handler->handle($request);
        }

        if ($this->jwtSecret === '') {
            return $this->createUnauthorizedResponse('JWT secret not configured');
        }

        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->createUnauthorizedResponse('Missing bearer token');
        }

        try {
            $claims = (array) JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        } catch (\Throwable $e) {
            return $this->createUnauthorizedResponse('Invalid token');
        }

        if (!$this->validateClaims($claims)) {
            return $this->createUnauthorizedResponse('Invalid token claims');
        }

        $request = $request
            ->withAttribute('auth_user_id', $claims['sub'])
            ->withAttribute('auth_email', $claims['email'] ?? null)
            ->withAttribute('auth_role', $claims['role'] ?? null);

        return $handler->handle($request);
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/api/v1/')) {
            return substr($path, 4);
        }

        if ($path === '/api/v1') {
            return '/v1';
        }

        return $path;
    }

    private function isPublicRoute(string $method, string $path): bool
    {
        if (\in_array($path, self::PUBLIC_PATHS, true)) {
            return true;
        }

        if ($method === 'GET' && preg_match('#^/v1/activities/[^/]+(/similar)?$#', $path) === 1) {
            return true;
        }

        return false;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $auth = $request->getHeaderLine('Authorization');

        if ($auth === '') {
            return null;
        }

        if (!str_starts_with(strtolower($auth), 'bearer ')) {
            return null;
        }

        $token = trim(substr($auth, 7));

        return $token !== '' ? $token : null;
    }

    private function validateClaims(array $claims): bool
    {
        if (!isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') {
            return false;
        }

        if ($this->jwtIssuer !== null && ($claims['iss'] ?? null) !== $this->jwtIssuer) {
            return false;
        }

        if ($this->jwtAudience !== null) {
            $aud = $claims['aud'] ?? null;
            if (is_array($aud)) {
                if (!in_array($this->jwtAudience, $aud, true)) {
                    return false;
                }
            } elseif ($aud !== $this->jwtAudience) {
                return false;
            }
        }

        return true;
    }

    private function createUnauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse(401);

        $response->getBody()->write(json_encode([
            'error' => 'unauthorized',
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}
