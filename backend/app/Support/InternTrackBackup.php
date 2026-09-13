<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Local backup of MySQL + persistent file storage.
 * This is restart/backup recovery, not off-site disaster recovery.
 */
final class InternTrackBackup
{
    public function backupDirectory(?string $destination = null): string
    {
        $root = $destination ?: storage_path('app/backups/'.now()->format('Ymd_His'));
        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($root.'/storage-private');

        return $root;
    }

    public function copyPrivateStorage(string $backupRoot, ?string $source = null): int
    {
        $source = $source ?: storage_path('app/private');
        $target = $backupRoot.'/storage-private';
        File::ensureDirectoryExists($target);
        if (! File::isDirectory($source)) {
            return 0;
        }

        File::copyDirectory($source, $target);

        return $this->countFiles($target);
    }

    public function dumpDatabase(string $backupRoot): ?string
    {
        $conn = config('database.connections.'.config('database.default'));
        if (($conn['driver'] ?? '') !== 'mysql') {
            return null;
        }

        $dumpPath = $backupRoot.'/database.sql';
        $mysqldump = $this->binary('mysqldump');
        if (! $mysqldump) {
            File::put($backupRoot.'/database.dump.skipped.txt', "mysqldump was not found on PATH.\n");

            return null;
        }

        $process = new Process([
            $mysqldump,
            '--host='.($conn['host'] ?? '127.0.0.1'),
            '--port='.(string) ($conn['port'] ?? '3306'),
            '--user='.($conn['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            $conn['database'],
        ]);
        $process->setTimeout(300);
        if (($conn['password'] ?? '') !== '') {
            $process->setEnv(['MYSQL_PWD' => (string) $conn['password']]);
        }
        $process->run();
        if (! $process->isSuccessful()) {
            File::put($backupRoot.'/database.dump.error.txt', $process->getErrorOutput() ?: $process->getOutput());

            return null;
        }

        File::put($dumpPath, $process->getOutput());

        return $dumpPath;
    }

    public function restoreDatabase(string $sqlPath, string $database): bool
    {
        $conn = config('database.connections.'.config('database.default'));
        $mysql = $this->binary('mysql');
        if (! $mysql || ! is_file($sqlPath)) {
            return false;
        }

        $process = Process::fromShellCommandline(
            escapeshellarg($mysql)
            .' --host='.escapeshellarg((string) ($conn['host'] ?? '127.0.0.1'))
            .' --port='.escapeshellarg((string) ($conn['port'] ?? '3306'))
            .' --user='.escapeshellarg((string) ($conn['username'] ?? 'root'))
            .' '.escapeshellarg($database)
        );
        $process->setTimeout(300);
        $process->setInput(File::get($sqlPath));
        if (($conn['password'] ?? '') !== '') {
            $process->setEnv(['MYSQL_PWD' => (string) $conn['password']]);
        }
        $process->run();

        return $process->isSuccessful();
    }

    public function restorePrivateStorage(string $backupRoot, ?string $target = null): int
    {
        $source = $backupRoot.'/storage-private';
        $dest = $target ?: storage_path('app/private');
        if (! File::isDirectory($source)) {
            return 0;
        }
        File::ensureDirectoryExists($dest);
        File::copyDirectory($source, $dest);

        return $this->countFiles($dest);
    }

    private function binary(string $name): ?string
    {
        $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $name]);
        $finder->run();
        if (! $finder->isSuccessful()) {
            return null;
        }

        $line = trim(strtok($finder->getOutput(), "\n"));

        return $line !== '' ? $line : null;
    }

    private function countFiles(string $dir): int
    {
        if (! File::isDirectory($dir)) {
            return 0;
        }
        $count = 0;
        foreach (File::allFiles($dir) as $file) {
            $count++;
        }

        return $count;
    }
}
