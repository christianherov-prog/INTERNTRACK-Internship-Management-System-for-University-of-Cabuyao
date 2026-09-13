<?php

namespace App\Console\Commands;

use App\Models\Internship;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetSupervisorAccounts extends Command
{
    protected $signature = 'supervisors:reset {--force : Delete without confirmation}';

    protected $description = 'Delete all supervisor accounts so the next registration is SUP-0001';

    public function handle(): int
    {
        $count = User::withTrashed()->where('role', 'supervisor')->count();

        if ($count === 0) {
            $this->info('No supervisor accounts found. Next registration will be SUP-0001.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} supervisor account(s)? Internships will be unlinked.", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(function () {
            $users = User::withTrashed()->where('role', 'supervisor')->get();
            $ids = $users->pluck('id');

            Internship::whereIn('supervisor_id', $ids)->update(['supervisor_id' => null]);
            SupervisorInviteToken::whereIn('supervisor_user_id', $ids)->update(['supervisor_user_id' => null]);
            SupervisorProfile::whereIn('user_id', $ids)->delete();
            Notification::whereIn('user_id', $ids)->delete();

            foreach ($users as $user) {
                $user->tokens()->delete();
                $user->forceDelete();
            }

            return $users->count();
        });

        $this->info("Deleted {$deleted} supervisor account(s). The next new supervisor will be SUP-0001.");

        return self::SUCCESS;
    }
}
