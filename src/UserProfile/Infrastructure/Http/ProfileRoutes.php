<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use UserProfile\Application\SaveSearch\SaveSearch;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Domain\ValueObject\UserId;
use Notifications\Domain\NotificationQueue\NotificationRepository;
use Notifications\Domain\ValueObject\NotificationId;

/**
 * Profile routes for user profile and saved searches.
 */
class ProfileRoutes
{
    public function __invoke(
        App $app,
        UserRepository $userRepository,
        SavedSearchRepository $searchRepository,
        FavoriteRepository $favoriteRepository,
        NotificationRepository $notificationRepository,
    ): void
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
        $app->delete('/v1/profile/saved-searches/{id}', function (Request $request, Response $response, array $args) use ($searchRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);
            $id = (string) ($args['id'] ?? '');

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

        // Update password
        $app->put('/v1/profile/password', function (Request $request, Response $response) use ($userRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $body = $request->getParsedBody();
            \assert(\is_array($body) || \is_object($body));

            $password = \is_array($body) ? (string) ($body['password'] ?? '') : '';

            if (!$this->isValidPassword($password)) {
                $response->getBody()->write(json_encode([
                    'error' => 'validation_error',
                    'message' => 'La contraseña no cumple los requisitos',
                ], JSON_THROW_ON_ERROR));

                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $userRepository->setPasswordHash($userId, $hash);

            $response->getBody()->write(json_encode(['data' => ['updated' => true]], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Delete account
        $app->delete('/v1/profile', function (Request $request, Response $response) use ($userRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $userRepository->deleteById($userId);

            $response->getBody()->write(json_encode(['data' => ['deleted' => true]], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get favorite activity IDs
        $app->get('/v1/profile/favorites/ids', function (Request $request, Response $response) use ($favoriteRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $ids = $favoriteRepository->findIdsByUserId($userId);
            $response->getBody()->write(json_encode(['data' => $ids], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get favorite activities
        $app->get('/v1/profile/favorites', function (Request $request, Response $response) use ($favoriteRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $favorites = $favoriteRepository->findDetailedByUserId($userId);
            $response->getBody()->write(json_encode(['data' => $favorites], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Add favorite activity
        $app->post('/v1/profile/favorites', function (Request $request, Response $response) use ($favoriteRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $body = $request->getParsedBody();
            \assert(\is_array($body) || \is_object($body));

            $activityId = \is_array($body) ? (string) ($body['activityId'] ?? '') : '';

            if ($activityId === '') {
                $response->getBody()->write(json_encode([
                    'error' => 'validation_error',
                    'message' => 'activityId is required',
                ], JSON_THROW_ON_ERROR));

                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $favoriteRepository->add($userId, $activityId);

            $response->getBody()->write(json_encode(['data' => ['added' => true]], JSON_THROW_ON_ERROR));

            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // Remove favorite activity
        $app->delete('/v1/profile/favorites/{activityId}', function (Request $request, Response $response, array $args) use ($favoriteRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);
            $activityId = (string) ($args['activityId'] ?? '');

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $favoriteRepository->remove($userId, $activityId);

            $response->getBody()->write(json_encode(['data' => ['deleted' => true]], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Update favorite metadata
        $app->put('/v1/profile/favorites/{activityId}', function (Request $request, Response $response, array $args) use ($favoriteRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);
            $activityId = (string) ($args['activityId'] ?? '');

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $body = $request->getParsedBody();
            \assert(\is_array($body) || \is_object($body));

            $enrolled = null;
            $rating = null;
            $notifyOnChange = null;

            if (\is_array($body)) {
                if (\array_key_exists('enrolled', $body)) {
                    $enrolled = (bool) $body['enrolled'];
                }

                if (\array_key_exists('rating', $body) && $body['rating'] !== null && $body['rating'] !== '') {
                    if (!\is_numeric($body['rating'])) {
                        $response->getBody()->write(json_encode([
                            'error' => 'validation_error',
                            'message' => 'La nota debe ser numérica',
                        ], JSON_THROW_ON_ERROR));

                        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                    }
                    $rating = (float) $body['rating'];
                    if ($rating < 0 || $rating > 5) {
                        $response->getBody()->write(json_encode([
                            'error' => 'validation_error',
                            'message' => 'La nota debe estar entre 0 y 5',
                        ], JSON_THROW_ON_ERROR));

                        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                    }
                }

                if (\array_key_exists('notifyOnChange', $body)) {
                    $notifyOnChange = (bool) $body['notifyOnChange'];
                }
            }

            $favoriteRepository->updateMetadata($userId, $activityId, $enrolled, $rating, $notifyOnChange);

            $response->getBody()->write(json_encode(['data' => ['updated' => true]], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get notifications
        $app->get('/v1/profile/notifications', function (Request $request, Response $response) use ($notificationRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $notifications = $notificationRepository->findByUserId($userId, 50, 0);
            $data = array_map(fn ($n): array => [
                'id' => $n->id->toString(),
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'data' => $n->data,
                'isRead' => $n->isRead,
                'createdAt' => $n->createdAt->format('Y-m-d H:i:s'),
            ], $notifications);

            $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Mark notification as read
        $app->post('/v1/profile/notifications/{id}/read', function (Request $request, Response $response, array $args) use ($notificationRepository): \Psr\Http\Message\ResponseInterface|\Psr\Http\Message\MessageInterface {
            $userId = $this->getUserIdFromRequest($request);
            $id = (string) ($args['id'] ?? '');

            if (!$userId instanceof \UserProfile\Domain\ValueObject\UserId) {
                return $this->unauthorizedResponse($response);
            }

            $notificationId = NotificationId::fromString($id);
            $updated = $notificationRepository->markAsReadForUser($notificationId, $userId);

            if ($updated === 0) {
                return $this->notFoundResponse($response, 'Notification not found');
            }

            $response->getBody()->write(json_encode(['data' => ['updated' => true]], JSON_THROW_ON_ERROR));

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

    private function isValidPassword(string $password): bool
    {
        $length = mb_strlen($password);
        if ($length < 12) {
            return false;
        }

        $uniqueChars = count(array_unique(preg_split('//u', $password, -1, PREG_SPLIT_NO_EMPTY)));

        return $uniqueChars >= 6;
    }
}
