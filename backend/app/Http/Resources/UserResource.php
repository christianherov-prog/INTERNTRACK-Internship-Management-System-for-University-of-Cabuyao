<?php

namespace App\Http\Resources;

use App\Support\IenrollProfileLock;
use App\Support\NameParts;
use App\Support\NotificationPreferences;
use App\Support\SexOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource
 *
 * Transforms a User model (with eager-loaded profiles and activeInternship)
 * into a standardized JSON payload consumed by the frontend.
 *
 * Replace all calls to AuthController::formatUser() with:
 *   new UserResource($user)
 */
class UserResource extends JsonResource
{
    /** Roles that are provisioned and locked by iEnroll. */
    private const IENROLL_ROLES = ['student', 'faculty', 'coordinator', 'director'];

    /** Human-readable label per role. */
    private const ROLE_LABELS = [
        'student'     => 'Student Account',
        'supervisor'  => 'Company Supervisor',
        'faculty'     => 'Faculty Supervisor',
        'coordinator' => 'Practicum Supervisor',
        'director'    => 'PALD Director',
        'admin'       => 'MISD Administrator',
    ];

    /** Default dashboard route per role. */
    private const ROLE_ROUTES = [
        'student'     => '/student/dashboard',
        'supervisor'  => '/supervisor/dashboard',
        'faculty'     => '/faculty/dashboard',
        'coordinator' => '/coordinator/monitoring',
        'director'    => '/director/dashboard',
        'admin'       => '/admin/dashboard',
    ];

    public function toArray(Request $request): array
    {
        $profile = $this->studentProfile
            ?? $this->facultyProfile
            ?? $this->supervisorProfile;

        $firstName  = $profile?->first_name;
        $middleName = $profile?->middle_name;
        $lastName   = $profile?->last_name;
        $suffix     = $profile?->suffix ?? null;
        $name       = NameParts::display($firstName, $middleName, $lastName, $suffix);

        if ($name === '') {
            $name = $this->username;
        }

        $email   = $profile?->email ?? $this->email ?? '';
        $contact = $profile?->contact_number ?? '';
        
        $program = '';
        if ($this->isStudent() && $this->studentProfile) {
            $program = $this->studentProfile->program?->name ?? '';
        } elseif ($this->isFaculty() && $this->facultyProfile) {
            $program = $this->facultyProfile->department?->name ?? '';
        } elseif ($this->isCoordinator() && $this->facultyProfile) {
            $program = $this->facultyProfile->department?->name ?? '';
        }

        return [
            // Identity
            'id'          => $this->id,
            'username'    => $this->username,
            'role'        => $this->role,
            'name'        => $name,
            'first_name'  => $firstName,
            'middle_name' => $middleName,
            'last_name'   => $lastName,
            'suffix'      => $suffix,
            'email'       => $email,
            'contact'     => $contact,
            'program'     => $program,
            'course_description' => $this->studentProfile?->course_description ?? '',
            'position'    => $profile?->position ?? '',
            'company'     => $this->resolveCompany(),
            'sex'         => $this->resolveSex($profile),
            'sex_editable'=> SexOptions::isEditableRole($this->role),

            // iEnroll lock metadata
            'profile_editable' => IenrollProfileLock::isProfileEditable($this->role),
            'locked_source'    => IenrollProfileLock::lockedSource($this->role),

            // Read-only iEnroll fields
            'student_number'    => $this->studentProfile?->student_number,
            'section'           => $this->studentProfile?->section,
            'year_level'        => $this->studentProfile?->year_level,
            'department'        => $this->studentProfile?->department ?? $this->facultyProfile?->department,
            'faculty_number'    => $this->facultyProfile?->faculty_number,
            'employment_status' => $this->facultyProfile?->employment_status,

            // Display helpers
            'avatar'    => $this->resolveAvatarInitials($firstName, $lastName, $name),
            'avatarUrl' => $this->resolveAvatarUrl($this->avatar_path),
            'subtitle'  => $this->resolveSubtitle($profile),
            'roleLabel' => self::ROLE_LABELS[$this->role] ?? ucfirst($this->role),
            'dashRoute' => self::ROLE_ROUTES[$this->role] ?? '/',

            // App metadata
            'term'        => config('interntrack.current_term', 'AY 2025-2026, 2nd Semester'),
            'coordinator' => $this->resolveCoordinatorName(),
            'faculty'     => $this->resolveFacultyName(),
            'lastLoginAt' => optional($this->last_login_at)?->toIso8601String(),

            // Practicum progress (for students)
            'hours_rendered' => $this->isStudent() ? (float) ($this->activeInternship?->rendered_hours ?? 0) : null,
            'target_hours'   => $this->isStudent() ? (float) ($this->activeInternship?->target_hours ?? 500) : null,
            'hours_progress' => $this->isStudent() ? (int) (
                ($this->activeInternship?->target_hours ?? 500) > 0
                    ? min(100, max(0, round((($this->activeInternship?->rendered_hours ?? 0) / ($this->activeInternship?->target_hours ?? 500)) * 100)))
                    : 0
            ) : null,

            // Preferences & security
            'notificationPreferences' => NotificationPreferences::mergeForUser(
                $this->role,
                is_array($this->notification_preferences) ? $this->notification_preferences : []
            ),
            'must_change_password' => (bool) $this->must_change_password,
        ];
    }

