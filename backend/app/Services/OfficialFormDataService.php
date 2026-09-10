<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Support\ManilaTime;
use App\Support\OfficialFormAsset;

/**
 * Canonical official-form payload for every authorized role.
 *
 * Controllers authorize the internship, then call this service. FO-30 / FO-31 /
 * evaluations all read the same identity, HTE logo, attendance, and signatures.
 */
class OfficialFormDataService
{
    public function __construct(
        protected PortfolioDataService $portfolio,
    ) {}

    public function bundle(Internship $internship): array
    {
        $viewer = $internship->student;
        $payload = $this->portfolio->payload($internship, $viewer);
        $identity = $payload['identity'] ?? [];
        $logo = $identity['company_logo_path']
            ?? ($payload['portfolio']['company_logo_path'] ?? null);

        return [
            'identity' => $identity,
            'company_logo_path' => $logo,
            'attendance' => $payload['internship']['attendance'] ?? [],
            'journals' => $payload['internship']['journals'] ?? [],
            'evaluations' => $payload['internship']['evaluations'] ?? [],
            'fo30' => $this->fo30From($identity, $payload['internship']['attendance'] ?? [], $logo),
            'timezone' => ManilaTime::TZ,
        ];
    }

    public function fo30(Internship $internship): array
    {
        $identity = $this->portfolio->identity($internship);
        $logo = $identity['company_logo_path'] ?? $this->portfolio->companyLogoPath($internship);
        $logs = AttendanceLog::where('internship_id', $internship->id)
            ->orderBy('date')
            ->get()
            ->map(fn (AttendanceLog $log) => $this->portfolio->serializeAttendance($log, $identity))
            ->values()
            ->all();

        return $this->fo30From($identity, $logs, $logo);
    }

    public function pdfDtr(Internship $internship, ?string $month = null): array
    {
        $fo30 = $this->fo30($internship);
        $logs = collect($fo30['logs'] ?? []);
        if (filled($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $logs = $logs->filter(fn (array $log) => str_starts_with((string) ($log['date'] ?? ''), $month));
        }

        $rows = $this->expandCalendarRows($logs->values()->all());
        $anyValidated = collect($rows)->contains(fn (array $row) => ! empty($row['validated']));

        return [
            'fo30' => $fo30,
            'month_label' => $month ? $month : null,
            'university_logo' => OfficialFormAsset::universityLogoDataUri(),
            'company_logo' => OfficialFormAsset::dataUri($fo30['company_logo_path'] ?? null),
            'student_signature' => OfficialFormAsset::dataUri($fo30['student_signature_path'] ?? null),
            'supervisor_signature' => $anyValidated
                ? OfficialFormAsset::dataUri($fo30['supervisor_signature_path'] ?? null)
                : null,
            'rows' => $rows,
            'timezone' => ManilaTime::TZ,
        ];
    }

    public function pdfJournal(Internship $internship): array
    {
        $bundle = $this->bundle($internship);

        return [
            'identity' => $bundle['identity'],
            'company_logo' => OfficialFormAsset::dataUri($bundle['company_logo_path'] ?? null),
            'university_logo' => OfficialFormAsset::universityLogoDataUri(),
            'student_signature' => OfficialFormAsset::dataUri($bundle['identity']['student_signature_path'] ?? null),
            'journals' => $bundle['journals'],
            'timezone' => ManilaTime::TZ,
        ];
    }

    private function fo30From(array $identity, mixed $logs, ?string $logo): array
    {
        return [
            'student_name' => $identity['student_name'] ?? '',
            'program' => $identity['program'] ?? '',
            'section' => $identity['section'] ?? '',
            'company_name' => $identity['company_name'] ?? '',
            'company_logo_path' => $logo,
            'supervisor_name' => $identity['supervisor_name'] ?? '',
            'supervisor_faculty_number' => $identity['supervisor_faculty_number'] ?? '',
            'faculty_name' => $identity['faculty_name'] ?? '',
            'coordinator_name' => $identity['coordinator_name'] ?? '',
            'student_signature_path' => $identity['student_signature_path'] ?? null,
            'supervisor_signature_path' => $identity['supervisor_signature_path'] ?? null,
            'faculty_signature_path' => $identity['faculty_signature_path'] ?? null,
            'logs' => is_array($logs) ? array_values($logs) : (method_exists($logs, 'values') ? $logs->values()->all() : []),
            'timezone' => ManilaTime::TZ,
        ];
    }

    /**
     * Fill every calendar day from the first log through the last so PDF rows
     * match the live FO-30 preview.
     */
    private function expandCalendarRows(array $logs): array
    {
        $map = [];
        foreach ($logs as $log) {
            $date = substr((string) ($log['date'] ?? ''), 0, 10);
            if ($date !== '') {
                $map[$date] = $log;
            }
        }

        ksort($map);
        $dates = array_keys($map);
        if ($dates === []) {
            return [];
        }

        $cursor = new \DateTimeImmutable($dates[0].' 00:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable(end($dates).' 00:00:00', new \DateTimeZone('UTC'));
        $rows = [];

        while ($cursor <= $end) {
            $ymd = $cursor->format('Y-m-d');
            $log = $map[$ymd] ?? null;
            $validated = (bool) ($log['validated'] ?? false);
            $htePath = $validated ? ($log['hte_signature_path'] ?? null) : null;

            $rows[] = [
                'date' => $ymd,
                'weekday' => (int) $cursor->format('w'),
                'am_time_in' => $log['am_time_in'] ?? null,
                'am_time_out' => $log['am_time_out'] ?? null,
                'pm_time_in' => $log['pm_time_in'] ?? null,
                'pm_time_out' => $log['pm_time_out'] ?? null,
                'hours_rendered' => $log['hours_rendered'] ?? null,
                'validated' => $validated,
                'hte_signature_path' => $htePath,
                'hte_signature' => OfficialFormAsset::dataUri($htePath),
                'status' => $log['status'] ?? null,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $rows;
    }
}
