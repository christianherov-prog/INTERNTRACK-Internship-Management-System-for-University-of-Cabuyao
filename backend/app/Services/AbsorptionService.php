<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AbsorptionService
{
    public const STATUSES = ['pending', 'absorbed', 'not_hired'];

    /** Active PALD Director user IDs. */
    public static function directorUserIds(): Collection
    {
        return User::query()
            ->where('role', 'director')
            ->where('is_active', true)
            ->pluck('id');
    }

    /** Called when internship is marked completed. */
    public static function initializePending(Internship $internship): void
    {
        if ($internship->status !== 'completed') {
            return;
        }

        if ($internship->absorption_status === null) {
            $internship->update([
                'absorption_status' => 'pending',
            ]);
        }

        foreach (self::directorUserIds() as $directorId) {
            Notification::notify(
                (int) $directorId,
                'absorption_pending',
                'Confirm Intern Absorption',
                'An internship was completed. Please confirm whether the intern was hired (absorbed) or not.',
                '/director/absorption'
            );
        }
    }

    /**
     * PALD Director records final absorption outcome.
     * @param  'director'  $role
     */
    public static function recordOutcome(
        Internship $internship,
        User $actor,
        string $role,
        string $status,
        ?string $absorbedAt = null,
        ?string $jobTitle = null,
        ?string $notes = null,
    ): Internship {
        if ($role !== 'director') {
            throw ValidationException::withMessages([
                'role' => 'Only the PALD Director may finalize absorption.',
            ]);
        }

        if ($internship->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Absorption can only be recorded after the internship is completed.',
            ]);
        }

        if (!in_array($status, ['absorbed', 'not_hired'], true)) {
            throw ValidationException::withMessages([
                'absorption_status' => 'Outcome must be absorbed or not_hired.',
            ]);
        }

        if ($status === 'absorbed' && !$absorbedAt) {
            $absorbedAt = now()->toDateString();
        }

        $internship->update([
            'absorption_status'           => $status,
            'absorbed_at'                 => $status === 'absorbed' ? $absorbedAt : null,
            'job_title'                   => $status === 'absorbed' ? $jobTitle : null,
            'absorption_notes'            => $notes,
            'absorption_recorded_by'      => $actor->id,
            'absorption_recorded_at'      => now(),
            'absorption_recorded_by_role' => 'director',
        ]);

        Notification::notify(
            $internship->student_id,
            'absorption_recorded',
            'Internship Hire Outcome Updated',
            $status === 'absorbed'
                ? 'The PALD Director recorded that you were absorbed/hired.'
                : 'The PALD Director recorded that you were not hired after the internship.',
            '/student/records'
        );

        return $internship->fresh(['company', 'student.studentProfile', 'supervisor.supervisorProfile', 'coordinator.facultyProfile']);
    }

    /** Student optional declaration — does NOT finalize; stays pending until Director confirms. */
    public static function studentDeclare(Internship $internship, ?string $notes = null): Internship
    {
        if ($internship->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'You can only declare hire status after your internship is completed.',
            ]);
        }

        if (in_array($internship->absorption_status, ['absorbed', 'not_hired'], true)) {
            throw ValidationException::withMessages([
                'absorption_status' => 'A final outcome was already recorded. Contact the PALD Director if this is wrong.',
            ]);
        }

        $internship->update([
            'absorption_status'          => 'pending',
            'student_declared_hired'     => true,
            'student_declared_at'        => now(),
            'student_declaration_notes'  => $notes,
        ]);

        foreach (self::directorUserIds() as $directorId) {
            Notification::notify(
                (int) $directorId,
                'student_declared_hired',
                'Student Declared They Were Hired',
                'A student reported they were hired by the HTE. Please confirm absorption (Yes/No).',
                '/director/absorption'
            );
        }

        // FYI for supervisor / coordinator (view-only pages)
        foreach (array_filter([$internship->supervisor_id, $internship->coordinator_id]) as $userId) {
            $link = $internship->supervisor_id === $userId
                ? '/supervisor/absorption'
                : '/coordinator/absorption';
            Notification::notify(
                (int) $userId,
                'student_declared_hired',
                'Student Declared They Were Hired',
                'A student reported they were hired. Final confirmation is recorded by the PALD Director.',
                $link
            );
        }

        return $internship->fresh(['company']);
    }

    /** List payload for absorption UIs. Optionally scope to a coordinator. */
    public static function completedList(?int $coordinatorId = null)
    {
        $query = Internship::where('status', 'completed')
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'coordinator.facultyProfile'])
            ->orderByDesc('end_date');

        if ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        }

        return $query->get();
    }

    /** Analytics for Director dashboard (or coordinator-scoped when $coordinatorId set). */
    public static function analytics(?int $coordinatorId = null): array
    {
        $query = Internship::where('status', 'completed')->with('company');
        if ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        }
        $completed = $query->get();

        $absorbed = $completed->where('absorption_status', 'absorbed')->count();
        $notHired = $completed->where('absorption_status', 'not_hired')->count();
        $pending  = $completed->where('absorption_status', 'pending')->count()
            + $completed->whereNull('absorption_status')->count();
        $decided  = $absorbed + $notHired;
        $rate     = $decided > 0 ? round(($absorbed / $decided) * 100, 1) : null;

        $byCompany = $completed
            ->groupBy(fn ($i) => $i->company?->company_name ?: 'Unknown Company')
            ->map(function ($rows, $name) {
                $a = $rows->where('absorption_status', 'absorbed')->count();
                $n = $rows->where('absorption_status', 'not_hired')->count();
                $p = $rows->filter(fn ($i) => !in_array($i->absorption_status, ['absorbed', 'not_hired'], true))->count();
                $d = $a + $n;
                return [
                    'company'   => $name,
                    'completed' => $rows->count(),
                    'absorbed'  => $a,
                    'not_hired' => $n,
                    'pending'   => $p,
                    'rate'      => $d > 0 ? round(($a / $d) * 100, 1) : null,
                ];
            })
            ->values()
            ->sortByDesc('absorbed')
            ->values();

        return [
            'completed_internships' => $completed->count(),
            'absorbed'              => $absorbed,
            'not_hired'             => $notHired,
            'pending'               => $pending,
            'absorption_rate'       => $rate,
            'student_declarations'  => $completed->where('student_declared_hired', true)->count(),
            'by_company'            => $byCompany,
        ];
    }
}
