<?php

declare(strict_types=1);

namespace HttpApi\Controller;

use CatalogQuery\Application\Dto\ActivityFilters;
use CatalogQuery\Application\GetActivityDetail\GetActivityDetail;
use CatalogQuery\Application\ListActivities\ListActivities;
use CatalogQuery\Application\Serialize\ActivityJsonSerializer;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller for activity endpoints.
 */
final class ActivityController
{
    public function __construct(
        private readonly ListActivities $listActivities,
        private readonly GetActivityDetail $getActivityDetail,
        private readonly ActivityJsonSerializer $serializer,
    ) {
    }

    /**
     * GET /activities - List activities with filters.
     */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filters = ActivityFilters::create($params);
        $page = (int) ($params['page'] ?? 1);
        $perPage = isset($params['perPage']) ? (int) $params['perPage'] : null;

        $result = $this->listActivities->list($filters, $page, $perPage);

        $data = [
            'data' => $this->serializer->toArrayList($result->activities),
            'meta' => $result->pagination(),
        ];

        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /activities/{id} - Get activity detail.
     */
    public function detail(Request $request, Response $response, string $id): Response
    {
        $activityId = ActivityId::fromString($id);
        $result = $this->getActivityDetail->get($activityId);

        $priceHistory = array_map(
            fn($snap) => [
                'priceAmount' => $snap->priceAmount,
                'priceCurrency' => $snap->priceCurrency,
                'capturedAt' => $snap->capturedAt->format('Y-m-d H:i:s'),
            ],
            $result->priceHistory
        );

        $data = $this->serializer->toArray($result->activity);
        $data['priceHistory'] = $priceHistory;

        $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
