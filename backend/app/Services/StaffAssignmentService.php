<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Assign and manage staff roles (director / coordinator) for the MISD admin portal.
 */
class StaffAssignmentService
{
    public function __construct(private MisdIntegrationService $misd)
    {
    }

    /**
     * Assign (or promote) a user to director/coordinator.
     * Prefer MISD faculty lookup; fall back to manual profile fields.
     */
    public function assign(string $employeeNumber, string $role, User $actor, array $manual = []): User
    {
        if (!in_array($role, ['director', 'coordinator'], true)) {
            throw ValidationException::withMessages(['role' => 'Role must be director or coordinator.']);
        }

        $employeeNumber = strtoupper(trim($employeeNumber));
        if ($employeeNumber === '') {
            throw ValidationException::withMessages(['employee_number' => 'Employee number is required.']);
        }

        $misdData = $this->misd->fetchFaculty($employeeNumber);
        $user = User::withTrashed()->where('username', $employeeNumber)->first();
        $oldRole = $user?->role;
        $defaultPw = config('interntrack.default_password', 'interntrack123');

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->update([
                'role'      => $role,
                'is_active' => true,
                'email'     => $manual['email'] ?? $misdData['email'] ?? $user->email,
            ]);
        } else {
            $user = User::create([
                'username'  => $employeeNumber,
                'email'     => $manual['email'] ?? $misdData['email'] ?? null,
                'password'  => Hash::make($defaultPw),
                'role'      => $role,
                'is_active' => true,
            ]);
        }

        $this->upsertFacultyProfile($user, $employeeNumber, $misdData, $manual, $role);

        $this->audit($actor, 'staff.assigned', $user, [
            'old_role' => $oldRole,
            'new_role' => $role,
        ], [
            'employee_number' => $employeeNumber,
            'source'          => !empty($misdData) ? 'misd' : 'manual',
        ]);

