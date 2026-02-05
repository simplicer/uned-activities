<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use RuntimeException;
use Throwable;

/**
 * Embeddings client that falls back when primary fails.
 */
final class FallbackEmbeddingClient implements EmbeddingClient
{
    private ?string $lastModel = null;

    public function __construct(
        private readonly EmbeddingClient $primary,
        private readonly ?EmbeddingClient $fallback = null,
    ) {
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        try {
            $embedding = $this->primary->embed($text);
            $this->lastModel = $this->primary->model();

            return $embedding;
        } catch (Throwable $error) {
            if (!$this->fallback instanceof EmbeddingClient) {
                throw $error;
            }

            try {
                $embedding = $this->fallback->embed($text);
                $this->lastModel = $this->fallback->model();

                return $embedding;
            } catch (Throwable $fallbackError) {
                throw new RuntimeException(
                    \sprintf(
                        'Primary embedding failed: %s. Fallback failed: %s.',
                        $error->getMessage(),
                        $fallbackError->getMessage(),
                    ),
                    0,
                    $fallbackError,
                );
            }
        }
    }

    public function model(): string
    {
        if ($this->lastModel !== null) {
            return $this->lastModel;
        }

        return $this->primary->model();
    }
}
