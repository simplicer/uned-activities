<?php

declare(strict_types=1);

namespace Apps\Bootstrap;

use DI\Container;
use DI\ContainerBuilder;
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityEmbeddingRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivitySnapshotRepository;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Domain\HtmlContentExtractor\HtmlContentExtractor;
use CatalogHarvest\Domain\ActivityEmbeddingGenerator\ActivityEmbeddingGenerator;
use CatalogHarvest\Domain\ActivityRepository;
use CatalogHarvest\Domain\ActivityEmbeddingRepository;
use CatalogHarvest\Domain\PriceSnapshotRepository;
use CatalogHarvest\Domain\ActivitySnapshotRepository;
use CatalogHarvest\Domain\HtmlFetcher;
use UserProfile\Infrastructure\Persistence\PdoUserRepository;
use UserProfile\Infrastructure\Persistence\PdoSavedSearchRepository;
use UserProfile\Infrastructure\Persistence\PdoFavoriteRepository;
use UserProfile\Domain\UserRepository;
use UserProfile\Domain\SavedSearchRepository;
use UserProfile\Domain\FavoriteRepository;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use Notifications\Domain\NotificationRepository;
use Shared\Infrastructure\Email\SmtpEmailService;
use Shared\Infrastructure\Auth\JwtService;
use Auth\Infrastructure\Persistence\PdoMagicTokenRepository;
use Auth\Domain\MagicTokenRepository;

/**
 * ContainerFactory.
 *
 * Centralized dependency injection configuration.
 * Binds domain interfaces to infrastructure implementations.
 */
final class ContainerFactory
{
    public static function create(): Container
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            // Domain → Infrastructure bindings
            HtmlFetcher::class => \DI\autowire(GuzzleHtmlFetcher::class),
            HtmlContentExtractor::class => \DI\autowire(ActivityDetailParser::class),
            ActivityRepository::class => \DI\autowire(PdoActivityRepository::class),
            ActivityEmbeddingRepository::class => \DI\autowire(PdoActivityEmbeddingRepository::class),
            PriceSnapshotRepository::class => \DI\autowire(PdoPriceSnapshotRepository::class),
            ActivitySnapshotRepository::class => \DI\autowire(PdoActivitySnapshotRepository::class),
            UserRepository::class => \DI\autowire(PdoUserRepository::class),
            SavedSearchRepository::class => \DI\autowire(PdoSavedSearchRepository::class),
            FavoriteRepository::class => \DI\autowire(PdoFavoriteRepository::class),
            NotificationRepository::class => \DI\autowire(PdoNotificationRepository::class),
            MagicTokenRepository::class => \DI\autowire(PdoMagicTokenRepository::class),

            // Environment-based configuration
            'app.version' => '0.10.1-alpha',
            'app.env' => \DI\env('APP_ENV', 'production'),
            'app.debug' => \DI\env('APP_DEBUG', 'false'),

            // JWT configuration
            JwtService::class => \DI\create(JwtService::class)
                ->constructor(
                    jwtSecret: \DI\env('SUPABASE_JWT_SECRET', \DI\env('JWT_SECRET', '')),
                    issuer: \DI\env('SUPABASE_JWT_ISSUER', null),
                    audience: \DI\env('SUPABASE_JWT_AUDIENCE', null),
                    ttl: (int) \DI\env('JWT_TTL_SECONDS', '3600')
                ),

            // Email service configuration
            SmtpEmailService::class => \DI\create(SmtpEmailService::class)
                ->constructor(
                    fromEmail: \DI\env('SMTP_FROM_EMAIL', 'noreply@example.com'),
                    fromName: \DI\env('SMTP_FROM_NAME', 'UNED Activities'),
                    host: \DI\env('SMTP_HOST', 'smtp.gmail.com'),
                    port: (int) \DI\env('SMTP_PORT', '587'),
                    username: \DI\env('SMTP_USER', \DI\env('SMTP_USERNAME', '')),
                    password: \DI\env('SMTP_PASSWORD', ''),
                    encryption: \DI\env('SMTP_ENCRYPTION', 'tls')
                ),

            // Database connection
            \PDO::class => function (): \PDO {
                $host = \DI\env('DB_HOST', 'localhost');
                $port = \DI\env('DB_PORT', '5432');
                $dbname = \DI\env('DB_NAME', 'uned_activities');
                $user = \DI\env('DB_USER', 'postgres');
                $password = \DI\env('DB_PASSWORD', 'postgres');

                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

                return new \PDO($dsn, $user, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            },

            // Redis connection (optional)
            \Redis::class => function (): ?\Redis {
                $host = \DI\env('REDIS_HOST', '');
                if ($host === '') {
                    return null;
                }

                $redis = new \Redis();
                $redis->connect($host, (int) \DI\env('REDIS_PORT', '6379'));

                $password = \DI\env('REDIS_PASSWORD', '');
                if ($password !== '') {
                    $redis->auth($password);
                }

                return $redis;
            },
        ]);

        return $builder->build();
    }
}
