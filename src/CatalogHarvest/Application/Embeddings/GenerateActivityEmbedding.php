<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\Embeddings;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityEmbeddingRepository;
use Shared\Infrastructure\AI\EmbeddingClient;

/**
 * Generates and stores embeddings for activities.
 */
final readonly class GenerateActivityEmbedding
{
    public function __construct(
        private ActivityEmbeddingRepository $embeddingRepository,
        private EmbeddingClient $embeddingClient,
        private bool $enabled = true,
    ) {
    }

    public function generate(Activity $activity): void
    {
        if (!$this->enabled) {
            return;
        }

        $text = $this->buildText($activity);

        if ($text === '') {
            return;
        }

        $embedding = $this->embeddingClient->embed($text);
        $this->embeddingRepository->upsert($activity->id, $embedding, $this->embeddingClient->model());
    }

    private function buildText(Activity $activity): string
    {
        $parts = [
            $activity->title,
            $activity->description,
            $activity->center,
            $activity->area,
            $activity->typology,
            $activity->modality,
        ];

        $text = trim(implode("\n", array_filter($parts, static fn ($v) => $v !== null && $v !== '')));

        if ($text === '') {
            return '';
        }

        return $this->truncate($text, 8000);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (\strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength);
    }
}
