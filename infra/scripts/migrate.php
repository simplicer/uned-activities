<?php

declare(strict_types=1);

/**
 * Migration runner - idempotent.
 * Usage: php infra/scripts/migrate.php [up|down]
 */

$direction = $argv[1] ?? 'up';
$migrationsDir = __DIR__ . '/../migrations';

// Load environment
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '5432';
$name = $_ENV['DB_NAME'] ?? 'uned_activities';
$user = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? 'postgres';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$name}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
} catch (PDOException $e) {
    echo "[ERROR] Failed to connect to database: {$e->getMessage()}\n";
    exit(1);
}

// Create migrations table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        executed_at TIMESTAMP DEFAULT NOW()
    )
");

if ($direction === 'up') {
    $files = glob("{$migrationsDir}/*.up.sql");
    sort($files);
    
    foreach ($files as $file) {
        $name = basename($file);
        
        $stmt = $pdo->prepare("SELECT id FROM schema_migrations WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->fetch()) {
            echo "[SKIP] {$name} (already executed)\n";
            continue;
        }
        
        $sql = file_get_contents($file);
        try {
            $pdo->exec($sql);
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES (?)")->execute([$name]);
            echo "[OK] {$name}\n";
        } catch (PDOException $e) {
            echo "[ERROR] {$name}: {$e->getMessage()}\n";
            exit(1);
        }
    }
} elseif ($direction === 'down') {
    $stmt = $pdo->query("SELECT name FROM schema_migrations ORDER BY id DESC LIMIT 1");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $migration = $row['name'];
        $rollbackFile = str_replace('.up.sql', '.down.sql', "{$migrationsDir}/{$migration}");
        
        if (file_exists($rollbackFile)) {
            $sql = file_get_contents($rollbackFile);
            try {
                $pdo->exec($sql);
                $pdo->prepare("DELETE FROM schema_migrations WHERE name = ?")->execute([$migration]);
                echo "[OK] Rolled back: {$migration}\n";
            } catch (PDOException $e) {
                echo "[ERROR] Rollback failed: {$e->getMessage()}\n";
                exit(1);
            }
        } else {
            echo "[ERROR] No rollback script for {$migration}\n";
            exit(1);
        }
    } else {
        echo "[INFO] No migrations to rollback\n";
    }
} else {
    echo "[ERROR] Invalid direction. Use 'up' or 'down'\n";
    exit(1);
}
