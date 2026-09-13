<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $this->failFastIfMysqlUnreachable();

        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Avoid multi-minute hangs on Windows when mysqld is stopped.
     * Feature tests require MySQL DB `interntrack_testing`.
     */
    private function failFastIfMysqlUnreachable(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $timeout = (float) (getenv('DB_TIMEOUT') ?: 5);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket) {
            fclose($socket);

            return;
        }

        fwrite(STDERR, <<<TXT

INTERNTRACK tests need MySQL running.

  Host: {$host}:{$port}
  Database: interntrack_testing
  Error: {$errstr} ({$errno})

Start MySQL (XAMPP/WAMP/service), create the database, then re-run:

  CREATE DATABASE IF NOT EXISTS interntrack_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  php artisan test --testsuite=Feature


TXT);

        exit(1);
    }
}
