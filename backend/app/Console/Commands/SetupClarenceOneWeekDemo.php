<?php

namespace App\Console\Commands;

use App\Services\OneWeekOjtDemoService;
use Illuminate\Console\Command;

class SetupClarenceOneWeekDemo extends Command
{
    protected $signature = 'interntrack:setup-clarence-one-week {student_number=2300592}';

    protected $description = 'Reconcile an existing CCS student (default Clarence 2300592) to the Aug 24–28 2026 one-week OJT demo without creating new users';

    public function handle(OneWeekOjtDemoService $service): int
    {
        $number = (string) $this->argument('student_number');

        try {
            $result = $service->reconcileStudent($number);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $internship = $result['internship'];
        $progress = $result['progress'];
        $profile = $internship->student?->studentProfile;

        $this->info('Clarence one-week demo reconciled (no commit).');
        $this->table(
            ['Field', 'Value'],
            [
                ['Student', trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''))],
                ['Student number', $profile?->student_number],
                ['Program', $profile?->program?->name],
                ['Department', $profile?->department?->name],
                ['Supervisor', $result['supervisor_name']],
                ['Company', $internship->company?->company_name],
                ['Internship ID', $internship->id],
                ['Status', $internship->status],
                ['Period', 'August 24–September 4, 2026 (Week 1–2)'],
                ['Required', $progress['target_hours']],
                ['Completed', $progress['hours_rendered']],
                ['Remaining', $progress['remaining_hours']],
                ['Progress', $progress['progress_pct'].'%'],
                ['Attendance days', count($result['attendance'])],
                ['Journal week 1', ($result['journal']->week_number ?? '?').' / '.$result['journal']->status],
                ['Journal week 2', (($result['week2_journal']->week_number ?? '?').' / '.$result['week2_journal']->status)],
                ['Uploads', count($result['uploads'])],
                ['Evaluation', $result['evaluation_eligibility']['label']],
            ]
        );

        return self::SUCCESS;
    }
}
