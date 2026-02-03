<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
        // Get current user profile (nginx rewrites /v1/profile -> /v1/profile)
        $app->get('/v1/profile', function (Request $request, Response $response) use ($userRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $user = $userRepository->findById($userId);

            if (!$user instanceof \UserProfile\Domain\Entity\User) {
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
        $app->put('/v1/profile', function (Request $request, Response $response) use ($userRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $user = $userRepository->findById($userId);

            if (!$user instanceof \UserProfile\Domain\Entity\User) {
                return $this->notFoundResponse($response, 'User not found');
            }

            $body = $request->getParsedBody();
            \assert(\is_array($body) || \is_object($body));

            if (\is_array($body) && \array_key_exists('fullName', $body)) {
                $user = $user->withFullName((string) $body['fullName']);
            }

            if (\is_array($body) && \array_key_exists('preferences', $body)) {
                $user = $user->withPreferences((array) $body['preferences']);
            }

            $userRepository->save($user);

            $response->getBody()->write(json_encode(['data' => ['updated' => true]], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get saved searches
        $app->get('/v1/profile/saved-searches', function (Request $request, Response $response) use ($searchRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $searches = $searchRepository->findByUserId($userId);

            $data = array_map(fn ($s): array => [
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
        $app->post('/v1/profile/saved-searches', function (Request $request, Response $response) use ($searchRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $body = $request->getParsedBody();
            \assert(\is_array($body) || \is_object($body));

            $name = 'Sin nombre';
            $filters = [];
            $notifyOnNew = false;

            if (\is_array($body)) {
                $name = $body['name'] ?? 'Sin nombre';
                $filters = $body['filters'] ?? [];
                $notifyOnNew = $body['notifyOnNew'] ?? false;
            }

            $useCase = new SaveSearch($searchRepository);
            $search = $useCase->execute(
                $userId,
                (string) $name,
                (array) $filters,
                (bool) $notifyOnNew
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
        $app->delete('/v1/profile/saved-searches/{id}', function (Request $request, Response $response, string $id) use ($searchRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $search = $searchRepository->findById($id);

            if (!$search instanceof \UserProfile\Domain\Entity\SavedSearch || !$search->userId->equals($userId)) {
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
        $userId = $request->getAttribute('auth_user_id');

        if (!is_string($userId) || $userId === '') {
            return null;
        }

        return UserId::fromString($userId);
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
