<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Loki JSON formatter for Monolog.
 *
 * Formats log entries as Loki-friendly JSON lines.
 */
final class LokiFormatter extends JsonFormatter
{
    public function __construct(private readonly string $streamName = 'uned-app')
    {
        parent::__construct();
    }

    #[\Override]
    public function format(LogRecord $record): string
    {
        $entry = [
            'timestamp' => $record->datetime->format(DATE_ATOM),
            'level' => $record->level->getName(),
            'message' => $record->message,
            'logger' => $record->channel,
            'app' => 'uned-activities-finder',
            'context' => $record->context,
        ];

        $labels = $this->buildStreamLabels($record);
        if ($labels !== [] && $labels !== null) {
            $entry['labels'] = $labels;
        }

        $result = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($result === false) {
            return '{"message":"log_format_error"}';
        }

        return $result . "\n";
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
