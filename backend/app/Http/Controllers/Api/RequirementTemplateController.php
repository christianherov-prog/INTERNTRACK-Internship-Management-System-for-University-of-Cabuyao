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
        $requirements = OjtRequirementTemplate::with('targets')
            ->where('created_by', $user->id)
            ->orderBy('sort_order')
            ->get();

        // 2. Fetch scoped students
        $studentsQuery = User::where('role', 'student')->with('studentProfile.program');
        
        if ($user->hasRole('faculty')) {
            $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $user->id)->pluck('section');
            $studentsQuery->whereHas('studentProfile', fn($q) => $q->whereIn('section', $sections));
        } elseif ($user->hasRole('coordinator')) {
            $deptId = $user->facultyProfile?->department_id;
            if ($deptId) {
                $studentsQuery->whereHas('studentProfile', fn($q) => $q->where('department_id', $deptId));
            }
        }
        
        $students = $studentsQuery->get();
        $internships = Internship::whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        
        $handledStudents = [];
        foreach ($students as $student) {
            $profile = $student->studentProfile;
            $internship = $internships->get($student->id);

            $handledStudents[] = [
                'id' => $student->id,
                'name' => $profile ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) : $student->username,
                'section_name' => $profile?->section,
                'program_name' => $profile?->program?->code,
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

            $validInternshipIds = $assignedStudents->pluck('internship_id')->filter()->values();
            
            $submissions = collect();
            if ($validInternshipIds->isNotEmpty()) {
                $submissions = Document::with('reviewer.facultyProfile')
                    ->where('document_type', $req->name)
                    ->whereIn('internship_id', $validInternshipIds)
                    ->get()
                    ->keyBy('internship_id');
            }

            $mappedSubmissions = $assignedStudents->map(function ($hs) use ($submissions, $req) {
                $doc = $hs['internship_id'] ? $submissions->get($hs['internship_id']) : null;
                
                $status = $doc?->status ?? 'not_submitted';
                if ($req->deadline && now()->greaterThan($req->deadline)) {
                    if (!$doc || $status === 'rejected' || $status === 'not_submitted') {
                        $status = 'no_submission';
                    }
                }

                $reviewerName = null;
                if ($doc && $doc->reviewer) {
                    $reviewerName = $doc->reviewer->facultyProfile ? trim($doc->reviewer->facultyProfile->first_name . ' ' . $doc->reviewer->facultyProfile->last_name) : $doc->reviewer->username;
                }
                
                return [
                    'student_id' => $hs['id'],
                    'student_name' => $hs['name'] ?: 'Unknown Student',
                    'section' => $hs['section_name'],
                    'status' => $status,
                    'submitted_at' => $doc?->submitted_at ? clone $doc->submitted_at : null,
                    'file_url' => $doc?->file_path ? url('/api/v1/files/download?path=' . urlencode($doc->file_path)) : null,
                    'file_path' => $doc?->file_path,
                    'drive_link' => $doc?->drive_link,
                    'document_id' => $doc?->id,
                    'remarks' => $doc?->remarks,
                    'reviewed_by_name' => $reviewerName,
                    'reviewed_at' => $doc?->reviewed_at,
                ];
            });

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
        
        if ($user->hasRole('faculty')) {
            $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $user->id)->pluck('section');
            $studentsQuery->whereHas('studentProfile', fn($q) => $q->whereIn('section', $sections));
        } elseif ($user->hasRole('coordinator')) {
            $deptId = $user->facultyProfile?->department_id;
            if ($deptId) {
                $studentsQuery->whereHas('studentProfile', fn($q) => $q->where('department_id', $deptId));
            }
        }
        
        $users = $studentsQuery->get();

        $students = [];
        $sections = [];
        $programs = [];
        
        $seenSections = [];
        $seenPrograms = [];
        
        foreach($users as $u) {
            $profile = $u->studentProfile;
            if (!$profile) continue;
            
            $name = trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''));
            $students[] = [
                'id' => $u->id,
                'name' => $name ?: $u->username,
                'section' => $profile->section
            ];

            if ($profile->section && !in_array($profile->section, $seenSections)) {
                $sections[] = [
                    'id' => $profile->section,
                    'name' => $profile->section,
                ];
                $seenSections[] = $profile->section;
            }

            if ($profile->program_id && !in_array($profile->program_id, $seenPrograms)) {
                $programs[] = [
                    'id' => $profile->program_id,
                    'name' => $profile->program ? ($profile->program->code ?? $profile->program->name) : 'Program ' . $profile->program_id,
                ];
                $seenPrograms[] = $profile->program_id;
            }
        }

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
            'targets' => 'required|array', // e.g. [['type' => 'section', 'id' => 1], ['type' => 'student', 'id' => 12]]
            'template_file' => 'nullable|file|mimes:doc,docx,pdf|max:10240',
            'drive_link' => 'nullable|url',
        ]);

        return DB::transaction(function () use ($request) {
            $templateFilePath = null;
            if ($request->hasFile('template_file')) {
                $file = $request->file('template_file');
                $templateFilePath = $file->store("requirement_templates", 'local');
            }

            $requirement = OjtRequirementTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'deadline' => $request->deadline,
                'template_file_path' => $templateFilePath,
                'drive_link' => $request->drive_link,
                'created_by' => $request->user()->id,
                'sort_order' => OjtRequirementTemplate::max('sort_order') + 1,
            ]);

            $this->syncTargets($requirement, $request->input('targets'));

            return response()->json([
                'message' => 'Requirement template created successfully.',
                'requirement' => $requirement->load('targets')
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
            'template_file' => 'nullable|file|mimes:doc,docx,pdf|max:10240',
            'drive_link' => 'nullable|url',
            'remove_template' => 'boolean'
        ]);

        return DB::transaction(function () use ($request, $requirement) {
            $templateFilePath = $requirement->template_file_path;

            if ($request->boolean('remove_template')) {
                if ($templateFilePath) {
                    Storage::disk('local')->delete($templateFilePath);
                }
                $templateFilePath = null;
            } elseif ($request->hasFile('template_file')) {
                if ($templateFilePath) {
                    Storage::disk('local')->delete($templateFilePath);
                }
                $file = $request->file('template_file');
                $templateFilePath = $file->store("requirement_templates", 'local');
            }

            $oldDeadline = $requirement->deadline?->toIso8601String();

            $requirement->update([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category ?? 'general',
                'is_active' => $request->boolean('is_active', true),
                'deadline' => $request->deadline,
                'template_file_path' => $templateFilePath,
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
                'requirement' => $requirement->load('targets')
            ]);
        });
    }

    /**
     * Delete a requirement.
     */
    public function destroy(Request $request, $id)
    {
        $requirement = OjtRequirementTemplate::where('created_by', $request->user()->id)->findOrFail($id);

        return DB::transaction(function () use ($requirement) {
            if ($requirement->template_file_path) {
                Storage::disk('local')->delete($requirement->template_file_path);
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
        $requirement = OjtRequirementTemplate::findOrFail($id);
        
        if (!$requirement->template_file_path || !Storage::disk('local')->exists($requirement->template_file_path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $fileName = $requirement->name . '_template.' . pathinfo($requirement->template_file_path, PATHINFO_EXTENSION);
        
        if ($request->query('preview')) {
            $mime = Storage::disk('local')->mimeType($requirement->template_file_path) ?: 'application/octet-stream';
            return Storage::disk('local')->response(
                $requirement->template_file_path,
                $fileName,
                [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                ]
            );
        }

        return Storage::disk('local')->download($requirement->template_file_path, $fileName);
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
                'target_id' => $t['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $targets);

        \App\Models\RequirementTarget::insert($records);
    }
}