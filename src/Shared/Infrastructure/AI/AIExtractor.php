<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use RuntimeException;

/**
 * AI Extractor with fallback models.
 *
 * Services priority:
 * 1. Gemini 2.0 Flash (Google) - fastest
 * 2. Gemini 1.5 Flash (Google) - alternative
 * 3. Qwen/Qwen-2.5-72B-Instruct (OpenRouter) - powerful free model
 * 4. Trinity Large Preview (OpenRouter)
 * 5. GPT-4O OpenSource (OpenRouter)
 */
final readonly class AIExtractor
{
    private const array GEMINI_MODELS = [
        'gemini-3-flash-preview',
        'gemini-2.5-flash',
    ];

    private const array OPENROUTER_MODELS = [
        'arcee-ai/trinity-large-preview:free',
        'arcee-ai/trinity-mini:free',
        'nvidia/nemotron-3-nano-30b-a3b:free',
        'openai/gpt-oss-20b:free',
    ];

    public function __construct(
        private string $geminiKey,
        private string $openrouterKey,
    ) {
    }

    /**
     * Extract structured data from HTML using AI with fallback.
     *
     * @param string $html The HTML content of the activity page
     * @param string $activityUrl The URL of the activity (for context)
     * @return array The extracted structured data
     * @throws RuntimeException If all AI services fail
     */
    public function extract(string $html, string $activityUrl): array
    {
        $lastError = null;
        $geminiModels = $this->readModelEnv('GEMINI_MODELS', self::GEMINI_MODELS);
        $openRouterModels = $this->readModelEnv('OPENROUTER_MODELS', self::OPENROUTER_MODELS);

        $allModels = array_merge(
            array_map(fn($m) => ['gemini', $m], $geminiModels),
            array_map(fn($m) => ['openrouter', $m], $openRouterModels),
        );

        foreach ($allModels as $index => [$service, $model]) {
            try {
                if ($service === 'gemini') {
                    $result = $this->extractFromGemini($html, $activityUrl, $model);
                } else {
                    $result = $this->extractFromOpenRouter($html, $activityUrl, $model);
                }

                $this->logSuccess($service . '/' . $model, $index + 1, count($allModels));
                return $result;
            } catch (RuntimeException $e) {
                $lastError = $e;
                $this->logFailure($service . '/' . $model, $index + 1, $e->getMessage());
                // Continue to next model
            }
        }

        // All models failed
        throw new RuntimeException(
            'All AI services failed. Last error: ' . ($lastError?->getMessage() ?? 'Unknown'),
            0,
            $lastError ?? new RuntimeException('AI extraction failed')
        );
    }

    /**
     * @return array<int, string>
     */
    private function readModelEnv(string $key, array $fallback): array
    {
        $raw = $_ENV[$key] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn ($value) => $value !== '');

        return $parts === [] ? $fallback : array_values($parts);
    }

    /**
     * Extract using Gemini models (3 Flash or 2.5 Flash Lite).
     *
     * @param string $html The HTML content
     * @param string $activityUrl The activity URL
     * @param string $model The model name (e.g., 'gemini-3-flash-preview' or 'gemini-2.5-flash-lite')
     * @return array Extracted structured data
     */
    private function extractFromGemini(string $html, string $activityUrl, string $model): array
    {
        $apiKey = $this->geminiKey;

        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->buildUserPrompt($html, $activityUrl);
        $schema = $this->getJsonSchema();

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt],
                        ['text' => $userPrompt],
                        ['text' => "\n\n" . '```json' . "\n" . $schema . "\n" . '```' . "\n\n"],
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1,
            ],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('Gemini request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('Gemini API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new RuntimeException('Invalid Gemini response format');
        }

        $jsonText = $data['candidates'][0]['content']['parts'][0]['text'];
        return $this->parseJsonResponse($jsonText, $activityUrl);
    }

    /**
     * Extract using OpenRouter.
     */
    private function extractFromOpenRouter(string $html, string $activityUrl, string $model): array
    {
        $apiKey = $this->openrouterKey;

        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->buildUserPrompt($html, $activityUrl);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 8192,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: ' . 'https://extension.uned.es',
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('OpenRouter request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('OpenRouter API returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('Invalid OpenRouter response format');
        }

        $jsonText = $data['choices'][0]['message']['content'];
        return $this->parseJsonResponse($jsonText, $activityUrl);
    }

    private function getSystemPrompt(): string
    {
        return <<<'EOD'
Eres un extractor de datos especializado en sitios web académicos de la UNED. Tu tarea es analizar el HTML de una página de actividad de extensión y extraer información estructurada en formato JSON.

ESTRUCTURA HTML DE UNED:
IMPORTANTE: UNED usa atributos JSON incrustados en el HTML (data-datos, data-titulo, etc.). Busca específicamente:
- data-datos='{"titulo":"...", "categoria":"del X al Y de fecha", ...}'
- data-categoria para fechas
- <h2 itemprop="name"> o <title> para el título
- Secciones con clases como "detalleActividad", "informacion", "programa"
- Tablas de precios con filas para "Presencial", "On line", "On line diferido"

EXTRACCIÓN DE FECHAS:
- Busca en atributos data-categoria: "del 2 al 18 de febrero de 2026" → start: "2026-02-02", end: "2026-02-18"
- Formatos: "del X al Y de mes de año" o "X/XX/XXXX - Y/YY/XXXX"
- Horas: "De 10:00 a 13:00 h." o similar

MODALIDAD:
- "Online" → "online"
- "Presencial" → "in-person"
- "Online o presencial" → "hybrid"
- "A distancia" → "online"
- "Semipresencial" → "hybrid"

PRECIOS:
- La tabla de precios tiene filas para modalidades (Presencial, On line directo, On line diferido)
- Extrae TODAS las combinaciones de modalidad × tipo de usuario
- "Gratuita" o "Gratis" = 0 centimos
- Precios en CENTIMOS (120€ = 12000)

REGLAS DE EXTRACCIÓN:
1. Responde ÚNICAMENTE con el JSON válido, sin texto adicional
2. Si no encuentras un dato, usa null en lugar de inventarlo
3. Las fechas deben estar en formato ISO 8601 (YYYY-MM-DD)
EOD;
    }

    private function buildUserPrompt(string $html, string $activityUrl): string
    {
        // Extract only the body content and remove scripts/styles to reduce tokens
        $html = $this->cleanHtml($html);

        // Truncate HTML if too large (model limits)
        $html = substr($html, 0, 100000); // ~100k chars should be enough for body content

        $urlSafe = htmlspecialchars($html, ENT_QUOTES, 'UTF-8');

        return <<<EOD
Analiza el siguiente HTML de una actividad de extensión de la UNED y extrae la información estructurada según el esquema JSON proporcionado.

URL de la actividad: {$activityUrl}

INSTRUCCIONES ESPECÍFICAS:
1. Busca el título en etiquetas <h2>, <h1> o meta title
2. Extrae las fechas del texto que contenga patterns como "del X al Y de mes de año" o "X/XX/XXXX"
3. Para la modalidad: "Online o presencial" = hybrid, "Online" = online, "Presencial" = in-person
4. La tabla de precios: extrae cada fila (modalidad) × cada columna (tipo de usuario)
5. "Gratuita" o "Gratis" = 0 centimos
6. Extrae información de: "Dirigido por" (director), "Coordinado por" (coordinador), "Ponente" (speaker)
7. Si hay un programa detallado, extrae las sesiones con sus fechas y horas

HTML a analizar:
{$html}

Responde ÚNICAMENTE con el JSON válido siguiendo el esquema proporcionado.
EOD;
    }

    private function getJsonSchema(): string
    {
        return <<<'EOD'
{
  "title": "string (título completo de la actividad)",
  "description": "string (descripción del primer párrafo o null)",
  "center": "string (nombre del centro, ej: 'UNED A Coruña' o null)",
  "centerId": "number (ID del centro si existe, ej: 32 para A Coruña, o null)",
  "topic": {
    "primary": "string (área temática o null)",
    "secondary": ["array de subtemáticas o vacío"],
    "cycle": "string (nombre del ciclo si pertenece a uno, ej: 'IDIOMAS Y COMPETENCIAS LINGÜÍSTICAS' o null)"
  },
  "dates": {
    "start": "string (fecha inicio ISO YYYY-MM-DD o null)",
    "end": "string (fecha fin ISO YYYY-MM-DD o null)",
    "display": "string (texto original de fechas, ej: 'del 9 al 19 de febrero de 2026')"
  },
  "schedule": {
    "timeStart": "string (hora inicio, ej: '10:00' o null)",
    "timeEnd": "string (hora fin, ej: '13:00' o null)",
    "timezone": "string (huso horario, default: 'Europe/Madrid')",
    "sessions": [
      {
        "date": "string (YYYY-MM-DD)",
        "timeStart": "string (ej: '10:00')",
        "timeEnd": "string (ej: '13:00')",
        "title": "string (título de la sesión)",
        "location": "string (lugar específico, ej: 'Aula 23A')"
      }
    ]
  },
  "location": {
    "venue": "string (lugar específico, ej: 'Aula 23A' o null)",
    "center": "string (centro, ej: 'Centro UNED A Coruña' o null)",
    "address": "string (dirección postal si existe o null)",
    "city": "string (ciudad, ej: 'A Coruña' o null)"
  },
  "modality": {
    "type": "string (online|in-person|hybrid - mapear desde: 'Online o presencial'=hybrid, 'Online'=online, 'Presencial'=in-person)",
    "hasLive": "boolean (true si tiene opción en directo)",
    "hasRecorded": "boolean (true si tiene opción en diferido)",
    "details": ["array de strings con detalles, ej: ['presencial', 'online en directo', 'online en diferido']"]
  },
  "pricing": {
    "table": [
      {
        "modality": "string (presencial|online_directo|online_diferido - extraer de la fila)",
        "studentType": "string (nombre del tipo de usuario, ej: 'Matrícula Ordinaria', 'Alumnos UNED')",
        "amount": "number (precio en CENTIMOS, 45€ = 4500, 'Gratuita' = 0)",
        "currency": "string (EUR)",
        "display": "string (formato visual original)"
      }
    ]
  },
  "credits": {
    "ects": "number (créditos ECTS como decimal, ej: 1.0 o null)",
    "hours": "number (horas lectivas si se indica, ej: 25 o null)",
    "certificate": "string (tipo de certificado o null)",
    "status": "string (estado de los créditos, ej: 'en trámite' o null)"
  },
  "staff": {
    "director": {
      "name": "string (nombre completo o null)",
      "role": "string (cargo/afiliación o null)"
    },
    "coordinator": {
      "name": "string (nombre completo o null)",
      "role": "string (cargo/afiliación o null)"
    },
    "speakers": [
      {
        "name": "string (nombre completo)",
        "role": "string (cargo/afiliación)",
        "bio": "string (descripción completa o null)"
      }
    ]
  },
  "enrollment": {
    "open": "boolean (true si el periodo de matrícula está abierto)",
    "info": "string (información de matrícula o null)",
    "link": "string (URL de matrícula online si existe o null)"
  },
  "targetAudience": "string (público objetivo o null)",
  "requirements": {
    "prerequisites": ["array de requisitos previos"],
    "methodology": "string (metodología o null)",
    "evaluation": "string (sistema de evaluación o null)"
  },
  "metadata": {
    "url": "string (URL de la actividad)",
    "extractedAt": "string (timestamp de extracción)"
  }
}
EOD;
    }

    private function parseJsonResponse(string $jsonText, string $activityUrl): array
    {
        // Extract JSON from markdown code blocks if present
        if (preg_match('/```(?:json)?\s*(\{.+?\})\s*```/s', $jsonText, $match)) {
            $jsonText = $match[1];
        }

        $data = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);

        // Add URL metadata
        $data['metadata']['url'] = $activityUrl;
        $data['metadata']['extractedAt'] = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');

        return $data;
    }

    private function logSuccess(string $model, int $attempt, int $total): void
    {
        error_log(sprintf('[AI Extractor] SUCCESS: %s (attempt %d/%d)', $model, $attempt, $total));
    }

    private function logFailure(string $model, int $attempt, string $error): void
    {
        error_log(sprintf('[AI Extractor] FAILED: %s (attempt %d) - %s', $model, $attempt, $error));
    }

    /**
     * Clean HTML to reduce tokens - extract body and remove scripts/styles.
     */
    private function cleanHtml(string $html): string
    {
        // Extract body content
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        // Remove script tags and their content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove style tags and their content
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Remove link tags (stylesheets)
        $html = preg_replace('/<link\b[^>]*>/i', '', $html);

        // Remove meta tags (except description/title for context)
        $html = preg_replace('/<meta\b(?![^>]*(?:name="description"|property="og:title|og:description"))[^>]*>/i', '', $html);

        return $html;
    }
}
