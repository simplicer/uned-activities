<?php

declare(strict_types=1);

namespace HttpApi\Controller;

use Auth\Application\RequestMagicLink\RequestMagicLink;
use Auth\Application\VerifyMagicLink\VerifyMagicLink;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Infrastructure\Auth\JwtService;
use UserProfile\Domain\UserDataStorage\UserRepository;

/**
 * Authentication controller for magic link auth.
 */
final readonly class AuthController
{
    public function __construct(
        private RequestMagicLink $requestMagicLink,
        private VerifyMagicLink $verifyMagicLink,
        private JwtService $jwtService,
        private UserRepository $userRepository,
        private bool $hideDetails = false,
    ) {
    }

    /**
     * POST /auth/request - Request a magic link
     */
    public function request(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!\is_array($body)) {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Invalid request body',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $email = $body['email'] ?? '';

        if ($email === '') {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Email is required',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Always return success to prevent email enumeration
            $expireMinutes = isset($_ENV['MAGIC_LINK_EXPIRE_MINUTES'])
                ? (int) $_ENV['MAGIC_LINK_EXPIRE_MINUTES']
                : null;
            $this->requestMagicLink->execute($email, $expireMinutes);

            $response->getBody()->write(json_encode([
                'message' => 'If the email exists, a magic link has been sent',
            ], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => $this->hideDetails ? 'Invalid request' : $e->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Log error but don't expose it
            error_log('Magic link request failed: ' . $e->getMessage());

            $response->getBody()->write(json_encode([
                'message' => 'If the email exists, a magic link has been sent',
            ], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /auth/verify - Verify a magic link
     */
    public function verify(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!\is_array($body)) {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Invalid request body',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $token = $body['token'] ?? '';

        if ($token === '') {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Token is required',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $result = $this->verifyMagicLink->execute($token);
        } catch (\Exception $e) {
            error_log('Magic link verify failed: ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'server_error',
                'message' => 'Unable to verify token',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        if (!$result->success) {
            $response->getBody()->write(json_encode([
                'error' => 'invalid_token',
                'message' => $result->error ?? 'Invalid token',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        try {
            $sessionToken = $this->jwtService->issue([
                'sub' => $result->user->id->toString(),
                'email' => $result->user->email,
                'role' => 'authenticated',
            ]);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'server_error',
                'message' => $this->hideDetails ? 'Server error' : $e->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        if ($result->user === null) {
            $response->getBody()->write(json_encode([
                'error' => 'invalid_token',
                'message' => 'User not found',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Store session token in user metadata or separate sessions table
        // For now, return user data with a session token
        $response->getBody()->write(json_encode([
            'data' => [
                'user' => [
                    'id' => $result->user->id->toString(),
                    'email' => $result->user->email,
                ],
                'token' => $sessionToken,
                'message' => 'Authentication successful',
            ],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /auth/me - Get current user info
     */
    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('auth_user_id');
        $email = $request->getAttribute('auth_email');

        if (!\is_string($userId) || $userId === '') {
            $response->getBody()->write(json_encode([
                'error' => 'unauthorized',
                'message' => 'Authentication required',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'data' => [
                'id' => $userId,
                'email' => $email,
            ],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /auth/password - Authenticate with email and password.
     */
    public function password(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!\is_array($body)) {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Invalid request body',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $email = isset($body['email']) ? strtolower(trim((string) $body['email'])) : '';
        $password = isset($body['password']) ? (string) $body['password'] : '';

        if ($email === '' || $password === '') {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Email y contraseña son obligatorios',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $emailValidation = filter_var($email, FILTER_VALIDATE_EMAIL);

        if ($emailValidation === false) {
            $response->getBody()->write(json_encode([
                'error' => 'validation_error',
                'message' => 'Email inválido',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $hash = $this->userRepository->getPasswordHashByEmail($email);

        if ($hash === null || !password_verify($password, $hash)) {
            $response->getBody()->write(json_encode([
                'error' => 'invalid_credentials',
                'message' => 'Usuario o contraseña inválidos',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $response->getBody()->write(json_encode([
                'error' => 'invalid_credentials',
                'message' => 'Usuario o contraseña inválidos',
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        try {
            $sessionToken = $this->jwtService->issue([
                'sub' => $user->id->toString(),
                'email' => $user->email,
                'role' => 'authenticated',
            ]);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'server_error',
                'message' => $this->hideDetails ? 'Server error' : $e->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'data' => [
                'user' => [
                    'id' => $user->id->toString(),
                    'email' => $user->email,
                ],
                'token' => $sessionToken,
                'message' => 'Authentication successful',
            ],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
