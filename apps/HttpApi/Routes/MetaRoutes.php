<?php

declare(strict_types=1);

namespace HttpApi\Routes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Register meta routes for /status and /version.
 *
 * These endpoints are required by the Zalando API guidelines.
 */
class MetaRoutes
{
    public function __invoke(App $app): void
    {
        $app->get('/status', function (Request $request, Response $response) {
            $payload = [
                'status' => 'ok',
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'checks' => [
                    'database' => 'ok', // TODO: Implement real DB health check
                    'cache' => 'ok',
                ],
            ];

            $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        });

        $app->get('/version', function (Request $request, Response $response) {
            $payload = [
                'version' => $_ENV['APP_VERSION'] ?? '1.0.0-dev',
                'commit' => $_ENV['GIT_COMMIT'] ?? 'unknown',
                'buildDate' => $_ENV['BUILD_DATE'] ?? (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ];

            $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        });
    }
}
