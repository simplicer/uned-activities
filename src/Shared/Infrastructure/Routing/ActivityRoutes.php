<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Routing;

use HttpApi\Controller\ActivityController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Register activity routes.
 */
class ActivityRoutes
{
    public function __invoke(App $app, ActivityController $controller): void
    {
        // GET /activities - List activities with filters
        $app->get('/activities', [$controller, 'list']);

        // GET /activities/{id} - Get activity detail
        $app->get('/activities/{id}', [$controller, 'detail']);
    }
}
