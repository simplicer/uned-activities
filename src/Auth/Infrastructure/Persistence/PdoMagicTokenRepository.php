<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence;

use Auth\Domain\Entity\MagicLinkToken;
use Auth\Domain\Port\MagicTokenRepository;
use Auth\Domain\ValueObject\MagicToken;
use PDO;

/**
 * PDO implementation of magic token repository.
 */
final class PdoMagicTokenRepository implements MagicTokenRepository
{
    private const string TABLE = 'magic_tokens';

    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function save(MagicLinkToken $token): void
    {
        $sql = sprintf(
            'INSERT INTO %s (id, email, token, expires_at, used_at, created_at)
            VALUES (:id, :email, :token, :expires_at, :used_at, :created_at)',
            self::TABLE
        );

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'id' => $token->id,
            'email' => $token->email,
            'token' => $token->token->toString(),
            'expires_at' => $token->expiresAt->format('Y-m-d H:i:s'),
            'used_at' => $token->usedAt?->format('Y-m-d H:i:s'),
            'created_at' => $token->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByToken(MagicToken $token): ?MagicLinkToken
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE token = :token ORDER BY created_at DESC LIMIT 1',
            self::TABLE
        );

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['token' => $token->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return MagicLinkToken::fromPersistence(
            $row['id'],
            $row['email'],
            $row['token'],
            new \DateTimeImmutable($row['expires_at']),
            isset($row['used_at']) && $row['used_at'] !== null
                ? new \DateTimeImmutable($row['used_at'])
                : null,
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function deleteExpired(): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE expires_at < NOW() OR used_at IS NOT NULL',
            self::TABLE
        );

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function markAsUsed(MagicToken $token): bool
    {
        $sql = sprintf(
            'UPDATE %s SET used_at = NOW() WHERE token = :token AND used_at IS NULL',
            self::TABLE
        );

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['token' => $token->toString()]);

        return $stmt->rowCount() > 0;
    }
}
