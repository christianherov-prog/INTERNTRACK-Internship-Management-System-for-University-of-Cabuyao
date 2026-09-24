<?php
/** Reuse ONLY the fully migrated disposable audit server; tests still roll back transactions. */
require __DIR__.'/../vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'interntrack_testing') {
    throw new RuntimeException('Evidence runner requires testing / interntrack_testing.');
}
$port = (int) getenv('DB_PORT');
$pdo = new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=interntrack_testing', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$dataDir = str_replace('\\', '/', $pdo->query('SELECT @@datadir')->fetchColumn());
$expectedDir = str_replace('\\', '/', realpath(__DIR__.'/../../..')).'/interntrack-unit-mysql-20260920/';
if (strtolower($dataDir) !== strtolower($expectedDir)) {
    throw new RuntimeException('Refusing non-disposable MySQL data directory: '.$dataDir);
}
$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob(__DIR__.'/../database/migrations/*.php') as $migration) {
    if (getenv('EVIDENCE_REBUILD') !== '1' && !in_array(basename($migration, '.php'), $applied, true)) {
        throw new RuntimeException('Unapplied migration: '.$migration);
    }
}
$pdo = null;
// RefreshDatabase begins/rolls back a transaction for each test, without rebuilding
// the already verified schema once per module. Concurrency/reset tests are excluded.
Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = getenv('EVIDENCE_REBUILD') !== '1';
