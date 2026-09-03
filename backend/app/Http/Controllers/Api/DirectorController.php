<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Company;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorController extends Controller
{
    public function dashboard(Request $request) { return $this->analytics($request); }

    public function analytics(Request $request)
    {
        $activeInterns    = Internship::whereIn('status', ['ongoing', 'active'])->count();
        $partnerCompanies = Company::where('is_active', true)->count();
        $completed        = Internship::where('status', 'completed')->count();
        $totalPlacements  = Internship::count();
        $placementRate    = $totalPlacements > 0 ? round(($activeInterns + $completed) / $totalPlacements * 100) : 0;

        // Prefer internship.program, then student profile program/course (seed often leaves internship.program null).
        $byProgram = $this->internsByProgram();

        $moaByStatus = Company::selectRaw('moa_status, COUNT(*) as count')
            ->groupBy('moa_status')
            ->pluck('count', 'moa_status');

        $topCompanies = Company::withCount('internships')
            ->orderByDesc('internships_count')
            ->limit(5)
            ->get(['id', 'company_name', 'industry', 'moa_status']);

        $evalBreakdown = Evaluation::selectRaw('
            AVG(average_score) as avg_overall
        ')->first();

        return response()->json([
            'stats' => [
                'active_interns'    => $activeInterns,
                'partner_companies' => $partnerCompanies,
                'completed'         => $completed,
                'placement_rate'    => $placementRate,
            ],
            'by_program'     => $byProgram,
            'moa_by_status'  => $moaByStatus,
            'top_companies'  => $topCompanies,
            'eval_breakdown' => $evalBreakdown,
            'absorption'     => AbsorptionService::analytics(),
        ]);
    }

    public function companies(Request $request)
    {
        $companies = Company::withCount('internships')->orderBy('company_name')->paginate(20);
        return ApiResponse::list($companies);
    }

    public function storeCompany(Request $request)
    {
        $validated = $request->validate([
            'company_name'   => 'required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'industry'       => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:30',
            'moa_status'     => 'required|in:active,pending,expired,for_renewal,on-process',
            'moa_start_date' => 'nullable|date',
            'moa_expiry_date'=> 'nullable|date|after_or_equal:moa_start_date',
            'slots_available'=> 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
        ]);
        $company = Company::create($validated);
        audit_log($request->user()->id, 'create_company', ['company_name' => $request->company_name]);
        return response()->json(['message' => 'Company added.', 'company' => $company], 201);
    }

    public function updateCompany(Request $request, int $id)
    {
        $validated = $request->validate([
            'company_name'   => 'sometimes|required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'industry'       => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:30',
            'moa_status'     => 'sometimes|required|in:active,pending,expired,for_renewal,on-process',
            'moa_start_date' => 'nullable|date',
            'moa_expiry_date'=> 'nullable|date',
            'slots_available'=> 'nullable|integer|min:0',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string',
        ]);
        $company = Company::findOrFail($id);
        $company->update($validated);
        audit_log($request->user()->id, 'update_company', ['company_id' => $id]);
        return response()->json(['message' => 'Company updated.', 'company' => $company]);
    }

    public function destroyCompany(Request $request, int $id)
    {
        $company = Company::findOrFail($id);
        $company->delete();
        audit_log($request->user()->id, 'delete_company', ['company_id' => $id]);
        return response()->json(['message' => 'Company removed.']);
    }

    public function moaMonitoring(Request $request)
    {
        $companies = Company::orderBy('moa_expiry_date')->get()->map(fn($c) => [
            'id'              => $c->id,
            'company_name'    => $c->company_name,
            'industry'        => $c->industry,
            'moa_status'      => $c->moa_status,
            'moa_start_date'  => $c->moa_start_date?->toDateString(),
            'moa_expiry_date' => $c->moa_expiry_date?->toDateString(),
            'expires_in_days' => $c->moa_expires_in_days,
            'contact_person'  => $c->contact_person,
            'slots_available' => $c->slots_available,
            'is_active'       => $c->is_active,
        ]);
        return ApiResponse::list($companies);
    }

    public function reports(Request $request)
    {
        $programs = $this->internsByProgram()->map(fn ($row) => [
            'program' => $row->program,
            'total' => $row->count,
            'completed' => $row->completed,
            'avg_hours' => $row->avg_hours,
        ])->values();

        return response()->json([
            'by_program' => $programs,
            'absorption' => AbsorptionService::analytics(),
        ]);
    }

    /**
     * Group internships by resolved academic program (internship → profile.program).
     */
    private function internsByProgram()
    {
        return Internship::with('student.studentProfile.program')
            ->get()
            ->groupBy(fn (Internship $i) => $this->resolveProgram($i))
            ->map(function ($rows, $program) {
                $active = $rows->whereIn('status', ['ongoing', 'active'])->count();
                $completed = $rows->where('status', 'completed')->count();

                return (object) [
                    'program' => $program,
                    'count' => $rows->count(),
                    'completed' => $completed,
                    'ongoing' => $active,
                    'avg_hours' => round((float) $rows->avg('total_hours_rendered'), 2),
                ];
            })
            ->sortBy('program')
            ->values();
    }

    private function resolveProgram(Internship $internship): string
    {
        $profile = $internship->student?->studentProfile;
        foreach ([
            $internship->program,
            $profile?->program?->name,
        ] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Unknown';
    }

    /** GET /api/v1/director/internships — roster for status tagging */
    public function internships(Request $request)
    {
        $rows = Internship::with(['student.studentProfile.program', 'company', 'coordinator.facultyProfile'])
            ->latest()
            ->paginate(50);

        $mapped = $rows->getCollection()->map(function ($i) {
            $p = $i->student?->studentProfile;
            return [
                'id' => $i->id,
                'status' => \App\Support\InternshipStatuses::normalize($i->status),
                'status_label' => \App\Support\InternshipStatuses::label($i->status),
                'status_reason' => $i->status_reason,
                'term' => $i->term,
                'company_name' => $i->company?->company_name,
                'student' => [
                    'id' => $i->student_id,
                    'name' => $p ? trim("{$p->last_name}, {$p->first_name}") : $i->student?->username,
                    'student_number' => $p?->student_number ?? $i->student?->username,
                    'program' => $p?->program?->name ?? '—',
                ],
            ];
        });
        $rows->setCollection($mapped);

        return ApiResponse::list($rows);
    }

    /** GET /api/v1/director/records */
    public function records(Request $request)
    {
        $query = User::where('role', 'student')
            ->with([
                'studentProfile.program',
                'activeInternship.company',
                'internshipsAsStudent' => fn ($q) => $q
                    ->withCount(['attendance as validated_days' => fn ($a) => $a->where('status', 'validated')]),
            ]);

        if ($request->filled('program')) {
            $query->whereHas('studentProfile', fn($q) => $q->where('program', $request->program));
        }

        if ($request->boolean('archived')) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        return ApiResponse::list($query->paginate(25));
    }

    public function setStudentArchived(Request $request, int $userId)
    {
        $request->validate(['archived' => 'required|boolean']);

        $student = User::where('role', 'student')->findOrFail($userId);
        if (!User::inDepartment()->where('id', $userId)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }
        $student->is_active = !$request->boolean('archived');
        $student->save();

        audit_log($request->user()->id, $request->boolean('archived') ? 'archive_student' : 'unarchive_student', [
            'student_id' => $userId,
        ]);

        return response()->json([
            'message' => $request->boolean('archived') ? 'Student archived.' : 'Student restored to active.',
            'student' => [
                'id'        => $student->id,
                'username'  => $student->username,
                'is_active' => $student->is_active,
            ],
        ]);
    }

    public function placementOptions(Request $request)
    {
        $companies = Company::where('moa_status', 'active')->get();
        $faculty = User::whereIn('role', ['faculty', 'coordinator'])->with('facultyProfile')->get();
        $supervisors = User::where('role', 'supervisor')->with('supervisorProfile')->get();

        return response()->json([
            'companies' => $companies,
            'faculty' => $faculty,
            'supervisors' => $supervisors,
        ]);
    }

    public function assignPlacement(Request $request, $id)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'faculty_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $internship = Internship::with('student.studentProfile.program')->findOrFail($id);
        if (!Internship::inDepartment()->where('id', $id)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }

        if ($internship->status !== 'pending_placement') {
            return response()->json(['message' => 'Student is already placed or cannot be placed at this time.'], 422);
        }

        $faculty = User::findOrFail((int) $request->faculty_id);
        if ($faculty->role !== 'faculty') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['faculty_id' => ['The selected faculty must have the faculty role.']],
            ], 422);
        }

        $supervisor = User::findOrFail((int) $request->supervisor_id);
        if ($supervisor->role !== 'supervisor') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['supervisor_id' => ['The selected user must be a company supervisor.']],
            ], 422);
        }

        $company = Company::findOrFail((int) $request->company_id);
        if (!$company->isEligibleForPlacement()) {
            return response()->json([
                'message' => $company->ineligibilityReason(),
                'errors' => ['company_id' => [$company->ineligibilityReason()]],
            ], 422);
        }

        $profile = $internship->student?->studentProfile;
        $program = $internship->program
            ?: ($profile?->program?->name);

        $internship->update([
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
            'status' => 'ongoing',
            'program' => $program,
        ]);

        // Auto-approve Form 1 if present
        $form1 = $internship->documents()->where('document_type', 'Form 1')->where('status', '!=', 'approved')->first();
        if ($form1) {
            $form1->update([
                'status' => 'approved',
                'current_stage' => 'completed',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        audit_log($request->user()->id, 'director_assign_placement', [
            'internship_id' => $internship->id,
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
        ]);

        return response()->json([
            'message' => 'Placement successfully assigned and internship started.',
            'internship' => $internship->fresh(['company', 'faculty.facultyProfile', 'supervisor.supervisorProfile'])
        ]);
    }

    /** GET /api/v1/director/reports/placement-trends — company × academic year (last 3 AYs) */
    public function placementTrends(Request $request)
    {
        return response()->json($this->placementTrendsPayload());
    }

    private function placementTrendsPayload(): array
    {
        $years = $this->lastThreeSchoolYears();

        $rows = DB::table('internships')
            ->join('companies', 'companies.id', '=', 'internships.company_id')
            ->whereIn('internships.school_year', $years)
            ->whereNotNull('internships.company_id')
            ->whereNull('internships.deleted_at')
            ->selectRaw('
                companies.id as company_id,
                companies.company_name,
                companies.industry,
                internships.school_year,
                COUNT(*) as placement_count
            ')
            ->groupBy(
                'companies.id',
                'companies.company_name',
                'companies.industry',
                'internships.school_year'
            )
            ->orderBy('internships.school_year')
            ->orderByDesc('placement_count')
            ->orderBy('companies.company_name')
            ->get();

        $byCompany = [];
        foreach ($rows as $row) {
            $id = (int) $row->company_id;
            if (!isset($byCompany[$id])) {
                $byCompany[$id] = [
                    'company_id'   => $id,
                    'company_name' => $row->company_name,
                    'industry'     => $row->industry,
                    'years'        => array_fill_keys($years, 0),
                    'total'        => 0,
                ];
            }
            $byCompany[$id]['years'][$row->school_year] = (int) $row->placement_count;
            $byCompany[$id]['total'] += (int) $row->placement_count;
        }

        $companies = collect($byCompany)
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'school_years' => $years,
            'rows'           => $rows,
            'by_company'     => $companies,
            'generated_at'   => now()->toDateTimeString(),
        ];
    }

    /** e.g. [2025-2026, 2024-2025, 2023-2024] newest first. */
    private function lastThreeSchoolYears(): array
    {
        $latestYear = Internship::inDepartment()
            ->whereNotNull('school_year')
            ->orderByDesc('school_year')
            ->value('school_year');

        if ($latestYear && preg_match('/^(\d{4})-(\d{4})$/', $latestYear, $m)) {
            $start = (int) $m[1];
        } else {
            $start = (int) now()->year - 1;
        }

        $years = [];
        for ($i = 0; $i < 3; $i++) {
            $s = $start - $i;
            $years[] = $s.'-'.($s + 1);
        }

        return $years;
    }

    /** GET /api/v1/director/absorption — completed internships needing / with outcomes */
    public function absorptionList(Request $request)
    {
        return response()->json(['internships' => AbsorptionService::completedList()]);
    }

    /** PATCH /api/v1/director/internships/{id}/absorption — finalize hire outcome */
    public function recordAbsorption(Request $request, int $id)
    {
        $request->validate([
            'absorption_status' => 'required|in:absorbed,not_hired',
            'absorbed_at'       => 'nullable|date',
            'job_title'         => 'nullable|string|max:255',
            'absorption_notes'  => 'nullable|string|max:2000',
        ]);

        $internship = Internship::findOrFail($id);
        if (!Internship::inDepartment()->where('id', $id)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }
        $updated = AbsorptionService::recordOutcome(
            $internship,
            $request->user(),
            'director',
            $request->absorption_status,
            $request->absorbed_at,
            $request->job_title,
            $request->absorption_notes,
        );

        audit_log($request->user()->id, 'record_absorption', [
            'internship_id' => $id,
            'status'        => $request->absorption_status,
            'role'          => 'director',
        ]);

        return response()->json(['message' => 'Absorption outcome saved.', 'internship' => $updated]);
    }

    /** GET /api/v1/director/documents — oversight (all stages) */
    public function documents(Request $request)
    {
        $docs = \App\Models\Document::with(['internship.student.studentProfile.program', 'reviews'])
            ->whereNotIn('status', ['not_submitted'])
            ->orderByDesc('submitted_at')
            ->paginate(40);

        return ApiResponse::list($docs);
    }

    public function hteEvaluations(Request $request)
    {
        return $this->evaluationsOverview($request);
    }

    public function evaluationsOverview(Request $request)
    {
        $formTypes = ['FO-24', 'FO-03', 'FO-22', 'FO-23'];

        $query = Internship::with([
            'student.studentProfile.program',
            'company',
            'supervisor.supervisorProfile',
            'evaluations' => function ($q) use ($formTypes) {
                $q->whereIn('form_type', $formTypes);
            },
        ])
        ->whereHas('evaluations', function ($q) use ($formTypes) {
            $q->whereIn('form_type', $formTypes);
        });

        // Apply filters
        if ($request->filled('department_id') || $request->filled('program_id') || $request->filled('section')) {
            $query->whereHas('student.studentProfile', function ($q) use ($request) {
                if ($request->filled('department_id')) {
                    $q->where('department_id', $request->input('department_id'));
                }
                if ($request->filled('program_id')) {
                    $q->where('program_id', $request->input('program_id'));
                }
                if ($request->filled('section')) {
                    $q->where('section', $request->input('section'));
                }
            });
        }

        // Summary stats across all evaluation types
        $statsQuery = Evaluation::whereIn('form_type', $formTypes);
        if ($request->filled('department_id') || $request->filled('program_id') || $request->filled('section')) {
            $statsQuery->whereHas('internship.student.studentProfile', function ($q) use ($request) {
                if ($request->filled('department_id')) {
                    $q->where('department_id', $request->input('department_id'));
                }
                if ($request->filled('program_id')) {
                    $q->where('program_id', $request->input('program_id'));
                }
                if ($request->filled('section')) {
                    $q->where('section', $request->input('section'));
                }
            });
        }

        $stats = (clone $statsQuery)
            ->selectRaw('COUNT(*) as total, AVG(average_score) as avg_score')
            ->first();

        $ratingCounts = (clone $statsQuery)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $formCounts = (clone $statsQuery)
            ->selectRaw('form_type, COUNT(*) as count')
            ->groupBy('form_type')
            ->pluck('count', 'form_type');

        $internships = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'stats'        => $stats,
            'rating_counts'=> $ratingCounts,
            'form_counts'  => $formCounts,
            'internships'  => $internships,
        ]);
    }

    /**
     * GET /api/v1/director/reports/ched-data
     * Aggregates the data required for the CHED Annual Internship Report.
     * Returns a list of all HTEs with their intern counts and program breakdown.
     */
    public function chedReportData(Request $request)
    {
        $year     = $request->input('school_year');
        $semester = $request->input('semester');
        $program  = $request->input('program');
        $section  = $request->input('section');

        $query = Internship::with(['student.studentProfile.program', 'company'])
            ->whereNotNull('company_id');

        if ($program)  { $query->where('program', $program); }
        if ($section)  { $query->whereHas('student.studentProfile', fn($q) => $q->where('section', $section)); }
        if ($year)     { $query->where('school_year', $year); }
        if ($semester) { $query->where('semester', $semester); }

        $internships = $query->get();

        // Group by company
        $byCompany = $internships->groupBy('company_id')->map(function ($group) {
            $company = $group->first()->company;
            $byProgram = $group->groupBy(fn($i) => $i->program ?? $i->student?->studentProfile?->program?->name ?? 'Unknown')
                ->map->count()
                ->sortKeys();

            return [
                'company_id'      => $company?->id,
                'company_name'    => $company?->company_name ?? '—',
                'address'         => $company?->address ?? '—',
                'industry'        => $company?->industry ?? '—',
                'moa_status'      => $company?->moa_status ?? '—',
                'total_interns'   => $group->count(),
                'completed'       => $group->where('status', 'completed')->count(),
                'ongoing'         => $group->whereIn('status', ['ongoing', 'active'])->count(),
                'by_program'      => $byProgram,
            ];
        })->values()->sortBy('company_name')->values();

        // Summary totals
        $totals = [
            'total_companies' => $byCompany->count(),
            'total_interns'   => $internships->count(),
            'completed'       => $internships->where('status', 'completed')->count(),
            'ongoing'         => $internships->whereIn('status', ['ongoing', 'active'])->count(),
        ];

        $schoolYears = Internship::distinct()->pluck('school_year')->sort()->values();
        $semesters = Internship::distinct()->pluck('semester')->sort()->values();

        return response()->json([
            'internships'    => $query->paginate(20),
            'school_years' => $schoolYears,
            'semesters'      => $semesters,
            'rows'           => $byCompany,
            'totals'         => $totals,
            'filters'        => ['school_year' => $year, 'semester' => $semester],
            'generated_at'   => now()->toDateTimeString(),
        ]);
    }
}
