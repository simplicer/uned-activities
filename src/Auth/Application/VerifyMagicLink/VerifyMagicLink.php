<?php

declare(strict_types=1);

namespace Auth\Application\VerifyMagicLink;

use Auth\Domain\AuthenticationTokenStorage\MagicTokenRepository;
use Auth\Domain\ValueObject\MagicToken;
use UserProfile\Domain\Entity\User;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Verify a magic link and authenticate the user.
 */
final readonly class VerifyMagicLink
{
    public function __construct(
        private MagicTokenRepository $tokenRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function execute(string $tokenString): VerifyMagicLinkResult
    {
        try {
            $token = MagicToken::fromString($tokenString);
        } catch (\InvalidArgumentException $e) {
            return new VerifyMagicLinkResult(
                success: false,
                user: null,
                error: 'Invalid token format',
            );
        }

        $magicLink = $this->tokenRepository->findByToken($token);

        if ($magicLink === null) {
            return new VerifyMagicLinkResult(
                success: false,
                user: null,
                error: 'Token not found',
            );
        }

        if (!$magicLink->isValid()) {
            if ($magicLink->isExpired()) {
                return new VerifyMagicLinkResult(
                    success: false,
                    user: null,
                    error: 'Token has expired',
                );
            }

            if ($magicLink->isUsed()) {
                return new VerifyMagicLinkResult(
                    success: false,
                    user: null,
                    error: 'Token has already been used',
                );
            }

            return new VerifyMagicLinkResult(
                success: false,
                user: null,
                error: 'Token is invalid',
            );
        }

        // Mark token as used
        $this->tokenRepository->markAsUsed($token);

        // Find or create user
        $user = $this->userRepository->findByEmail($magicLink->email);

        if ($user === null) {
            // Create new user
            $user = User::create(
                UserId::generate(),
                $magicLink->email,
            );
            $this->userRepository->save($user);
        }

        return new VerifyMagicLinkResult(
            success: true,
            user: $user,
            error: null,
        );
    }
}

/**
 * Result of magic link verification.
 */
final readonly class VerifyMagicLinkResult
{
    public function __construct(
        public bool $success,
        public ?User $user,
        public ?string $error,
    ) {
    }
}
