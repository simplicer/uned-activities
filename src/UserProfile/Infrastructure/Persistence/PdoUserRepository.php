<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Persistence;

use PDO;
use UserProfile\Domain\Entity\User;
use UserProfile\Domain\UserDataStorage\UserRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * PDO implementation of UserRepository.
 */
final readonly class PdoUserRepository implements UserRepository
{
    private const string TABLE = 'users';

    public function __construct(private PDO $connection)
    {
    }

    #[\Override]
    public function save(User $user): void
    {
        $exists = $this->existsById($user->id);

        if ($exists) {
            $this->update($user);
        } else {
            $this->insert($user);
        }
    }

    #[\Override]
    public function findById(UserId $id): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE email = :email'
        );

        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function deleteById(UserId $id): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
    }

    #[\Override]
    public function setPasswordHash(UserId $id, string $hash): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET password_hash = :hash, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id->toString(),
            'hash' => $hash,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    #[\Override]
    public function getPasswordHashByEmail(string $email): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT password_hash FROM ' . self::TABLE . ' WHERE email = :email'
        );

        $stmt->execute(['email' => $email]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || $hash === null || $hash === '') {
            return null;
        }

        return (string) $hash;
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
                id, email, full_name, preferences, password_hash, created_at, updated_at
            ) VALUES (
                :id, :email, :full_name, :preferences, :password_hash, :created_at, :updated_at
            )'
        );

        $stmt->execute([
            'id' => $user->id->toString(),
            'email' => $user->email,
            'full_name' => $user->fullName,
            'preferences' => json_encode($user->preferences),
            'password_hash' => null,
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
            $row['full_name'] !== '' ? $row['full_name'] : null,
            json_decode($row['preferences'] ?? '{}', true),
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
