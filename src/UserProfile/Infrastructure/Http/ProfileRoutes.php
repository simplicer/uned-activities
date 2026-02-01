<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use UserProfile\Application\SaveSearch\SaveSearch;
use UserProfile\Domain\Port\SavedSearchRepository;
use UserProfile\Domain\Port\UserRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Profile routes for user profile and saved searches.
 */
class ProfileRoutes
{
    public function __invoke(App $app, UserRepository $userRepository, SavedSearchRepository $searchRepository): void
    {
        // Get current user profile
        $app->get('/profile', function (Request $request, Response $response) use ($userRepository) {
            $userId = $this->getUserIdFromRequest($request);

            if ($userId === null) {
                return $this->unauthorizedResponse($response);
            }

            $user = $userRepository->findById($userId);

            if ($user === null) {
                return $this->notFoundResponse($response, 'User not found');
            }

            $data = [
                'id' => $user->id->toString(),
                'email' => $user->email,
                'fullName' => $user->fullName,
                'preferences' => $user->preferences,
                'createdAt' => $user->createdAt->format('Y-m-d H:i:s'),
            ];

            $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Update user profile
        $app->put('/profile', function (Request $request, Response $response) use ($userRepository) {
            $userId = $this->getUserIdFromRequest($request);

            if ($userId === null) {
                return $this->unauthorizedResponse($response);
            }

            $user = $userRepository->findById($userId);

            if ($user === null) {
                return $this->notFoundResponse($response, 'User not found');
            }

            $body = $request->getParsedBody();

            if (isset($body['fullName'])) {
                $user = $user->withFullName($body['fullName']);
            }

            if (isset($body['preferences'])) {
                $user = $user->withPreferences($body['preferences']);
            }

            $userRepository->save($user);

            $response->getBody()->write(json_encode(['data' => ['updated' => true]], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get saved searches
        $app->get('/profile/saved-searches', function (Request $request, Response $response) use ($searchRepository) {
            $userId = $this->getUserIdFromRequest($request);

            if ($userId === null) {
                return $this->unauthorizedResponse($response);
            }

            $searches = $searchRepository->findByUserId($userId);

            $data = array_map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'filters' => $s->filters,
                'notifyOnNew' => $s->notifyOnNew,
                'createdAt' => $s->createdAt->format('Y-m-d H:i:s'),
            ], $searches);

            $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Create saved search
        $app->post('/profile/saved-searches', function (Request $request, Response $response) use ($searchRepository) {
            $userId = $this->getUserIdFromRequest($request);

            if ($userId === null) {
                return $this->unauthorizedResponse($response);
            }

            $body = $request->getParsedBody();

            $useCase = new SaveSearch($searchRepository);
            $search = $useCase->execute(
                $userId,
                $body['name'] ?? 'Sin nombre',
                $body['filters'] ?? [],
                $body['notifyOnNew'] ?? false
            );

            $data = [
                'id' => $search->id,
                'name' => $search->name,
                'filters' => $search->filters,
                'notifyOnNew' => $search->notifyOnNew,
                'createdAt' => $search->createdAt->format('Y-m-d H:i:s'),
            ];

            $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // Delete saved search
        $app->delete('/profile/saved-searches/{id}', function (Request $request, Response $response, string $id) use ($searchRepository) {
            $userId = $this->getUserIdFromRequest($request);

            if ($userId === null) {
                return $this->unauthorizedResponse($response);
            }

            $search = $searchRepository->findById($id);

            if ($search === null || !$search->userId->equals($userId)) {
                return $this->notFoundResponse($response, 'Saved search not found');
            }

            $useCase = new SaveSearch($searchRepository);
            $useCase->delete($id);

            $response->getBody()->write(json_encode(['data' => ['deleted' => true]], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }

    private function getUserIdFromRequest(Request $request): ?UserId
    {
        // Get user ID from Supabase auth header
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || !str_starts_with(strtolower($authHeader), 'bearer ')) {
            return null;
        }

        // In production, this would validate the JWT with Supabase
        // For now, we extract the user ID from the header
        // This is a simplified version - proper JWT validation is needed
        $token = substr($authHeader, 7);

        // TODO: Validate JWT with Supabase
        // For development, we'll extract from a custom header or query param
        $userId = $request->getHeaderLine('X-User-Id');
        if (!empty($userId)) {
            return UserId::fromString($userId);
        }

        return null;
    }

    private function unauthorizedResponse(Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'error' => 'unauthorized',
            'message' => 'Authentication required',
        ], JSON_THROW_ON_ERROR));

        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    private function notFoundResponse(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode([
            'error' => 'not_found',
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
}
