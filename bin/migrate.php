<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use Camagru\Database;

Database::query('CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
Database::transaction(static function (): void {
    Database::query("SELECT pg_advisory_xact_lock(4298042)");
    foreach (glob(dirname(__DIR__) . '/migrations/*.sql') as $file) {
        $version = basename($file);
        if (!Database::query('SELECT 1 FROM schema_migrations WHERE version=?', [$version])->fetchColumn()) {
            Database::connection()->exec(file_get_contents($file));
            Database::query('INSERT INTO schema_migrations(version) VALUES (?)', [$version]);
        }
    }
});
