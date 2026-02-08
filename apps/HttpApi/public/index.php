<?php

declare(strict_types=1);

use Apps\Bootstrap\ContainerFactory;
use HttpApi\Controller\ActivityController;
use HttpApi\Controller\AuthController;
use HttpApi\Controller\ContactController;
use Shared\Infrastructure\Middleware\CorsMiddleware;
use Shared\Infrastructure\Middleware\RequestLoggerMiddleware;
use Shared\Infrastructure\Middleware\RateLimiterMiddleware;
use Shared\Infrastructure\Middleware\WebTokenGateMiddleware;
use Shared\Infrastructure\Logging\LoggerFactory;
use Monolog\Level;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Infrastructure\Routing\ActivityRoutes;
use Shared\Infrastructure\Routing\AuthRoutes;
use Shared\Infrastructure\Routing\ContactRoutes;
use Shared\Infrastructure\Routing\MetaRoutes;
use Notifications\Domain\NotificationQueue\NotificationRepository;
use Slim\Psr7\Stream;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Infrastructure\Http\ProfileRoutes;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables
$envRoot = __DIR__ . '/../../../';
$infraEnv = $envRoot . 'infra/env/local.env';

if (file_exists($infraEnv)) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot . 'infra/env', 'local.env');
    $dotenv->load();
} elseif (file_exists($envRoot . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot);
    $dotenv->load();
}

// Backward compatibility for legacy local/proxy setups that still call /api/*.
$requestUri = $_SERVER['REQUEST_URI'] ?? null;

if (is_string($requestUri) && str_starts_with($requestUri, '/api/')) {
    $_SERVER['REQUEST_URI'] = substr($requestUri, 4);
} elseif ($requestUri === '/api') {
    $_SERVER['REQUEST_URI'] = '/';
}

// Set default environment values
$_ENV['APP_DEBUG'] ??= 'false';
$_ENV['APP_VERSION'] ??= '1.0.0';
$_ENV['APP_ENV'] ??= 'development';
$appEnv = $_ENV['APP_ENV'];

// Rate limiting settings
$rateLimit = (int) ($_ENV['RATE_LIMIT'] ?? 100);
$rateWindow = (int) ($_ENV['RATE_WINDOW'] ?? 60);

$jwtSecret = $_ENV['SUPABASE_JWT_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? '');
$jwtIssuer = $_ENV['SUPABASE_JWT_ISSUER'] ?? null;
$jwtAudience = $_ENV['SUPABASE_JWT_AUDIENCE'] ?? null;

// Create Slim app with centralized container wiring.
$container = ContainerFactory::create();
$app = AppFactory::createFromContainer($container);

$redis = null;

try {
    $redis = $container->get(\Redis::class);
} catch (\Throwable) {
    $redis = null;
}

// Add middleware (order matters - last added runs first)
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Add security middlewares
$allowedOrigins = array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*')));
$app->add(new CorsMiddleware($allowedOrigins === [] ? ['*'] : $allowedOrigins));
$app->add(new RateLimiterMiddleware($rateLimit, $rateWindow, $redis));
$app->add(new WebTokenGateMiddleware($jwtSecret, $jwtIssuer, $jwtAudience, $appEnv === 'production'));

$logLevel = strtolower((string) ($_ENV['LOG_LEVEL'] ?? 'info'));
$level = match ($logLevel) {
    'debug' => Level::Debug,
    'warning' => Level::Warning,
    'error' => Level::Error,
    'critical' => Level::Critical,
    default => Level::Info,
};
$httpLogger = LoggerFactory::create('http', 'php://stdout', $level);
$app->add(new RequestLoggerMiddleware($httpLogger));

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
$authController = $container->get(AuthController::class);
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

// Serve frontend static files built into apps/HttpApi/public/dist.
$frontendDist = __DIR__ . '/dist';
$frontendDistReal = is_dir($frontendDist) ? realpath($frontendDist) : false;
$mimeByExt = static function (string $path): string {
    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'map' => 'application/json; charset=utf-8',
        default => 'application/octet-stream',
    };
};
$serveFile = static function (string $filePath, Response $response) use ($mimeByExt): Response {
    $resource = fopen($filePath, 'rb');

    if ($resource === false) {
        return $response->withStatus(500);
    }

    $cacheHeader = str_ends_with($filePath, '.html')
        ? 'no-cache'
        : 'public, max-age=31536000, immutable';

    return $response
        ->withBody(new Stream($resource))
        ->withHeader('Content-Type', $mimeByExt($filePath))
        ->withHeader('Cache-Control', $cacheHeader);
};

$app->map(['GET', 'HEAD'], '/{path:.*}', function (Request $request, Response $response, array $args) use ($frontendDist, $frontendDistReal, $serveFile): Response {
    if ($frontendDistReal === false) {
        return $response->withStatus(404);
    }

    $requestedPath = trim((string) ($args['path'] ?? ''), '/');

    // Keep API namespace separated from frontend static serving.
    if (
        $requestedPath === 'v1'
        || str_starts_with($requestedPath, 'v1/')
        || $requestedPath === 'api'
        || str_starts_with($requestedPath, 'api/')
    ) {
        return $response->withStatus(404);
    }

    $relativePath = $requestedPath === '' ? 'index.html' : $requestedPath;
    $candidatePath = realpath($frontendDist . '/' . $relativePath);
    $isInDist = $candidatePath !== false
        && (str_starts_with($candidatePath, $frontendDistReal . DIRECTORY_SEPARATOR) || $candidatePath === $frontendDistReal);

    if ($isInDist && is_file($candidatePath)) {
        return $serveFile($candidatePath, $response);
    }

    $indexFile = $frontendDist . '/index.html';

    if (is_file($indexFile)) {
        return $serveFile($indexFile, $response);
    }

    return $response->withStatus(404);
});

// Run the application
$app->run();
