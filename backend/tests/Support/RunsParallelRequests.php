<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

trait RunsParallelRequests
{
    /**
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array{exit:int|null, stdout:string, stderr:string, json:?array}>
     */
    protected function parallelRequests(array $jobs): array
    {
        $dir = storage_path('app/concurrency-jobs');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $processes = [];
        foreach (array_values($jobs) as $i => $job) {
            $file = $dir.DIRECTORY_SEPARATOR.'job-'.getmypid().'-'.$i.'-'.bin2hex(random_bytes(4)).'.json';
            file_put_contents($file, json_encode($job));
            $process = new Process(
                [PHP_BINARY, base_path('artisan'), 'interntrack:concurrency-worker', $file],
                base_path(),
                $this->workerEnvironment()
            );
            $process->setTimeout(90);
            $processes[] = [$process, $file];
        }

        foreach ($processes as [$process]) {
            $process->start();
        }

        $results = [];
        foreach ($processes as [$process, $file]) {
            $process->wait();
            $stdout = trim($process->getOutput());
            $results[] = [
                'exit' => $process->getExitCode(),
                'stdout' => $stdout,
                'stderr' => $process->getErrorOutput(),
                'json' => json_decode($stdout, true),
            ];
            @unlink($file);
        }

        return $results;
    }

    /** @return array<string, string> */
    protected function workerEnvironment(): array
    {
        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        return array_merge($env, [
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'interntrack_testing',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'MISD_USE_MOCK' => 'true',
        ]);
    }
}
