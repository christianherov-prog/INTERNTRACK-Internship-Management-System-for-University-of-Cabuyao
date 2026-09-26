<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Sets one locally chosen password on every account of a restored controlled
 * demonstration database, so no password has to be published in the
 * repository. Refuses to run in production.
 */
class SetDemoPasswords extends Command
{
    protected $signature = 'interntrack:set-demo-passwords
                            {--password= : Password to set (defaults to the DEMO_PASSWORD environment value)}';

    protected $description = 'Set a local password on all accounts of the restored controlled demonstration database.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to change passwords in production.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: env('DEMO_PASSWORD', ''));
        if (strlen($password) < 8) {
            $this->error('Provide a password of at least 8 characters with --password=... or DEMO_PASSWORD in backend/.env.');

            return self::FAILURE;
        }

        $hash = Hash::make($password);
        $count = User::query()->update([
            'password' => $hash,
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'remember_token' => null,
        ]);

        $this->info("Password set for {$count} account(s).");

        return self::SUCCESS;
    }
}
