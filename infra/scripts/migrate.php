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

// Detect and/or create schema_migrations table.
// We support both:
// - legacy: schema_migrations(version varchar)
// - current: schema_migrations(name varchar, executed_at timestamp)
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(255) PRIMARY KEY)");

$cols = $pdo
    ->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'schema_migrations'")
    ->fetchAll(PDO::FETCH_COLUMN);
$cols = array_map('strval', $cols ?: []);

$keyCol = null;
if (in_array('name', $cols, true)) {
    $keyCol = 'name';
} elseif (in_array('version', $cols, true)) {
    $keyCol = 'version';
} else {
    echo "[ERROR] schema_migrations exists but has no known key column (name/version)\n";
    exit(1);
}

if ($direction === 'up') {
    $files = glob("{$migrationsDir}/*.up.sql");
    sort($files);
    
    foreach ($files as $file) {
        $name = basename($file);
        $key = $name;
        if ($keyCol === 'version') {
            // Legacy schema_migrations.version is often very short (e.g. varchar(14)).
            // Store only the base migration id (e.g. "001_init") instead of full filename.
            $key = preg_replace('/\\.up\\.sql$/', '', $name) ?? $name;
        }
        
        $stmt = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE {$keyCol} = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            echo "[SKIP] {$name} (already executed)\n";
            continue;
        }
        
        $sql = file_get_contents($file);
        try {
            $pdo->exec($sql);
            $pdo->prepare("INSERT INTO schema_migrations ({$keyCol}) VALUES (?)")->execute([$key]);
            echo "[OK] {$name}\n";
        } catch (PDOException $e) {
            echo "[ERROR] {$name}: {$e->getMessage()}\n";
            exit(1);
        }
    }
} elseif ($direction === 'down') {
    // Prefer executed_at if present, otherwise fall back to key column ordering.
    if (in_array('executed_at', $cols, true)) {
        $stmt = $pdo->query("SELECT {$keyCol} AS k FROM schema_migrations ORDER BY executed_at DESC LIMIT 1");
    } else {
        $stmt = $pdo->query("SELECT {$keyCol} AS k FROM schema_migrations ORDER BY {$keyCol} DESC LIMIT 1");
    }
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $migration = (string) $row['k'];
        if ($keyCol === 'version') {
            $rollbackFile = "{$migrationsDir}/{$migration}.down.sql";
        } else {
            $rollbackFile = str_replace('.up.sql', '.down.sql', "{$migrationsDir}/{$migration}");
        }
        
        if (file_exists($rollbackFile)) {
            $sql = file_get_contents($rollbackFile);
            try {
                $pdo->exec($sql);
                $pdo->prepare("DELETE FROM schema_migrations WHERE {$keyCol} = ?")->execute([$migration]);
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
