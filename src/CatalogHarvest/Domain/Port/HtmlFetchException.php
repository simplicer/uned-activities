<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

/**
 * Exception thrown when HTML fetch fails.
 */
final class HtmlFetchException extends \RuntimeException
{
    public static function fromUrl(string $url, int $code, ?string $message = null): self
    {
        $suffix = $message !== null ? " - {$message}" : '';

        return new self(
            "Failed to fetch URL '{$url}': HTTP {$code}{$suffix}",
            $code
        );
    }

    public static function networkError(string $url, string $error): self
    {
        return new self("Network error fetching '{$url}': {$error}");
    }

    public static function timeout(string $url): self
    {
        return new self("Timeout fetching '{$url}'");
    }
}
