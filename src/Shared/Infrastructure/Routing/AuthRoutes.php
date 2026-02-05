<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Routing;

use HttpApi\Controller\AuthController;
use Slim\App;

/**
 * Register authentication routes.
 */
class AuthRoutes
{
    public function __invoke(App $app, AuthController $controller): void
    {
        // POST /v1/auth/request - Request magic link
        $app->post('/v1/auth/request', fn ($request, $response) => $controller->request($request, $response));

        // POST /v1/auth/verify - Verify magic link
        $app->post('/v1/auth/verify', fn ($request, $response) => $controller->verify($request, $response));

        // POST /v1/auth/password - Login with password
        $app->post('/v1/auth/password', fn ($request, $response) => $controller->password($request, $response));

        // GET /v1/auth/me - Get current user
        $app->get('/v1/auth/me', fn ($request, $response) => $controller->me($request, $response));
    }
}
