<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InternshipAnalyticsService
{
    private const FORM_TYPES = ['FO-22', 'FO-23', 'FO-24', 'FO-03'];

    public function build(Request $request): array
    {
        $user = $request->user();
        $crossDepartment = DepartmentScope::isUniversityWide($user);
        $forcedDeptId = $crossDepartment ? ($request->filled('department_id') ? (int) $request->input('department_id') : null) : DepartmentScope::departmentIdFor($user);

        $schoolYear = $request->filled('school_year') ? (string) $request->input('school_year') : null;
        $semester = $request->filled('semester') ? (string) $request->input('semester') : null;

        $scoped = $this->internshipQuery($user, $forcedDeptId);
        $all = (clone $scoped)
            ->with([
                'student.studentProfile.program.department',
                'company',
                'evaluations' => fn ($q) => $q->whereIn('form_type', self::FORM_TYPES),
            ])
            ->withCount([
                'documents as approved_docs_count' => fn ($q) => $q->where('status', 'approved'),
                'journals as approved_journals_count' => fn ($q) => $q->academic()->where('status', 'approved'),
            ])
            ->get();

        $availableTerms = $all
            ->map(fn (Internship $i) => $this->termKey($i->school_year, $i->semester))
            ->unique()
            ->filter(fn ($t) => $t['school_year'] !== '')
            ->sortByDesc(fn ($t) => $t['school_year'].'|'.$t['semester'])
            ->values();

        $current = $all->filter(function (Internship $i) use ($schoolYear, $semester) {
            if ($schoolYear !== null && (string) $i->school_year !== $schoolYear) {
                return false;
            }
            if ($semester !== null && (string) $i->semester !== $semester) {
                return false;
            }

            return true;
        })->values();

        $eligible = $current->filter(fn (Internship $i) => ! in_array($i->status, ['cancelled', 'withdrawn', 'terminated'], true))->values();
        $studentIds = $eligible->pluck('student_id')->unique()->filter()->values();

        $applications = $studentIds->isEmpty()
            ? collect()
            : InternshipApplication::with('company')
                ->whereIn('student_id', $studentIds)
                ->get();

        $evaluations = $this->evaluationsBlock($eligible);
        $activity = $this->activityBlock($eligible);
        $companies = $this->companiesBlock($eligible, $applications);
        $compliance = $this->complianceBlock($eligible);

        $payload = [
            'scope' => [
                'role' => $user->role,
                'cross_department' => $crossDepartment && $forcedDeptId === null,
                'department_id' => $forcedDeptId,
            ],
            'filters' => [
                'school_year' => $schoolYear,
                'semester' => $semester,
                'department_id' => $forcedDeptId,
            ],
            'available_terms' => $availableTerms,
            'departments' => $crossDepartment
                ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                : collect(),
            'kpis' => [
                'internships' => $eligible->count(),
                'active' => $eligible->whereIn('status', ['ongoing', 'active'])->count(),
                'completed' => $eligible->where('status', 'completed')->count(),
                'avg_hours' => round((float) $eligible->avg('total_hours_rendered'), 1),
                'applications' => $applications->count(),
                'evaluations_submitted' => $evaluations['submitted'],
                'eval_completion_pct' => $evaluations['completion_pct'],
                'doc_compliance_pct' => $compliance['document_pct'],
            ],
            'evaluations' => $evaluations,
            'student_activity' => $activity,
            'companies' => $companies,
            'compliance' => $compliance,
            'period_comparison' => $this->periodComparison($all),
        ];

        if ($crossDepartment && $forcedDeptId === null) {
            $payload['department_leaderboard'] = $this->departmentLeaderboard($eligible, $applications);
            $payload['evaluations']['by_department'] = $this->evaluationsByDepartment($eligible);
            $payload['companies']['applications_by_department'] = $this->applicationsByCompanyDepartment($eligible, $applications);
        }

        return $payload;
    }

    private function internshipQuery(User $user, ?int $departmentId)
    {
        $query = Internship::query();
        DepartmentScope::constrainInternships($query, $user);

        if ($departmentId && DepartmentScope::isUniversityWide($user)) {
            $query->whereHas('student.studentProfile', function ($q) use ($departmentId) {
                DepartmentScope::constrainStudentProfiles($q, $departmentId);
            });
        }

        return $query;
    }

    private function termKey(mixed $year, mixed $semester): array
    {
        return [
            'school_year' => trim((string) $year),
            'semester' => trim((string) $semester),
        ];
    }

    private function departmentMeta(Internship $internship): array
    {
        $profile = $internship->student?->studentProfile;
        $dept = $profile?->program?->department;

        return [
            'id' => $dept?->id ?? $profile?->department_id,
            'code' => $dept?->code ?: '—',
            'name' => $dept?->name ?: 'Unknown',
        ];
    }

    private function studentName(Internship $internship): string
    {
        $p = $internship->student?->studentProfile;
        if ($p) {
            return trim(($p->last_name ?: '').', '.($p->first_name ?: ''), ' ,');
        }

        return $internship->student?->student_number ?: $internship->student?->username ?: '—';
    }

    private function evaluationsBlock(Collection $internships): array
    {
        $byForm = collect(self::FORM_TYPES)->mapWithKeys(fn ($type) => [$type => ['form_type' => $type, 'count' => 0, 'avg_score' => null]]);
        $total = $internships->count();
        $withAny = 0;
        $submitted = 0;

        foreach ($internships as $internship) {
            $forms = $internship->evaluations->whereIn('form_type', self::FORM_TYPES);
            if ($forms->isNotEmpty()) {
                $withAny++;
            }
            foreach ($forms as $eval) {
                $submitted++;
                $row = $byForm[$eval->form_type];
                $row['count']++;
                $row['sum'] = ($row['sum'] ?? 0) + (float) $eval->average_score;
                $byForm[$eval->form_type] = $row;
            }
        }

        $byForm = $byForm->map(function (array $row) {
            $row['avg_score'] = ($row['count'] ?? 0) > 0 ? round(($row['sum'] ?? 0) / $row['count'], 2) : null;
            unset($row['sum']);

            return $row;
        })->values();

        $completion = [];
        foreach (self::FORM_TYPES as $type) {
            $have = $internships->filter(fn (Internship $i) => $i->evaluations->contains('form_type', $type))->count();
            $completion[$type] = [
                'count' => $have,
                'pct' => $total > 0 ? round($have / $total * 100, 1) : 0,
            ];
        }

        return [
            'submitted' => $submitted,
            'internships_with_any' => $withAny,
            'completion_pct' => $total > 0 ? round($withAny / $total * 100, 1) : 0,
            'by_form' => $byForm,
            'completion' => $completion,
        ];
    }

    private function evaluationsByDepartment(Collection $internships): array
    {
        return $internships
            ->groupBy(fn (Internship $i) => $this->departmentMeta($i)['code'])
            ->map(function (Collection $rows, $code) {
                $meta = $this->departmentMeta($rows->first());
                $evals = $rows->flatMap->evaluations->whereIn('form_type', self::FORM_TYPES);

                return [
                    'department_id' => $meta['id'],
                    'department_code' => $code,
                    'department_name' => $meta['name'],
                    'internships' => $rows->count(),
                    'submitted' => $evals->count(),
                    'avg_score' => $evals->isEmpty() ? null : round((float) $evals->avg('average_score'), 2),
                    'completion_pct' => $rows->count() > 0
                        ? round($rows->filter(fn (Internship $i) => $i->evaluations->whereIn('form_type', self::FORM_TYPES)->isNotEmpty())->count() / $rows->count() * 100, 1)
                        : 0,
                ];
            })
            ->sortBy('department_name')
            ->values()
            ->all();
    }

    private function activityBlock(Collection $internships): array
    {
        $ranked = $internships
            ->sortByDesc(fn (Internship $i) => (float) $i->total_hours_rendered)
            ->take(8)
            ->map(function (Internship $i) {
                $dept = $this->departmentMeta($i);

                return [
                    'internship_id' => $i->id,
                    'student_name' => $this->studentName($i),
                    'hours' => round((float) $i->total_hours_rendered, 1),
                    'company' => $i->company?->company_name ?: '—',
                    'department_code' => $dept['code'],
                    'department_name' => $dept['name'],
                ];
            })
            ->values();

        $perCompany = $internships
            ->filter(fn (Internship $i) => $i->company_id)
            ->groupBy('company_id')
            ->map(function (Collection $rows) {
                $top = $rows->sortByDesc(fn (Internship $i) => (float) $i->total_hours_rendered)->first();
                $dept = $this->departmentMeta($top);

                return [
                    'company_id' => $top->company_id,
                    'company' => $top->company?->company_name ?: '—',
                    'student_name' => $this->studentName($top),
                    'hours' => round((float) $top->total_hours_rendered, 1),
                    'department_code' => $dept['code'],
                    'department_name' => $dept['name'],
                ];
            })
            ->sortByDesc('hours')
            ->take(10)
            ->values();

        return [
            'top_students' => $ranked->all(),
            'most_active_per_company' => $perCompany->all(),
        ];
    }

    private function companiesBlock(Collection $internships, Collection $applications): array
    {
        $placed = $internships
            ->filter(fn (Internship $i) => $i->company_id)
            ->groupBy('company_id')
            ->map(function (Collection $rows) {
                return [
                    'company_id' => $rows->first()->company_id,
                    'company' => $rows->first()->company?->company_name ?: '—',
                    'interns' => $rows->count(),
                ];
            })
            ->sortByDesc('interns')
            ->take(10)
            ->values();

        $applied = $applications
            ->groupBy('company_id')
            ->map(function (Collection $rows) {
                $company = $rows->first()->company;

                return [
                    'company_id' => $rows->first()->company_id,
                    'company' => $company?->company_name ?: '—',
                    'applications' => $rows->count(),
                ];
            })
            ->sortByDesc('applications')
            ->take(10)
            ->values();

        return [
            'most_placed' => $placed->all(),
            'most_applied' => $applied->all(),
        ];
    }

    private function applicationsByCompanyDepartment(Collection $internships, Collection $applications): array
    {
        $deptByStudent = $internships->mapWithKeys(function (Internship $i) {
            $dept = $this->departmentMeta($i);

            return [$i->student_id => $dept];
        });

        return $applications
            ->groupBy('company_id')
            ->map(function (Collection $rows) use ($deptByStudent) {
                $company = $rows->first()->company;
                $byDept = $rows->groupBy(fn ($app) => $deptByStudent[$app->student_id]['code'] ?? '—')
                    ->map->count();

                return [
                    'company_id' => $rows->first()->company_id,
                    'company' => $company?->company_name ?: '—',
                    'applications' => $rows->count(),
                    'by_department' => $byDept,
                ];
            })
            ->sortByDesc('applications')
            ->take(12)
            ->values()
            ->all();
    }

    private function complianceBlock(Collection $internships): array
    {
        $total = $internships->count();
        $withDocs = $internships->filter(fn (Internship $i) => (int) $i->approved_docs_count > 0)->count();
        $withJournals = $internships->filter(fn (Internship $i) => (int) $i->approved_journals_count > 0)->count();
        $periodApproved = $internships->filter(fn (Internship $i) => ($i->evaluation_period_status ?: 'pending') === 'approved')->count();

        return [
            'document_pct' => $total > 0 ? round($withDocs / $total * 100, 1) : 0,
            'journal_pct' => $total > 0 ? round($withJournals / $total * 100, 1) : 0,
            'eval_period_approved_pct' => $total > 0 ? round($periodApproved / $total * 100, 1) : 0,
            'with_approved_documents' => $withDocs,
            'with_approved_journals' => $withJournals,
            'eval_period_approved' => $periodApproved,
            'internships' => $total,
        ];
    }

    private function departmentLeaderboard(Collection $internships, Collection $applications): array
    {
        $appsByStudent = $applications->groupBy('student_id');

        return $internships
            ->groupBy(fn (Internship $i) => $this->departmentMeta($i)['code'])
            ->map(function (Collection $rows, $code) use ($appsByStudent) {
                $meta = $this->departmentMeta($rows->first());
                $evals = $rows->flatMap->evaluations->whereIn('form_type', self::FORM_TYPES);
                $appCount = $rows->sum(fn (Internship $i) => $appsByStudent->get($i->student_id, collect())->count());
                $withEval = $rows->filter(fn (Internship $i) => $i->evaluations->whereIn('form_type', self::FORM_TYPES)->isNotEmpty())->count();
                $withDocs = $rows->filter(fn (Internship $i) => (int) $i->approved_docs_count > 0)->count();

                return [
                    'department_id' => $meta['id'],
                    'department_code' => $code,
                    'department_name' => $meta['name'],
                    'internships' => $rows->count(),
                    'avg_hours' => round((float) $rows->avg('total_hours_rendered'), 1),
                    'applications' => $appCount,
                    'eval_completion_pct' => $rows->count() > 0 ? round($withEval / $rows->count() * 100, 1) : 0,
                    'doc_compliance_pct' => $rows->count() > 0 ? round($withDocs / $rows->count() * 100, 1) : 0,
                    'eval_avg' => $evals->isEmpty() ? null : round((float) $evals->avg('average_score'), 2),
                ];
            })
            ->sortByDesc('internships')
            ->values()
            ->all();
    }

    private function periodComparison(Collection $all): array
    {
        return $all
            ->groupBy(fn (Internship $i) => $i->school_year.'|'.$i->semester)
            ->map(function (Collection $rows) {
                $term = $this->termKey($rows->first()->school_year, $rows->first()->semester);
                $eligible = $rows->filter(fn (Internship $i) => ! in_array($i->status, ['cancelled', 'withdrawn', 'terminated'], true));
                $evals = $eligible->flatMap->evaluations->whereIn('form_type', self::FORM_TYPES);

                return [
                    'school_year' => $term['school_year'],
                    'semester' => $term['semester'],
                    'internships' => $eligible->count(),
                    'avg_hours' => round((float) $eligible->avg('total_hours_rendered'), 1),
                    'evaluations_submitted' => $evals->count(),
                    'eval_completion_pct' => $eligible->count() > 0
                        ? round($eligible->filter(fn (Internship $i) => $i->evaluations->whereIn('form_type', self::FORM_TYPES)->isNotEmpty())->count() / $eligible->count() * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc(fn ($row) => $row['school_year'].'|'.$row['semester'])
            ->take(6)
            ->values()
            ->all();
    }
}
