<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\VerifyMagicLink;

use Auth\Application\VerifyMagicLink\VerifyMagicLink;
use Auth\Domain\AuthenticationTokenStorage\MagicTokenRepository;
use Auth\Domain\Entity\MagicLinkToken;
use Auth\Domain\ValueObject\MagicToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UserProfile\Domain\Entity\User;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Domain\ValueObject\UserId;

#[CoversClass(VerifyMagicLink::class)]
final class VerifyMagicLinkTest extends TestCase
{
    public function testVerifiesValidTokenAndConsumesIt(): void
    {
        $token = MagicToken::generate();
        $link = MagicLinkToken::create('user@example.com', $token, new \DateTimeImmutable('+15 minutes'));
        $repo = new FakeTokenRepository([MagicLinkToken::hashToken($token->toString()) => $link], markAsUsedResult: true);
        $users = new FakeUserRepository();

        $result = (new VerifyMagicLink($repo, $users))->execute($token->toString());

        self::assertTrue($result->success);
        self::assertNotNull($result->user);
        self::assertSame('user@example.com', $result->user->email);
        self::assertTrue($repo->markedAsUsed);
    }

    public function testFailsClosedWhenSingleUseConsumeReportsFailure(): void
    {
        // Regression: markAsUsed implements the atomic single-use guard
        // (UPDATE ... WHERE used_at IS NULL). Its boolean result was discarded,
        // so a raced/replayed token still authenticated. The use case must
        // fail closed when the consume reports zero affected rows.
        $token = MagicToken::generate();
        $link = MagicLinkToken::create('user@example.com', $token, new \DateTimeImmutable('+15 minutes'));
        $repo = new FakeTokenRepository([MagicLinkToken::hashToken($token->toString()) => $link], markAsUsedResult: false);
        $users = new FakeUserRepository();

        $result = (new VerifyMagicLink($repo, $users))->execute($token->toString());

        self::assertFalse($result->success, 'a failed consume must not authenticate');
        self::assertNull($result->user);
        self::assertSame('Token has already been used', $result->error);
    }

    public function testRejectsUnknownToken(): void
    {
        $repo = new FakeTokenRepository([], markAsUsedResult: true);
        $users = new FakeUserRepository();

        $result = (new VerifyMagicLink($repo, $users))->execute(MagicToken::generate()->toString());

        self::assertFalse($result->success);
        self::assertSame('Token not found', $result->error);
    }
}

final class FakeTokenRepository implements MagicTokenRepository
{
    /** @var array<string, MagicLinkToken> */
    public array $tokens;
    public bool $markedAsUsed = false;

    public function __construct(array $tokens, private readonly bool $markAsUsedResult)
    {
        $this->tokens = $tokens;
    }

    #[\Override]
    public function save(MagicLinkToken $token): void
    {
        $this->tokens[$token->hashToken()] = $token;
    }

    #[\Override]
    public function findByToken(MagicToken $token): ?MagicLinkToken
    {
        return $this->tokens[MagicLinkToken::hashToken($token->toString())] ?? null;
    }

    #[\Override]
    public function deleteExpired(): int
    {
        return 0;
    }

    #[\Override]
    public function markAsUsed(MagicToken $token): bool
    {
        $this->markedAsUsed = true;

        return $this->markAsUsedResult;
    }
}

final class FakeUserRepository implements UserRepository
{
    public ?User $created = null;

    #[\Override]
    public function save(User $user): void
    {
        $this->created = $user;
    }

    #[\Override]
    public function findById(UserId $id): ?User
    {
        return null;
    }

    #[\Override]
    public function findByEmail(string $email): ?User
    {
        return null;
    }

    #[\Override]
    public function deleteById(UserId $id): void
    {
    }

    #[\Override]
    public function setPasswordHash(UserId $id, string $hash): void
    {
    }

    #[\Override]
    public function getPasswordHashByEmail(string $email): ?string
    {
        return null;
    }
}
