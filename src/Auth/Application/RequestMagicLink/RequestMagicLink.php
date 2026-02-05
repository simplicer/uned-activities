<?php

declare(strict_types=1);

namespace Auth\Application\RequestMagicLink;

use Auth\Domain\Entity\MagicLinkToken;
use Auth\Domain\AuthenticationTokenStorage\MagicTokenRepository;
use Auth\Domain\ValueObject\MagicToken;
use Shared\Infrastructure\Email\SmtpEmailService;

/**
 * Request a magic link for passwordless authentication.
 */
final readonly class RequestMagicLink
{
    private const int DEFAULT_EXPIRE_MINUTES = 15;

    public function __construct(
        private MagicTokenRepository $tokenRepository,
        private SmtpEmailService $emailService,
        private string $frontendUrl,
    ) {
    }

    public function execute(string $email, ?int $expireMinutes = null): void
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            throw new \InvalidArgumentException('Email cannot be empty');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        // Clean up old expired tokens
        $this->tokenRepository->deleteExpired();

        // Generate magic token
        $token = MagicToken::generate();
        $expiresAt = new \DateTimeImmutable(
            \sprintf('+%d minutes', $expireMinutes ?? self::DEFAULT_EXPIRE_MINUTES)
        );

        $magicLink = MagicLinkToken::create($email, $token, $expiresAt);
        $this->tokenRepository->save($magicLink);

        // Send email
        $magicLinkUrl = \sprintf('%s/?token=%s', rtrim($this->frontendUrl, '/'), $token->toString());
        $this->emailService->sendMagicLink($email, $magicLinkUrl);
    }
}
