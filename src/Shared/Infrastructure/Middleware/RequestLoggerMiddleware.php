<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Request logger middleware (Loki JSON line format).
 */
final readonly class RequestLoggerMiddleware
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $requestId = (string) ($request->getAttribute('request_id') ?? '');

        if ($requestId === '') {
            $requestId = Uuid::uuid4()->toString();
            $request = $request->withAttribute('request_id', $requestId);
        }

        $start = microtime(true);
        $response = $handler->handle($request);
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());
        $status = $response->getStatusCode();

        $rawUserId = $request->getAttribute('auth_user_id');
        $userIdHash = \is_string($rawUserId) && $rawUserId !== ''
            ? substr(hash('sha256', $rawUserId), 0, 12)
            : null;

        $this->logger->info('http_request', [
            'request_id' => $requestId,
            'user_id' => $userIdHash,
            'action' => $method . ' ' . $path,
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);

        return $response;
    }
}
