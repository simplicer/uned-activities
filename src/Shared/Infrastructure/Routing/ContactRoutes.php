<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Routing;

use HttpApi\Controller\ContactController;
use Slim\App;

/**
 * Register contact routes.
 */
final class ContactRoutes
{
    public function __invoke(App $app, ContactController $controller): void
    {
        // (nginx rewrites /api/v1/contact -> /v1/contact)
        $app->post('/v1/contact', [$controller, 'submit']);
    }
}
