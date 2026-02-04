<?php

declare(strict_types=1);

use Auth\Application\RequestMagicLink\RequestMagicLink;
use Auth\Application\VerifyMagicLink\VerifyMagicLink;
use Auth\Domain\Port\MagicTokenRepository;
use Auth\Infrastructure\Persistence\PdoMagicTokenRepository;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\ActivityEmbeddingRepository;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityEmbeddingRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use HttpApi\Controller\ActivityController;
use HttpApi\Controller\AuthController;
use HttpApi\Controller\ContactController;
use Shared\Infrastructure\Email\SmtpEmailService;
use Shared\Infrastructure\Auth\JwtService;
use Shared\Infrastructure\Middleware\CorsMiddleware;
use Shared\Infrastructure\Middleware\RateLimiterMiddleware;
use Shared\Infrastructure\Middleware\WebTokenGateMiddleware;
use Shared\Infrastructure\Routing\ActivityRoutes;
use Shared\Infrastructure\Routing\AuthRoutes;
use Shared\Infrastructure\Routing\ContactRoutes;
use Shared\Infrastructure\Routing\MetaRoutes;
use Slim\App;
use Notifications\Domain\Port\NotificationRepository;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use UserProfile\Domain\Port\FavoriteRepository;
use UserProfile\Domain\Port\SavedSearchRepository;
use UserProfile\Domain\Port\UserRepository;
use UserProfile\Infrastructure\Http\ProfileRoutes;
use UserProfile\Infrastructure\Persistence\PdoFavoriteRepository;
use UserProfile\Infrastructure\Persistence\PdoSavedSearchRepository;
use UserProfile\Infrastructure\Persistence\PdoUserRepository;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables
$envRoot = __DIR__ . '/../../..' . '/';
$infraEnv = $envRoot . 'infra/env/local.env';

if (file_exists($infraEnv)) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot . 'infra/env', 'local.env');
    $dotenv->load();
} elseif (file_exists($envRoot . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot);
    $dotenv->load();
}

// Set default environment values
$_ENV['APP_DEBUG'] ??= 'false';
$_ENV['APP_VERSION'] ??= '1.0.0-dev';

// Rate limiting settings
$rateLimit = (int) ($_ENV['RATE_LIMIT'] ?? 100);
$rateWindow = (int) ($_ENV['RATE_WINDOW'] ?? 60);

// Database connection
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_PORT'] ?? '5432',
    $_ENV['DB_NAME'] ?? 'uned_activities',
);
$pdo = new \PDO(
    $dsn,
    $_ENV['DB_USER'] ?? 'postgres',
    $_ENV['DB_PASSWORD'] ?? 'postgres',
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]
);

// Redis connection (optional)
$redis = null;

if (isset($_ENV['REDIS_HOST']) && $_ENV['REDIS_HOST'] !== '') {
    $redis = new \Redis();
    $redis->connect($_ENV['REDIS_HOST'], (int) ($_ENV['REDIS_PORT'] ?? 6379));

    if (isset($_ENV['REDIS_PASSWORD']) && $_ENV['REDIS_PASSWORD'] !== '') {
        $redis->auth($_ENV['REDIS_PASSWORD']);
    }
}

// Create Slim app with container
$container = new \DI\Container();
$container->set(PDO::class, $pdo);
$container->set(ActivityRepository::class, \DI\autowire(PdoActivityRepository::class));
$container->set(ActivityEmbeddingRepository::class, \DI\autowire(PdoActivityEmbeddingRepository::class));
$container->set(PriceSnapshotRepository::class, \DI\autowire(PdoPriceSnapshotRepository::class));
$container->set(UserRepository::class, \DI\autowire(PdoUserRepository::class));
$container->set(SavedSearchRepository::class, \DI\autowire(PdoSavedSearchRepository::class));
$container->set(FavoriteRepository::class, \DI\autowire(PdoFavoriteRepository::class));
$container->set(NotificationRepository::class, \DI\autowire(PdoNotificationRepository::class));

// Auth services
$container->set(MagicTokenRepository::class, \DI\autowire(PdoMagicTokenRepository::class));

// Email service
$smtpFromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com';
$smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? 'Buscador UNED';
$smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtpPort = (int) ($_ENV['SMTP_PORT'] ?? 587);
$smtpUser = $_ENV['SMTP_USER'] ?? ($_ENV['SMTP_USERNAME'] ?? '');
$smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';
$smtpEncryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
$container->set(SmtpEmailService::class, \DI\create(SmtpEmailService::class)
    ->constructor($smtpFromEmail, $smtpFromName, $smtpHost, $smtpPort, $smtpUser, $smtpPassword, $smtpEncryption));

// Contact controller
$contactRecipient = $_ENV['CONTACT_EMAIL'] ?? 'hola@lexemas.com';
$container->set(ContactController::class, \DI\autowire(ContactController::class)
    ->constructorParameter('recipient', $contactRecipient)
    ->constructorParameter('context', 'Contacto'));

// Auth use cases
$frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:8080';
$container->set(RequestMagicLink::class, \DI\autowire(RequestMagicLink::class)
    ->constructorParameter('frontendUrl', $frontendUrl));
$container->set(VerifyMagicLink::class, \DI\autowire(VerifyMagicLink::class));
$jwtSecret = $_ENV['SUPABASE_JWT_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? '');
$jwtIssuer = $_ENV['SUPABASE_JWT_ISSUER'] ?? null;
$jwtAudience = $_ENV['SUPABASE_JWT_AUDIENCE'] ?? null;
$jwtTtl = (int) ($_ENV['JWT_TTL_SECONDS'] ?? 3600);
$container->set(JwtService::class, \DI\create(JwtService::class)
    ->constructor($jwtSecret, $jwtIssuer, $jwtAudience, $jwtTtl));

$app = Slim\Factory\AppFactory::createFromContainer($container);

// Add middleware (order matters - last added runs first)
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Add security middlewares
$allowedOrigins = array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*')));
$app->add(new CorsMiddleware($allowedOrigins === [] ? ['*'] : $allowedOrigins));
$app->add(new RateLimiterMiddleware($rateLimit, $rateWindow, $redis));
$app->add(new WebTokenGateMiddleware($jwtSecret, $jwtIssuer, $jwtAudience, $appEnv === 'production'));

// Error handling
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    logErrors: true,
    logErrorDetails: true
);

// Register meta routes
(new MetaRoutes())($app);

// Register activity routes
$activityController = $container->get(ActivityController::class);
(new ActivityRoutes())($app, $activityController);

// Register auth routes
$authController = new AuthController(
    $container->get(RequestMagicLink::class),
    $container->get(VerifyMagicLink::class),
    $container->get(JwtService::class),
    $container->get(UserRepository::class),
    $appEnv === 'production',
);
(new AuthRoutes())($app, $authController);

// Register contact routes
$contactController = $container->get(ContactController::class);
(new ContactRoutes())($app, $contactController);

// Register profile routes
$userRepository = $container->get(UserRepository::class);
$savedSearchRepository = $container->get(SavedSearchRepository::class);
$favoriteRepository = $container->get(FavoriteRepository::class);
$notificationRepository = $container->get(NotificationRepository::class);
(new ProfileRoutes())($app, $userRepository, $savedSearchRepository, $favoriteRepository, $notificationRepository);

// Run the application
$app->run();
