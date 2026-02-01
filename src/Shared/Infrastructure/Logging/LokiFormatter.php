<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Loki JSON formatter for Monolog.
 *
 * Formats log entries in Grafana Loki JSON format.
 */
final class LokiFormatter extends JsonFormatter
{
    private const BATCH_SIZE = 100;

    private array $batch = [];
    private string $streamName;

    public function __construct(string $streamName = 'uned-app')
    {
        parent::__construct();
        $this->streamName = $streamName;
    }

    public function format(LogRecord $record): string
    {
        $entry = [
            'streams' => [
                [
                    'stream' => $this->buildStreamLabels($record),
                    'values' => [
                        [
                            (string) ($record->datetime->format('U')), // nanosecond placeholder
                            $record->formatted,
                        ],
                    ],
                ],
            ],
        ];

        return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Build Loki stream labels from record context.
     *
     * @return array<string, string>
     */
    private function buildStreamLabels(LogRecord $record): array
    {
        $labels = [
            'job' => $this->streamName,
            'level' => $record->level->getName(),
            'app' => 'uned-activities-finder',
        ];

        // Add contextual labels if available
        $context = $record->context;
        if (isset($context['request_id'])) {
            $labels['request_id'] = $context['request_id'];
        }
        if (isset($context['user_id'])) {
            $labels['user_id'] = $context['user_id'];
        }
        if (isset($context['action'])) {
            $labels['action'] = $context['action'];
        }

        return $labels;
    }
}
