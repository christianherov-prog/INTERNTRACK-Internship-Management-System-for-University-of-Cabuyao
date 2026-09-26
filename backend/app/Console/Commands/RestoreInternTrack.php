<?php

namespace App\Console\Commands;

use App\Support\InternTrackBackup;
use Illuminate\Console\Command;

class RestoreInternTrack extends Command
{
    protected $signature = 'interntrack:restore
                            {backup : Path to a directory created by interntrack:backup}
                            {--database= : Target MySQL database name (required for SQL restore)}
                            {--files-only : Restore private storage only}
                            {--force : Required. Refuses to run without it.}';

    protected $description = 'Restore InternTrack from a local backup directory. Never run against production without an explicit ops plan.';

    public function handle(InternTrackBackup $backup): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to restore without --force.');

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->argument('backup'), '\\/');
        if (! is_dir($dir)) {
            $this->error('Backup directory not found.');

            return self::FAILURE;
        }

        $default = (string) config('database.connections.'.config('database.default').'.database');
        $target = (string) ($this->option('database') ?: '');
        if (! $this->option('files-only')) {
            if ($target === '' || $target === $default && app()->environment('production')) {
                $this->error('Pass --database= with an isolated test database name. Refusing to restore over the current production database.');

                return self::FAILURE;
            }
            $sql = $dir.'/database.sql';
            if (! is_file($sql)) {
                $this->error('database.sql not found in backup. Use --files-only to restore storage.');

                return self::FAILURE;
            }
            if (! $backup->restoreDatabase($sql, $target)) {
                $this->error('Database restore failed.');

                return self::FAILURE;
            }
            $this->info('Restored database '.$target);
        }

        $count = $backup->restorePrivateStorage($dir);
        $this->info("Restored {$count} private storage path(s).");

        return self::SUCCESS;
    }
}
