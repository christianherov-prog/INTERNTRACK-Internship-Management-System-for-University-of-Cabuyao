<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SupervisorIds;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ConcurrencyWorkerCommand extends Command
{
    protected $signature = 'interntrack:concurrency-worker {payload : Path to a JSON job file}';

    protected $description = 'Execute one concurrency-harness HTTP or ID-allocation job';

    public function handle(): int
    {
        $path = (string) $this->argument('payload');
        $job = json_decode((string) file_get_contents($path), true);
        if (! is_array($job)) {
            $this->line(json_encode(['ok' => false, 'error' => 'invalid payload']));

            return self::FAILURE;
        }

        if (($job['action'] ?? 'http') === 'next-supervisor-id') {
            try {
                $user = User::create([
                    'email' => 'conc-'.Str::uuid().'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'supervisor',
                    'is_active' => true,
                ]);
                $this->line(json_encode([
                    'ok' => true,
                    'status' => 200,
                    'faculty_number' => SupervisorIds::ensureFor($user),
                    'user_id' => $user->id,
                ]));

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->line(json_encode([
                    'ok' => false,
                    'status' => 500,
                    'error' => $e->getMessage(),
                ]));

                return self::FAILURE;
            }
        }

        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if (! empty($job['token'])) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$job['token'];
        }
        foreach (($job['headers'] ?? []) as $header => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        $request = Request::create(
            (string) $job['uri'],
            strtoupper((string) ($job['method'] ?? 'GET')),
            [],
            [],
            [],
            $server,
            json_encode($job['json'] ?? new \stdClass())
        );

        $response = $kernel->handle($request);
        $body = $response->getContent();
        $decoded = json_decode($body, true);

        $this->line(json_encode([
            'ok' => $response->isSuccessful(),
            'status' => $response->getStatusCode(),
            'body' => $decoded ?? $body,
        ]));

        $kernel->terminate($request, $response);

        return self::SUCCESS;
    }
}