        return $user->fresh(['facultyProfile']);
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
        }

        if (!$active && $user->role === 'director') {
            $activeDirectors = User::where('role', 'director')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeDirectors === 0) {
                throw ValidationException::withMessages([
                    'user' => 'Cannot deactivate the last active director. Assign another director first.',
                ]);
            }
        }

        if (!$active && $user->role === 'admin') {
            $activeAdmins = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeAdmins === 0) {
                throw ValidationException::withMessages([
                    'user' => 'Cannot deactivate the last active MISD admin.',
                ]);
            }
        }

        $old = $user->is_active;
        $user->update(['is_active' => $active]);

        $this->audit($actor, $active ? 'staff.activated' : 'staff.deactivated', $user, [
            'is_active' => $old,
        ], [
            'is_active' => $active,
        ]);

        return $user->fresh(['facultyProfile', 'studentProfile', 'supervisorProfile']);
    }

    /**
     * Demote a director/coordinator to faculty (keeps account) or deactivate.
     */
    public function revoke(User $user, User $actor, string $mode = 'deactivate'): User
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages(['user' => 'You cannot revoke your own account.']);
        }

        if (!in_array($user->role, ['director', 'coordinator'], true)) {
            throw ValidationException::withMessages(['user' => 'Only directors and coordinators can be revoked here.']);
        }

        if ($user->role === 'director') {
            $activeDirectors = User::where('role', 'director')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($activeDirectors === 0 && $mode !== 'promote_other_first') {
                throw ValidationException::withMessages([
                    'user' => 'Cannot revoke the last active director. Assign another director first.',
                ]);
            }
        }

        $oldRole = $user->role;

        if ($mode === 'demote_faculty') {
            $user->update(['role' => 'faculty', 'is_active' => true]);
        } else {
            $user->update(['is_active' => false]);
        }

        $this->audit($actor, 'staff.revoked', $user, [
            'role'      => $oldRole,
            'is_active' => true,
        ], [
            'role'      => $user->role,
            'is_active' => $user->is_active,
            'mode'      => $mode,
        ]);

        return $user->fresh(['facultyProfile']);
    }

    public function resetPassword(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages(['user' => 'Use Settings to change your own password.']);
        }

        $defaultPw = config('interntrack.default_password', 'interntrack123');
        $user->update([
            'password' => Hash::make($defaultPw),
            'must_change_password' => true,
        ]);

        $this->audit($actor, 'staff.password_reset', $user, null, [
            'username' => $user->username,
        ]);
    }

    public function syncFromMisd(User $user): User
    {
        if (!in_array($user->role, ['faculty', 'coordinator', 'director', 'admin'], true)) {
            throw ValidationException::withMessages(['user' => 'MISD profile sync is only for faculty/staff accounts.']);
        }

        $data = $this->misd->fetchFaculty($user->username);
        if (empty($data)) {
            // Clear cache and retry once
            $this->misd->forgetFacultyCache($user->username);
            $data = $this->misd->fetchFaculty($user->username);
        }

        if (empty($data)) {
            throw ValidationException::withMessages(['user' => 'No MISD faculty record found for ' . $user->username]);
        }

        $this->upsertFacultyProfile($user, $user->username, $data, [], $user->role);
        if (!empty($data['email'])) {
            $user->update(['email' => $data['email']]);
        }

        return $user->fresh(['facultyProfile']);
    }

    public function formatStaff(User $user): array
    {
        $fp = $user->facultyProfile;

        return [
            'id'              => $user->id,
            'username'        => $user->username,
            'email'           => $user->email ?? $fp?->email,
            'role'            => $user->role,
            'is_active'       => (bool) $user->is_active,
            'last_login_at'   => optional($user->last_login_at)?->toIso8601String(),
            'name'            => $fp
                ? trim("{$fp->first_name} {$fp->last_name}")
                : $user->username,
            'first_name'      => $fp?->first_name,
            'middle_name'     => $fp?->middle_name,
            'last_name'       => $fp?->last_name,
            'contact_number'  => $fp?->contact_number,
            'department'      => $fp?->department,
            'college'         => $fp?->college,
            'position'        => $fp?->position,
            'employee_number' => $fp?->employee_number ?? $user->username,
            'synced_at'       => optional($fp?->synced_at)?->toIso8601String(),
            'created_at'      => optional($user->created_at)?->toIso8601String(),
        ];
    }

    private function upsertFacultyProfile(User $user, string $employeeNumber, array $misdData, array $manual, string $role): void
    {
        $positionDefault = match ($role) {
            'director'    => 'PALD Director',
            'coordinator' => 'Practicum Coordinator',
            'admin'       => 'MISD Administrator',
            default       => 'Faculty',
        };

        $payload = [
            'employee_number'   => $employeeNumber,
            'first_name'        => $manual['first_name'] ?? $misdData['first_name'] ?? 'UC',
            'middle_name'       => $manual['middle_name'] ?? $misdData['middle_name'] ?? null,
            'last_name'         => $manual['last_name'] ?? $misdData['last_name'] ?? 'Staff',
            'email'             => $manual['email'] ?? $misdData['email'] ?? $user->email,
            'contact_number'    => $manual['contact_number'] ?? $misdData['contact_number'] ?? null,
            'department'        => $manual['department'] ?? $misdData['department'] ?? ($role === 'director' ? 'Director' : 'CCS'),
            'college'           => $manual['college'] ?? $misdData['college'] ?? 'University of Cabuyao',
            'position'          => $manual['position'] ?? $misdData['position'] ?? $positionDefault,
            'employment_status' => $misdData['employment_status'] ?? 'Regular',
            'synced_at'         => !empty($misdData) ? now() : null,
        ];

        FacultyProfile::updateOrCreate(['user_id' => $user->id], $payload);
    }

    private function audit(User $actor, string $action, User $target, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $actor->id,
            'action'     => $action,
            'model_type' => User::class,
            'model_id'   => $target->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
