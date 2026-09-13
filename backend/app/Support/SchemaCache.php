<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Request-lifetime cache for Schema::hasTable — information_schema checks
 * showed up repeatedly on student dashboard/attendance (1ms+ each).
 */
final class SchemaCache
{
    /** @var array<string, bool> */
    private static array $tables = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function flush(): void
    {
        self::$tables = [];
    }
}
