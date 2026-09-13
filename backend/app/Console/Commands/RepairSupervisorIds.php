<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SupervisorIds;
use Illuminate\Console\Command;

class RepairSupervisorIds extends Command
{
    protected $signature = 'interntrack:repair-supervisor-ids';

    protected $description = 'Assign unique SUP-#### IDs to supervisors that are missing faculty_number. Never deletes accounts.';

    public function handle(): int
    {
        $duplicates = User::query()
            ->select('faculty_number')
            ->whereNotNull('faculty_number')
            ->groupBy('faculty_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('faculty_number');

        if ($duplicates->isNotEmpty()) {
            $this->warn('Duplicate faculty_number values exist and were left untouched:');
            foreach ($duplicates as $code) {
                $owners = User::withTrashed()->where('faculty_number', $code)->get(['id', 'email', 'role', 'deleted_at']);
                $this->line('  '.$code);
                foreach ($owners as $owner) {
                    $this->line('    user #'.$owner->id.' '.$owner->email.' ('.$owner->role.')');
                }
            }
        }

        $missing = User::withTrashed()->where('role', 'supervisor')->whereNull('faculty_number')->count();
        $this->info("Supervisors missing faculty_number: {$missing}");

        $assigned = SupervisorIds::backfillMissing();
        foreach ($assigned as $row) {
            $this->line("  assigned {$row['faculty_number']} to user #{$row['user_id']} {$row['email']}");
        }

        $this->info('Assigned '.count($assigned).' supervisor ID(s). No accounts were deleted.');

        return self::SUCCESS;
    }
}