    // ─── Private Resolvers ────────────────────────────────────────────────────

    private function resolveCompany(): string
    {
        $company = $this->activeInternship?->company?->company_name ?? '';

        if ($this->role === 'supervisor' && $company === '') {
            $supervised = $this->internshipsSupervised()->with('company')->latest()->first();
            $company = $supervised?->company?->company_name ?? '';
        }

        return $company;
    }

    private function resolveSex(mixed $profile): ?string
    {
        $sex = match ($this->role) {
            'student'                           => $this->studentProfile?->sex,
            'supervisor'                        => $this->supervisorProfile?->sex,
            'faculty', 'coordinator', 'director'=> $this->facultyProfile?->sex,
            'admin'                             => $this->sex ?? $this->facultyProfile?->sex,
            default                             => $this->sex,
        };

        return SexOptions::sanitize($sex);
    }

    private function resolveCoordinatorName(): string
    {
        $internship  = $this->activeInternship;
        $coordinator = $internship?->coordinator;

        if (!$coordinator) {
            $coordinator = \App\Models\User::where('role', 'coordinator')
                ->where('is_active', true)
                ->with('facultyProfile')
                ->orderBy('id')
                ->first();
        }

        if (!$coordinator) {
            return 'N/A';
        }

        $profile  = $coordinator->facultyProfile;
        $fullName = trim("{$profile?->first_name} {$profile?->last_name}");

        return $fullName !== '' ? $fullName : ($coordinator->username ?: 'N/A');
    }

    private function resolveFacultyName(): string
    {
        if ($this->activeInternship?->faculty?->facultyProfile) {
            $p = $this->activeInternship->faculty->facultyProfile;
            return trim("{$p->first_name} {$p->last_name}");
        }

        $faculty = app(\App\Services\FacultySectionAssignmentService::class)
            ->resolveFacultyForProfile($this->studentProfile);

        if ($faculty?->facultyProfile) {
            return trim("{$faculty->facultyProfile->first_name} {$faculty->facultyProfile->last_name}");
        }

        return 'Not Assigned';
    }

    private function resolveSubtitle(mixed $profile): string
    {
        $coordinatorPrefix = 'CCS Coordinator';
        if ($this->role === 'coordinator') {
            $departmentName = $this->facultyProfile?->department;
            if (stripos($departmentName, 'Computing') !== false || stripos($departmentName, 'CCS') !== false) {
                $coordinatorPrefix = 'CCS Coordinator';
            } elseif (stripos($departmentName, 'Engineering') !== false || stripos($departmentName, 'COE') !== false) {
                $coordinatorPrefix = 'COE Coordinator';
            }
        }

        return match ($this->role) {
            'student'     => $this->formatStudentSubtitle($profile),
            'supervisor'  => 'Company Supervisor · ' . $this->username,
            'faculty'     => 'Faculty Supervisor · ' . $this->username,
            'coordinator' => $coordinatorPrefix . ' · ' . $this->username,
            'director'    => 'PALD Director · ' . $this->username,
            'admin'       => 'MISD Administrator · ' . $this->username,
            default       => $this->username,
        };
    }

    private function formatStudentSubtitle(mixed $profile): string
    {
        $section = trim((string) ($profile?->section ?? ''));
        $year    = $profile?->year_level;

        // Strip redundant duplicate year prefix if present (e.g. "4 - 4IT-D" -> "4IT-D")
        $section = preg_replace('/^(\d+)\s*-\s*(?=\1)/', '', $section);

        if (preg_match('/^(\d+)[\s\-_]*([A-Za-z]+?)[\s\-_]*([A-Za-z])$/', $section, $m)) {
            $label = $m[1] . strtoupper($m[2]) . ' - ' . strtoupper($m[3]);
        } elseif (preg_match('/^([A-Za-z]+?)[\s\-_]*([A-Za-z])$/', $section, $m)) {
            $label = ($year ? (string) $year : '') . strtoupper($m[1]) . ' - ' . strtoupper($m[2]);
        } elseif ($section !== '') {
            $label = $section;
        } elseif ($year) {
            $label = (string) $year;
        } else {
            $label = 'Student';
        }

        return "{$label} | {$this->username}";
    }

    private function resolveAvatarInitials(?string $firstName, ?string $lastName, ?string $name): string
    {
        $name  = $name ?? '';
        $first = $firstName ?: ($name !== '' ? explode(' ', $name)[0] : 'U');
        $last  = $lastName ?: 'U';

        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }

    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $filename   = basename($normalized);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $base . '/api/v1/media/avatars/' . $filename;
    }
}
