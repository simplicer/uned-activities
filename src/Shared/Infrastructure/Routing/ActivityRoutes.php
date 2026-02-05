<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Routing;

use HttpApi\Controller\ActivityController;
use Slim\App;

/**
 * Register activity routes.
 */
class ActivityRoutes
{
    public function __invoke(App $app, ActivityController $controller): void
    {
        // GET /v1/activities - List activities with filters
        // (nginx rewrites /api/v1/activities -> /v1/activities)
        $app->get('/v1/activities', fn ($request, $response) => $controller->list($request, $response));

        // GET /v1/centers - List centers with counts
        $app->get('/v1/centers', fn ($request, $response) => $controller->centers($request, $response));

        // GET /v1/activities/{id} - Get activity detail
        $app->get('/v1/activities/{id}', fn ($request, $response, $args) => $controller->detail($request, $response, $args['id']));

        // GET /v1/activities/{id}/similar - Get similar activities
        $app->get('/v1/activities/{id}/similar', fn ($request, $response, $args) => $controller->similar($request, $response, $args['id']));
    }
}
