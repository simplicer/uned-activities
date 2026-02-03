<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

/**
 * Embeddings client abstraction.
 */
interface EmbeddingClient
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    public function model(): string;
}
