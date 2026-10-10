<?php

declare(strict_types=1);

namespace Tests\Unit\UserProfile\Infrastructure\Http;

use Notifications\Domain\NotificationQueue\NotificationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Infrastructure\Http\ProfileRoutes;

#[CoversClass(ProfileRoutes::class)]
final class ProfileRoutesContainerBindingTest extends TestCase
{
    /**
     * Regression: Slim binds route closures to the DI container when the app
     * is created from a container (AppFactory::createFromContainer), so
     * $this inside a route closure is the container, not ProfileRoutes.
     * Every /v1/profile/* route returned 500 in production because of it.
     */
    public function testFavoritesRouteRespondsWhenClosuresAreContainerBound(): void
    {
        $container = new \DI\Container();
        $app = AppFactory::createFromContainer($container);

        $favorites = $this->createMock(FavoriteRepository::class);
        $favorites->method('findDetailedByUserId')->willReturn([]);

        (new ProfileRoutes())(
            $app,
            $this->createMock(UserRepository::class),
            $this->createMock(SavedSearchRepository::class),
            $favorites,
            $this->createMock(NotificationRepository::class),
        );

        $request = (new \Slim\Psr7\Factory\RequestFactory())
            ->createRequest('GET', '/v1/profile/favorites')
            ->withAttribute('auth_user_id', '22222222-2222-4222-8222-222222222222');

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"data":[]}', (string) $response->getBody());
    }
}
