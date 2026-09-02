<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OjtRequirementTemplate;
use App\Models\User;
use App\Models\Internship;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Support\ApiResponse;
use App\Models\AuditLog;

class RequirementTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Fetch requirements created by this user
        $requirements = OjtRequirementTemplate::with(['targets', 'attachments'])
            ->where('created_by', $user->id)
            ->orderBy('sort_order')
            ->get();

        // 2. Fetch scoped students
        $studentsQuery = User::where('role', 'student')->with('studentProfile.program');
        $this->applyStudentTargetScope($studentsQuery, $user);
        
        $students = $studentsQuery->get();
        $internships = Internship::whereIn('student_id', $students->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
        
        $handledStudents = [];
        foreach ($students as $student) {
            $profile = $student->studentProfile;
            $internship = $internships->get($student->id);

            $handledStudents[] = [
                'id' => $student->id,
                'name' => $profile ? trim(($profile->last_name ?? '') . ', ' . ($profile->first_name ?? '')) : $student->username,
                'id_number' => $profile?->id_number,
                'initials' => $profile ? strtoupper(substr($profile->first_name ?? '', 0, 1) . substr($profile->last_name ?? '', 0, 1)) : strtoupper(substr($student->username, 0, 2)),
                'section_name' => $profile?->section,
                'program_name' => $profile?->program?->name,
                'internship_id' => $internship ? $internship->id : null,
            ];
        }

        // 3. For each requirement, determine assigned students
        $requirements->transform(function ($req) use ($handledStudents) {
            $assignedStudents = collect($handledStudents)->filter(function ($hs) use ($req) {
                foreach ($req->targets as $t) {
                    if ($t->target_type === 'student' && (string)$t->target_id === (string)$hs['id']) return true;
                    if ($t->target_type === 'section' && $t->target_id === $hs['section_name']) return true;
                    if ($t->target_type === 'program' && $t->target_id === $hs['program_name']) return true;
                }
                return false;
            })->values();

            $validStudentIds = $assignedStudents->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();

            $submissions = collect();
            if ($validStudentIds->isNotEmpty()) {
                $submissions = Document::with(['internship', 'reviewer.facultyProfile', 'attachments'])
                    ->where('document_type', $req->name)
                    ->whereHas('internship', fn ($q) => $q->whereIn('student_id', $validStudentIds))
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->get()
                    ->unique(fn ($doc) => (int) $doc->internship?->student_id)
                    ->keyBy(fn ($doc) => (int) $doc->internship?->student_id);
            }

            $mappedSubmissions = $assignedStudents->map(function ($hs) use ($submissions, $req) {
                $doc = $submissions->get((int) $hs['id']);
                
                $status = $doc?->status ?? 'not_submitted';
                if ($req->deadline && now()->greaterThan($req->deadline)) {
                    if (!$doc || $status === 'rejected' || $status === 'not_submitted') {
                        $status = 'no_submission';
                    }
                }

                $reviewerName = null;
                if ($doc && $doc->reviewer) {
                    $reviewerName = $doc->reviewer->facultyProfile ? trim($doc->reviewer->facultyProfile->last_name . ', ' . $doc->reviewer->facultyProfile->first_name) : $doc->reviewer->username;
                }
                
                return [
                    'student_id' => $hs['id'],
                    'student_name' => $hs['name'] ?: 'Unknown Student',
                    'student_id_number' => $hs['id_number'],
                    'student_initials' => $hs['initials'],
                    'section' => $hs['section_name'],
                    'status' => $status,
                    'submitted_at' => $doc?->submitted_at ? clone $doc->submitted_at : null,
                    'file_url' => null, // Kept for backwards compatibility if needed, but not used now
                    'file_path' => null,
                    'file_name' => null,
                    'attachments' => $doc ? $doc->attachments->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'file_name' => $a->file_name,
                            'file_path' => $a->file_path,
                            'file_url' => url('/api/v1/files/download?path=' . urlencode($a->file_path)),
                            'file_size' => $a->file_size,
                            'mime_type' => $a->mime_type,
                        ];
                    })->toArray() : [],
                    'drive_link' => $doc?->drive_link,
                    'document_id' => $doc?->id,
                    'remarks' => $doc?->remarks,
                    'reviewed_by_name' => $reviewerName,
                    'reviewed_by_role' => $doc?->reviewer?->role ? ucfirst($doc->reviewer->role) : null,
                    'reviewed_at' => $doc?->reviewed_at,
                ];
            });

            foreach ($req->targets as $target) {
                $target->setAttribute('label', $this->labelForTarget($target, $handledStudents));
            }

            $req->submissions = $mappedSubmissions;
            $req->total_assigned = $assignedStudents->count();
            $req->completed_count = $mappedSubmissions->whereIn('status', ['approved', 'completed'])->count();

            return $req;
        });

        return ApiResponse::list($requirements);
    }

    /**
     * Get available target options. Enforces RBAC.
     */
    public function options(Request $request)
    {
        $user = $request->user();

        $studentsQuery = User::where('role', 'student')->with('studentProfile.program');
        $this->applyStudentTargetScope($studentsQuery, $user);

        $users = $studentsQuery->get();

        $students = [];
        $sections = [];
        $seenSections = [];

        foreach ($users as $u) {
            $profile = $u->studentProfile;
            if (!$profile) {
                continue;
            }

            $middleInitial = $profile->middle_name ? substr($profile->middle_name, 0, 1) . '.' : '';
            $name = trim(($profile->last_name ?? '') . ', ' . ($profile->first_name ?? '') . ' ' . $middleInitial);
            $students[] = [
                'id' => $u->id,
                'name' => $name ?: $u->username,
                'section' => $profile->section,
            ];

            if ($profile->section && !in_array($profile->section, $seenSections, true)) {
                $sections[] = [
                    'id' => $profile->section,
                    'name' => $profile->section,
                ];
                $seenSections[] = $profile->section;
            }
        }

        foreach ($this->assignedSectionsFor($user) as $sectionName) {
            if ($sectionName && !in_array($sectionName, $seenSections, true)) {
                $sections[] = [
                    'id' => $sectionName,
                    'name' => $sectionName,
                ];
                $seenSections[] = $sectionName;
            }
        }

        $programsQuery = \App\Models\Program::where('is_active', true)->orderBy('name');
        if ($user->hasRole('faculty') || $user->hasRole('coordinator')) {
            $deptId = $user->facultyProfile?->department_id;
            if ($deptId) {
                $programsQuery->where('department_id', $deptId);
            }
        }
        $programs = $programsQuery->get()->map(fn ($p) => [
            'id' => $p->name,
            'name' => $p->name,
        ])->values()->all();

        usort($students, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json([
            'students' => $students,
            'sections' => $sections,
            'programs' => $programs,
        ]);
    }

    /**
     * Store a new requirement.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'targets' => 'required|array',
            'template_files.*' => 'nullable|file|mimes:doc,docx,pdf,jpg,jpeg,png|max:10240',
            'drive_link' => 'nullable|url',
        ]);

        return DB::transaction(function () use ($request) {
            $requirement = OjtRequirementTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'deadline' => $request->deadline,
                'drive_link' => $request->drive_link,
                'created_by' => $request->user()->id,
                'sort_order' => OjtRequirementTemplate::max('sort_order') + 1,
            ]);

            if ($request->hasFile('template_files')) {
                foreach ($request->file('template_files') as $file) {
                    $requirement->attachments()->create([
                        'file_path' => $file->store("requirement_templates", 'local'),
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            $this->syncTargets($requirement, $request->input('targets'));

            return response()->json([
                'message' => 'Requirement template created successfully.',
                'requirement' => $requirement->load('targets', 'attachments')
            ], 201);
        });
    }

    /**
     * Update an existing requirement.
     */
    public function update(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::where('created_by', $request->user()->id)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'targets' => 'required|array',
            'template_files.*' => 'nullable|file|mimes:doc,docx,pdf,jpg,jpeg,png|max:10240',
            'drive_link' => 'nullable|url',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer|exists:requirement_template_attachments,id'
        ]);

        return DB::transaction(function () use ($request, $requirement) {
            if ($request->has('remove_attachments')) {
                $attachmentsToRemove = $requirement->attachments()->whereIn('id', $request->remove_attachments)->get();
                foreach ($attachmentsToRemove as $attachment) {
                    Storage::disk('local')->delete($attachment->file_path);
                    $attachment->delete();
                }
            }

            if ($request->hasFile('template_files')) {
                foreach ($request->file('template_files') as $file) {
                    $requirement->attachments()->create([
                        'file_path' => $file->store("requirement_templates", 'local'),
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            $oldDeadline = $requirement->deadline?->toIso8601String();

            $requirement->update([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'deadline' => $request->deadline,
                'drive_link' => $request->has('drive_link') ? $request->drive_link : $requirement->drive_link,
            ]);

            if ($request->has('deadline') && $oldDeadline != $request->deadline) {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'extend_deadline',
                    'model_type' => OjtRequirementTemplate::class,
                    'model_id' => $requirement->id,
                    'old_values' => ['deadline' => $oldDeadline],
                    'new_values' => ['deadline' => $request->deadline],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);
            }

            $this->syncTargets($requirement, $request->input('targets'));

            return response()->json([
                'message' => 'Requirement template updated successfully.',
                'requirement' => $requirement->load('targets', 'attachments')
            ]);
        });
    }

    /**
     * Delete a requirement.
     */
    public function destroy(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::where('created_by', $request->user()->id)
            ->with('attachments')
            ->findOrFail($id);

        return DB::transaction(function () use ($requirement) {
            if ($requirement->template_file_path) {
                Storage::disk('local')->delete($requirement->template_file_path);
            }
            foreach ($requirement->attachments as $attachment) {
                if ($attachment->file_path) {
                    Storage::disk('local')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $requirement->targets()->delete();
            $requirement->delete();

            return response()->json(['message' => 'Requirement template deleted successfully.']);
        });
    }

    /**
     * Download the template file.
     */
    public function downloadTemplate(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::with('attachments')->findOrFail($id);
        $attachment = $requirement->attachments->first();
        $path = $attachment?->file_path ?: $requirement->template_file_path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $fileName = $attachment?->file_name
            ?: ($requirement->name.'_template.'.pathinfo($path, PATHINFO_EXTENSION));

        if ($request->query('preview')) {
            $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
            return Storage::disk('local')->response(
                $path,
                $fileName,
                [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                ]
            );
        }

        return Storage::disk('local')->download($path, $fileName);
    }

    /**
     * Coordinator sees the college; faculty sees assigned sections, or the college if none are mapped.
     * Coordinators also match hasRole('faculty'), so coordinator must be checked first.
     */
    private function applyStudentTargetScope($query, User $user): void
    {
        $user->loadMissing('facultyProfile');
        $deptId = $user->facultyProfile?->department_id;

        if ($user->isCoordinator()) {
            if ($deptId) {
                $query->whereHas('studentProfile', fn ($q) => $q->where('department_id', $deptId));
            }

            return;
        }

        if (!$user->isFaculty()) {
            return;
        }

        $sections = $this->assignedSectionsFor($user);

        $query->whereHas('studentProfile', function ($q) use ($sections, $deptId) {
            $q->where(function ($inner) use ($sections, $deptId) {
                if ($sections->isNotEmpty()) {
                    $inner->whereIn('section', $sections);
                }
                if ($deptId) {
                    $inner->orWhere('department_id', $deptId);
                }
                if ($sections->isEmpty() && !$deptId) {
                    $inner->whereRaw('0 = 1');
                }
            });
        });
    }

    private function assignedSectionsFor(User $user)
    {
        $query = \App\Models\FacultySectionAssignment::query()->where('is_active', true);

        if ($user->isCoordinator()) {
            $deptId = $user->facultyProfile?->department_id;
            if (!$deptId) {
                return collect();
            }

            return $query
                ->whereHas('faculty.facultyProfile', fn ($q) => $q->where('department_id', $deptId))
                ->pluck('section')
                ->filter()
                ->unique()
                ->values();
        }

        return $query
            ->where('faculty_user_id', $user->id)
            ->pluck('section')
            ->filter()
            ->unique()
            ->values();
    }

    private function labelForTarget($target, array $handledStudents): string
    {
        if ($target->target_type === 'student') {
            foreach ($handledStudents as $student) {
                if ((string) $student['id'] === (string) $target->target_id) {
                    return $student['name'] ?: ('Student #'.$target->target_id);
                }
            }

            return 'Student #'.$target->target_id;
        }

        return (string) $target->target_id;
    }

    /**
     * Sync requirement targets.
     */
    private function syncTargets(OjtRequirementTemplate $requirement, array $targets)
    {
        $requirement->targets()->delete();
        
        if (empty($targets)) {
            return; // Prevent SQL crash on empty insert
        }

        $records = array_map(function ($t) use ($requirement) {
            return [
                'requirement_template_id' => $requirement->id,
                'target_type' => $t['type'],
                'target_id' => (string) $t['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $targets);

        \App\Models\RequirementTarget::insert($records);
    }
}





