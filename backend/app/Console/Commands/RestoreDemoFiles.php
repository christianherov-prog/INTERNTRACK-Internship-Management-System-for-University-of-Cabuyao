<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies the controlled demonstration files (synthetic signatures and
 * stand-in documents referenced by the database snapshot) from
 * database/demo-files/private into the private storage disk.
 */
class RestoreDemoFiles extends Command
{
    protected $signature = 'interntrack:restore-demo-files
                            {--force : Overwrite files that already exist}
                            {--target= : Destination directory (defaults to storage/app/private)}';

    protected $description = 'Copy the controlled demonstration files referenced by the database snapshot into storage/app/private.';

    public function handle(): int
    {
        $source = database_path('demo-files/private');
        $target = rtrim((string) ($this->option('target') ?: storage_path('app/private')), '/\\');

        if (! File::isDirectory($source)) {
            $this->error("Demo files not found at {$source}.");

            return self::FAILURE;
        }

        $copied = 0;
        $skipped = 0;
        foreach (File::allFiles($source) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $destination = $target.'/'.$relative;

            if (File::exists($destination) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($file->getPathname(), $destination);
            $copied++;
        }

        $this->info("Demo files copied: {$copied}; already present (kept): {$skipped}.");

        return self::SUCCESS;
    }
}
