<?php

namespace App\Console\Commands;

use App\Support\InternTrackBackup;
use Illuminate\Console\Command;

class BackupInternTrack extends Command
{
    protected $signature = 'interntrack:backup
                            {--path= : Destination directory (defaults to storage/app/backups/{timestamp})}
                            {--skip-database : Copy files only}';

    protected $description = 'Backup InternTrack MySQL records and persistent private file storage. Uses DB_* from environment; does not hardcode passwords.';

    public function handle(InternTrackBackup $backup): int
    {
        $root = $backup->backupDirectory($this->option('path') ?: null);
        $this->info('Backup directory: '.$root);

        $files = $backup->copyPrivateStorage($root);
        $this->info("Copied {$files} private storage file(s).");

        if (! $this->option('skip-database')) {
            $dump = $backup->dumpDatabase($root);
            if ($dump) {
                $this->info('Database dump: '.$dump);
            } else {
                $this->warn('Database dump skipped or failed. See backup directory notes. File storage copy still succeeded.');
            }
        }

        $this->newLine();
        $this->line('This backup supports restore on the same class of environment.');
        $this->line('Off-site disaster recovery is NOT implemented by this command.');

        return self::SUCCESS;
    }
}
