<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use RuntimeException;

/**
 * Gemini embeddings client.
 */
final readonly class GeminiEmbeddingClient implements EmbeddingClient
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
            throw new RuntimeException('Gemini embedding config missing');
        }

        $payload = [
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $url = $this->requestUrl();
        $headers = $this->requestHeaders();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException('Gemini embeddings request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('Gemini embeddings API returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['embedding']['values']) || !\is_array($data['embedding']['values'])) {
            throw new RuntimeException('Invalid Gemini embeddings response');
        }

        return array_map('floatval', $data['embedding']['values']);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Request target. The API key must never travel in the URL: proxies and
     * access logs retain query strings far more readily than headers.
     */
    private function requestUrl(): string
    {
        return \sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent',
            urlencode($this->model),
        );
    }

    /**
     * @return array<int, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ];
    }
}
