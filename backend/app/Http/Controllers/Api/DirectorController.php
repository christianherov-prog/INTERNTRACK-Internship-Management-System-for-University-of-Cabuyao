<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Company;
use App\Models\Evaluation;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

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
            AVG(technical_skills) as avg_technical,
            AVG(communication_skills) as avg_communication,
            AVG(teamwork) as avg_teamwork,
            AVG(initiative) as avg_initiative,
            AVG(work_ethics) as avg_work_ethics,
            AVG(attendance_punctuality) as avg_attendance,
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
            'expires_in_days' => $c->moa_expires_in_days_attribute,
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

        return response()->json(['by_program' => $programs]);
    }

    /**
     * Group internships by resolved academic program (internship → profile.program → course_name).
     */
    private function internsByProgram()
    {
        return Internship::with('student.studentProfile')
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
            $profile?->program,
            $profile?->course_name,
        ] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Unknown';
    }

    /** GET /api/v1/director/internships — roster for status tagging + certificates */
    public function internships(Request $request)
    {
        $rows = Internship::with(['student.studentProfile', 'company', 'coordinator.facultyProfile'])
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
                    'name' => $p ? trim("{$p->first_name} {$p->last_name}") : $i->student?->username,
                    'student_number' => $p?->student_number ?? $i->student?->username,
                    'program' => $p?->program ?? $p?->course_name,
                ],
            ];
        });
        $rows->setCollection($mapped);

        return ApiResponse::list($rows);
    }

    /** GET /api/v1/director/documents — oversight (all stages) */
    public function documents(Request $request)
    {
        $docs = \App\Models\Document::with(['internship.student.studentProfile', 'reviews'])
            ->whereNotIn('status', ['not_submitted'])
            ->orderByDesc('submitted_at')
            ->paginate(40);

        return ApiResponse::list($docs);
    }
}
