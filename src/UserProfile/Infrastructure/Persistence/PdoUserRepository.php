<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Persistence;

use PDO;
use UserProfile\Domain\Entity\User;
use UserProfile\Domain\Port\UserRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * PDO implementation of UserRepository.
 */
final class PdoUserRepository implements UserRepository
{
    private const TABLE = 'users';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(User $user): void
    {
        $exists = $this->existsById($user->id);

        if ($exists) {
            $this->update($user);
        } else {
            $this->insert($user);
        }
    }

    public function findById(UserId $id): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE email = :email'
        );

        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    private function existsById(UserId $id): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function insert(User $user): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, email, full_name, preferences, created_at, updated_at
            ) VALUES (
                :id, :email, :full_name, :preferences, :created_at, :updated_at
            )'
        );

        $stmt->execute([
            'id' => $user->id->toString(),
            'email' => $user->email,
            'full_name' => $user->fullName,
            'preferences' => json_encode($user->preferences),
            'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    private function update(User $user): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                full_name = :full_name,
                preferences = :preferences,
                updated_at = :updated_at
            WHERE id = :id'
        );

        $stmt->execute([
            'id' => $user->id->toString(),
            'full_name' => $user->fullName,
            'preferences' => json_encode($user->preferences),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function mapToEntity(array $row): User
    {
        return User::fromPersistence(
            UserId::fromString($row['id']),
            $row['email'],
            $row['full_name'] ?: null,
            json_decode($row['preferences'] ?? '{}', true),
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
