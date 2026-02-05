<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use RuntimeException;

/**
 * OpenRouter embeddings client.
 */
final readonly class OpenRouterEmbeddingClient implements EmbeddingClient
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        if ($this->apiKey === '' || $this->model === '') {
            throw new RuntimeException('OpenRouter embedding config missing');
        }

        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $ch = curl_init('https://openrouter.ai/api/v1/embeddings');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://extension.uned.es',
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException('OpenRouter embeddings request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('OpenRouter embeddings API returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['data'][0]['embedding']) || !\is_array($data['data'][0]['embedding'])) {
            throw new RuntimeException('Invalid OpenRouter embeddings response');
        }

        return array_map('floatval', $data['data'][0]['embedding']);
    }

    public function model(): string
    {
        return $this->model;
    }
}
