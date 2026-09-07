<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

final class UniqueWrite
{
    public static function isDuplicate(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($e->getMessage(), 'Duplicate entry');
    }

    public static function isDeadlock(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '40001' || $driverCode === 1213 || str_contains($e->getMessage(), 'Deadlock');
    }

    public static function retry(callable $callback, int $attempts = 4): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                if (self::isDeadlock($e) && $attempt < $attempts - 1) {
                    $attempt++;
                    usleep(25000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }
}
