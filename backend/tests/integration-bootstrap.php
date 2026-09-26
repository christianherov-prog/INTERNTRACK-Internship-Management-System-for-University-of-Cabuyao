<?php
require __DIR__.'/evidence-bootstrap.php';
// The preceding guard positively verifies the disposable server/database/schema.
// Rebuild once for this process so committed concurrency fixtures cannot leak in.
// Migrations legitimately seed records: a clean migrated schema is not empty.
Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
