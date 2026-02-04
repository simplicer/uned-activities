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
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityEmbeddingRepository;
use CatalogHarvest\Domain\ActivityDataStorage\PriceSnapshotRepository;
use CatalogHarvest\Domain\ActivityDataStorage\ActivitySnapshotRepository;
use CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher;
use UserProfile\Infrastructure\Persistence\PdoUserRepository;
use UserProfile\Infrastructure\Persistence\PdoSavedSearchRepository;
use UserProfile\Infrastructure\Persistence\PdoFavoriteRepository;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use Notifications\Domain\NotificationQueue\NotificationRepository;
use Shared\Infrastructure\Email\SmtpEmailService;
use Shared\Infrastructure\Auth\JwtService;
use Auth\Infrastructure\Persistence\PdoMagicTokenRepository;
use Auth\Domain\AuthenticationTokenStorage\MagicTokenRepository;

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
            'app.env' => (string) ($_ENV['APP_ENV'] ?? 'production'),
            'app.debug' => (string) ($_ENV['APP_DEBUG'] ?? 'false'),

            // JWT configuration
            JwtService::class => function (): JwtService {
                return new JwtService(
                    secret: (string) ($_ENV['SUPABASE_JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? ''),
                    issuer: $_ENV['SUPABASE_JWT_ISSUER'] ?? null,
                    audience: $_ENV['SUPABASE_JWT_AUDIENCE'] ?? null,
                    ttlSeconds: (int) ($_ENV['JWT_TTL_SECONDS'] ?? 3600)
                );
            },

            // Email service configuration
            SmtpEmailService::class => function (): SmtpEmailService {
                return new SmtpEmailService(
                    fromEmail: (string) ($_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com'),
                    fromName: (string) ($_ENV['SMTP_FROM_NAME'] ?? 'UNED Activities'),
                    host: (string) ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'),
                    port: (int) ($_ENV['SMTP_PORT'] ?? 587),
                    username: (string) ($_ENV['SMTP_USER'] ?? $_ENV['SMTP_USERNAME'] ?? ''),
                    password: (string) ($_ENV['SMTP_PASSWORD'] ?? ''),
                    encryption: (string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')
                );
            },

            // Database connection
            \PDO::class => function (): \PDO {
                $host = (string) ($_ENV['DB_HOST'] ?? 'localhost');
                $port = (int) ($_ENV['DB_PORT'] ?? 5432);
                $dbname = (string) ($_ENV['DB_NAME'] ?? 'uned_activities');
                $user = (string) ($_ENV['DB_USER'] ?? 'postgres');
                $password = (string) ($_ENV['DB_PASSWORD'] ?? 'postgres');

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
                $host = (string) ($_ENV['REDIS_HOST'] ?? '');
                if ($host === '') {
                    return null;
                }

                $redis = new \Redis();
                $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
                $redis->connect($host, $port);

                $password = (string) ($_ENV['REDIS_PASSWORD'] ?? '');
                if ($password !== '') {
                    $redis->auth($password);
                }

                return $redis;
            },
        ]);

        return $builder->build();
    }
}
