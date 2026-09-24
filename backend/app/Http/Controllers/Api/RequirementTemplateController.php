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
use App\Support\DepartmentScope;
use App\Support\UploadLimits;
use App\Models\AuditLog;

class RequirementTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Faculty: own customs + system standards.
        // Coordinator: own customs ONLY (standards remain faculty-side).
        $requirements = OjtRequirementTemplate::with(['targets', 'attachments'])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id);
                if ($user->isFaculty() && ! $user->isCoordinator()) {
                    $q->orWhere('is_system', true);
                }
            })
            ->orderByDesc('is_system')
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
                'id_number' => $profile?->id_number ?? $student->student_number,
                'initials' => $profile ? strtoupper(substr($profile->first_name ?? '', 0, 1) . substr($profile->last_name ?? '', 0, 1)) : strtoupper(substr($student->username, 0, 2)),
                'section_name' => $profile?->section,
                'program_name' => $profile?->program?->name,
                'internship_id' => $internship ? $internship->id : null,
            ];
        }

        // 3. For each requirement, determine assigned students
        $requirements->transform(function ($req) use ($handledStudents) {
            $hasTargets = $req->targets->isNotEmpty();
            $assignedStudents = collect($handledStudents)->filter(function ($hs) use ($req, $hasTargets) {
                // Empty targets = system-wide / all students in reviewer scope
                if (! $hasTargets) {
                    return true;
                }
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
                $aliases = \App\Services\DocumentComplianceService::aliasesForCode($req->system_code, $req->name);
                $submissions = Document::with(['internship', 'reviewer.facultyProfile', 'attachments'])
                    ->where(function ($q) use ($req, $aliases) {
                        $q->where('document_type', $req->name);
                        foreach ($aliases as $alias) {
                            if ($alias !== $req->name) {
                                $q->orWhere('document_type', $alias);
                            }
                        }
                    })
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
                    'file_url' => null,
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

            $req->setAttribute('requirement_type', $req->is_system ? 'standard' : 'custom');
            $req->setAttribute('target_type_label', $hasTargets
                ? ($req->targets->pluck('target_type')->unique()->implode(', '))
                : 'All eligible students');
            $req->submissions = $mappedSubmissions;
            $req->total_assigned = $assignedStudents->count();
            $req->completed_count = $mappedSubmissions->whereIn('status', ['approved', 'completed'])->count();
            $req->pending_count = $mappedSubmissions->filter(fn ($s) => in_array($s['status'], [
                'pending', 'pending_review', 'pending_faculty', 'under_review', 'resubmitted', 'submitted',
            ], true))->count();
            $req->missing_count = max(0, $req->total_assigned - $req->completed_count - $req->pending_count);

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
            $deptId = DepartmentScope::departmentIdFor($user);
            if ($deptId) {
                $programsQuery->where('department_id', $deptId);
            } else {
                $programsQuery->whereRaw('1 = 0');
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
        $maxFiles = UploadLimits::maxFiles();
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'targets' => 'required|array|min:1',
            'template_files' => "nullable|array|max:{$maxFiles}",
            'template_files.*' => 'nullable|'.UploadLimits::fileRule('doc,docx,pdf,jpg,jpeg,png'),
            'drive_link' => 'nullable|url',
        ], UploadLimits::maxMessages('template_files'));

        return DB::transaction(function () use ($request) {
            $requirement = OjtRequirementTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'is_system' => false,
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
            \App\Support\RequiredDocuments::clearCache();

            audit_log($request->user()->id, 'requirement_created', [
                'requirement_id' => $requirement->id,
                'name' => $requirement->name,
                'is_system' => false,
            ]);

            return response()->json([
                'message' => 'Requirement template created successfully.',
                'requirement' => $requirement->load('targets', 'attachments')
            ], 201);
        });
    }

    /**
     * Update an existing requirement.
     * System standards: faculty may edit display metadata (not system_code / is_system).
     * Custom: owner only; targets required.
     */
    public function update(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::findOrFail($id);
        $user = $request->user();

        if ($requirement->is_system) {
            if (! $user->isFaculty() || $user->isCoordinator()) {
                return response()->json([
                    'message' => 'Only Faculty may edit standard system requirements.',
                ], 403);
            }
        } elseif ((int) $requirement->created_by !== (int) $user->id) {
            abort(403, 'You may only edit requirement templates you created.');
        }

        $maxFiles = UploadLimits::maxFiles();
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'template_files' => "nullable|array|max:{$maxFiles}",
            'template_files.*' => 'nullable|'.UploadLimits::fileRule('doc,docx,pdf,jpg,jpeg,png'),
            'drive_link' => 'nullable|url',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer|exists:requirement_template_attachments,id',
        ];

        if (! $requirement->is_system) {
            $rules['targets'] = 'required|array|min:1';
        } else {
            $rules['targets'] = 'nullable|array';
        }

        $request->validate($rules, UploadLimits::maxMessages('template_files'));

        return DB::transaction(function () use ($request, $requirement) {
            // Validate new files before deleting existing attachments (replacement safety).
            if ($request->hasFile('template_files')) {
                foreach ($request->file('template_files') as $file) {
                    if (! $file || ! $file->isValid()) {
                        return response()->json([
                            'message' => UploadLimits::oversizedMessage($file?->getClientOriginalName()),
                            'errors' => [
                                'template_files' => [UploadLimits::oversizedMessage($file?->getClientOriginalName())],
                            ],
                        ], 422);
                    }
                }
            }

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

            $old = [
                'name' => $requirement->name,
                'description' => $requirement->description,
                'deadline' => $requirement->deadline?->toIso8601String(),
                'is_active' => $requirement->is_active,
            ];

            $payload = [
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? $requirement->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'deadline' => $request->deadline,
                'drive_link' => $request->has('drive_link') ? $request->drive_link : $requirement->drive_link,
            ];
            // Never allow clients to alter identity of system templates.
            unset($payload['is_system'], $payload['system_code'], $payload['created_by']);

            $requirement->update($payload);

            if (! $requirement->is_system && $request->has('targets')) {
                $this->syncTargets($requirement, $request->input('targets', []));
            }

            \App\Support\RequiredDocuments::clearCache();

            audit_log($request->user()->id, 'requirement_updated', [
                'requirement_id' => $requirement->id,
                'system_code' => $requirement->system_code,
                'old' => $old,
                'new' => [
                    'name' => $requirement->name,
                    'description' => $requirement->description,
                    'deadline' => $requirement->deadline?->toIso8601String(),
                    'is_active' => $requirement->is_active,
                ],
            ]);

            return response()->json([
                'message' => 'Requirement template updated successfully.',
                'requirement' => $requirement->fresh()->load('targets', 'attachments')
            ]);
        });
    }

    /**
     * Delete a requirement.
     */
    public function destroy(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::with('attachments')->findOrFail($id);

        if ($requirement->is_system) {
            return response()->json([
                'message' => 'System requirement templates cannot be deleted.',
            ], 422);
        }

        if ((int) $requirement->created_by !== (int) $request->user()->id) {
            abort(403, 'You may only delete requirement templates you created.');
        }

        return DB::transaction(function () use ($requirement, $request) {
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
            $name = $requirement->name;
            $id = $requirement->id;
            $requirement->delete();
            \App\Support\RequiredDocuments::clearCache();

            audit_log($request->user()->id, 'requirement_deleted', [
                'requirement_id' => $id,
                'name' => $name,
            ]);

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
        $deptId = DepartmentScope::departmentIdFor($user);

        if ($user->isCoordinator()) {
            if ($deptId) {
                $query->whereHas('studentProfile', fn ($q) => DepartmentScope::constrainStudentProfiles($q, $deptId));
            } else {
                $query->whereRaw('1 = 0');
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
            $deptId = DepartmentScope::departmentIdFor($user);
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





