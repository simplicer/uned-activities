<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Web token gate middleware.
 *
 * Requires valid API token for access.
 * Tokens are stored in database or environment variables.
 */
final class WebTokenGateMiddleware
{
    private const TOKEN_HEADER = 'X-API-Token';
    private const TOKEN_QUERY_PARAM = 'token';

    /** @var array<string, true> Valid tokens cache */
    private array $validTokens = [];

    /** @var array<string, true> Whitelisted paths that don't require tokens */
    private const WHITELIST_PATHS = [
        '/status',
        '/version',
        '/health',
    ];

    public function __construct(
        private readonly ?\PDO $db = null,
    ) {
        $this->loadTokensFromEnv();
    }

    /**
     * Load tokens from environment for development.
     */
    private function loadTokensFromEnv(): void
    {
        $tokens = $_ENV['API_TOKENS'] ?? '';
        if (empty($tokens)) {
            return;
        }

        foreach (explode(',', $tokens) as $token) {
            $token = trim($token);
            if (!empty($token)) {
                $this->validTokens[$token] = true;
            }
        }
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Check if path is whitelisted
        $path = $request->getUri()->getPath();
        if ($this->isWhitelisted($path)) {
            return $handler->handle($request);
        }

        // Extract token
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->createUnauthorizedResponse('Missing API token');
        }

        // Validate token
        if (!$this->isValidToken($token)) {
            return $this->createUnauthorizedResponse('Invalid API token');
        }

        // Add token info to request for downstream use
        return $handler->handle($request);
    }

    /**
     * Check if path is whitelisted.
     */
    private function isWhitelisted(string $path): bool
    {
        // Exact match
        if (in_array($path, self::WHITELIST_PATHS, true)) {
            return true;
        }

        // Prefix match for health check variants
        foreach (self::WHITELIST_PATHS as $whitelisted) {
            if (str_starts_with($path, $whitelisted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract API token from request.
     */
    private function extractToken(Request $request): ?string
    {
        // Check custom header
        $token = $request->getHeaderLine(self::TOKEN_HEADER);
        if (!empty($token)) {
            return $token;
        }

        // Check Authorization header (Bearer token)
        $auth = $request->getHeaderLine('Authorization');
        if (str_starts_with(strtolower($auth), 'bearer ')) {
            return substr($auth, 7);
        }

        // Check query parameter
        $params = $request->getQueryParams();
        return $params[self::TOKEN_QUERY_PARAM] ?? null;
    }

    /**
     * Validate token against database or environment.
     */
    private function isValidToken(string $token): bool
    {
        // Check environment tokens first
        if (isset($this->validTokens[$token])) {
            return true;
        }

        // Check database if available
        if ($this->db !== null) {
            return $this->validateTokenFromDb($token);
        }

        return false;
    }

    /**
     * Validate token from database.
     */
    private function validateTokenFromDb(string $token): bool
    {
        static $stmt = null;

        if ($stmt === null) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM api_tokens WHERE token = :token AND is_active = true AND (expires_at IS NULL OR expires_at > NOW())'
            );
        }

        $stmt->execute(['token' => $token]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Create unauthorized response.
     */
    private function createUnauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse(401); // HTTP 401 Unauthorized

        $response->getBody()->write(json_encode([
            'error' => 'unauthorized',
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}
