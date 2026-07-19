<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MisdIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected MisdIntegrationService $misd) {}

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Authenticate against the real user store.

        $username = strtoupper(trim($request->username));
        $user     = User::where('username', $username)->first();

        // Auto-provision from MISD if user doesn't exist locally
        if (!$user) {
            $user = $this->misd->provision($username, $request->password);
            if (!$user) {
                throw ValidationException::withMessages([
                    'username' => ['Invalid credentials. Please check your ID and password.'],
                ]);
            }
        }

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials. Please check your ID and password.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account has been deactivated. Please contact your coordinator.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $user->tokens()->delete();
        $token = $user->createToken('interntrack-session')->plainTextToken;

        audit_log($user->id, 'login', ['ip' => $request->ip()]);

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user->load($this->userRelations())),
        ]);
    }

    /** POST /api/v1/auth/logout — revoke the current Sanctum personal access token. */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Revoke only this request's token (not every device session).
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        audit_log($user->id, 'logout', []);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load($this->userRelations());
        return response()->json(['user' => $this->formatUser($user)]);
    }

    /** POST /api/v1/auth/avatar — multipart upload; persists path on the user record. */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB
        ]);

        $user = $request->user();

        // Remove the previous photo so orphaned files don't accumulate in storage.
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = Storage::disk('public')->putFile('avatars', $request->file('avatar'));
        $user->forceFill(['avatar_path' => $path])->save();
        $user->refresh();

        audit_log($user->id, 'update_avatar', []);

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'user'    => $this->formatUser($user->load($this->userRelations())),
        ]);
    }

    /** Same eager-loads used for login /me / avatar so company + coordinator both resolve. */
    private function userRelations(): array
    {
        return [
            'studentProfile',
            'facultyProfile',
            'supervisorProfile',
            'activeInternship.company',
            'activeInternship.coordinator.facultyProfile',
        ];
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        audit_log($user->id, 'change_password', []);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * PUT /api/v1/auth/profile — persist editable profile fields to the real profile tables.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user()->load(['studentProfile', 'facultyProfile', 'supervisorProfile']);

        $data = $request->validate([
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'name' => 'sometimes|nullable|string|max:200',
            'email' => 'sometimes|nullable|email|max:255',
            'contact' => 'sometimes|nullable|string|max:40',
            'contact_number' => 'sometimes|nullable|string|max:40',
            'program' => 'sometimes|nullable|string|max:255',
            'position' => 'sometimes|nullable|string|max:255',
            'company' => 'sometimes|nullable|string|max:255',
        ]);

        // Allow a single "name" field from the Settings UI → split into first/last.
        if (!empty($data['name']) && empty($data['first_name']) && empty($data['last_name'])) {
            $parts = preg_split('/\s+/', trim($data['name']), 2);
            $data['first_name'] = $parts[0] ?? '';
            $data['last_name'] = $parts[1] ?? ($parts[0] ?? '');
        }

        $contact = $data['contact_number'] ?? $data['contact'] ?? null;

        if ($user->role === 'student' && $user->studentProfile) {
            $user->studentProfile->fill(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'contact_number' => $contact,
                'program' => $data['program'] ?? null,
            ], fn ($v) => $v !== null))->save();
        } elseif (in_array($user->role, ['faculty', 'coordinator', 'director'], true) && $user->facultyProfile) {
            $user->facultyProfile->fill(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'contact_number' => $contact,
                'position' => $data['position'] ?? null,
                'department' => $data['program'] ?? null,
            ], fn ($v) => $v !== null))->save();
        } elseif ($user->role === 'supervisor' && $user->supervisorProfile) {
            $user->supervisorProfile->fill(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'contact_number' => $contact,
                'position' => $data['position'] ?? null,
            ], fn ($v) => $v !== null))->save();
        } else {
            return response()->json(['message' => 'No profile record found for this account.'], 422);
        }

        if (array_key_exists('email', $data) && $data['email']) {
            $user->forceFill(['email' => $data['email']])->save();
        }

        audit_log($user->id, 'update_profile', []);

        $user->refresh()->load($this->userRelations());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Format student identity line: "4 - IT D | 2300600"
     * Uses section codes like "4ITD" (year + program + block), never a "BS" course prefix.
     */
    private function formatStudentSubtitle($profile, string $username): string
    {
        $section = trim((string) ($profile?->section ?? ''));
        $year    = $profile?->year_level;

        // "4ITD" → "4 - IT D"
        if (preg_match('/^(\d+)\s*([A-Za-z]+?)([A-Za-z])$/', $section, $m)) {
            $label = $m[1] . ' - ' . strtoupper($m[2]) . ' ' . strtoupper($m[3]);
        } elseif ($section !== '') {
            $label = $year ? "{$year} - {$section}" : $section;
        } elseif ($year) {
            $label = (string) $year;
        } else {
            $label = 'Student';
        }

        return "{$label} | {$username}";
    }

    /**
     * Resolve the practicum coordinator's display name for Profile Summary.
     *
     * Priority:
     *  1. internships.coordinator_id → users → faculty_profiles (when placed)
     *  2. Active coordinator user account (students may have no internship yet)
     *
     * Returns "N/A" only when no coordinator user exists in the system.
     */
    private function resolveCoordinatorName(?\App\Models\Internship $internship): string
    {
        $coordinator = null;

        if ($internship) {
            // Legacy placements sometimes set company_id but left coordinator_id null.
            if (!$internship->coordinator_id) {
                $defaultCoordId = $this->defaultCoordinatorId();
                if ($defaultCoordId) {
                    $internship->forceFill(['coordinator_id' => $defaultCoordId])->saveQuietly();
                    $internship->load('coordinator.facultyProfile');
                }
            }
            $coordinator = $internship->coordinator;
        }

        // No placement yet (or coordinator still missing) — use the practicum
        // coordinator of record so Profile Summary is never blank when one exists.
        if (!$coordinator) {
            $coordinator = User::where('role', 'coordinator')
                ->where('is_active', true)
                ->with('facultyProfile')
                ->orderBy('id')
                ->first();
        }

        if (!$coordinator) {
            return 'N/A';
        }

        $profile = $coordinator->facultyProfile;
        if ($profile) {
            $fullName = trim("{$profile->first_name} {$profile->last_name}");
            if ($fullName !== '') {
                return $fullName;
            }
        }

        return $coordinator->username ?: 'N/A';
    }

    /** First active coordinator account id (seeded COR-1001 / Arcelito Quiatchon). */
    private function defaultCoordinatorId(): ?int
    {
        $id = User::where('role', 'coordinator')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function formatUser(User $user): array
    {
        $profile = $user->studentProfile ?? $user->facultyProfile ?? $user->supervisorProfile;
        $name    = $profile ? trim("{$profile->first_name} {$profile->last_name}") : $user->username;
        $email   = $profile?->email ?? $user->email ?? '';
        $contact = $profile?->contact_number ?? '';
        $program = $profile?->program ?? $profile?->department ?? '';
        $position = $profile?->position ?? '';
        $company = $user->activeInternship?->company?->company_name ?? '';
        // Supervisors are not students — resolve HTE company from an internship they supervise.
        if ($user->role === 'supervisor' && $company === '') {
            $supervised = $user->internshipsSupervised()->with('company')->latest()->first();
            $company = $supervised?->company?->company_name ?? '';
        }
        // Same source pattern as company: internships → related row → display name.
        // Company: internships.company_id → companies.company_name
        // Coordinator: internships.coordinator_id → users → faculty_profiles first/last name
        $coord   = $this->resolveCoordinatorName($user->activeInternship);

        $parts  = explode(' ', $name);
        $avatar = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr(end($parts) ?? '', 0, 1));

        $roleLabels = [
            'student'     => 'Student Account',
            'supervisor'  => 'Company Supervisor',
            'faculty'     => 'Faculty Supervisor',
            'coordinator' => 'Practicum Supervisor',
            'director'    => 'PALD Director',
            'admin'       => 'System Administrator',
        ];
        $roleRoutes = [
            'student'     => '/student/dashboard',
            'supervisor'  => '/supervisor/dashboard',
            'faculty'     => '/faculty/dashboard',
            'coordinator' => '/coordinator/monitoring',
            'director'    => '/director/dashboard',
            'admin'       => '/admin/dashboard',
        ];

        // Student: "4 - IT D | 2300600" (year - program section | student no). No "BS" prefix.
        $subtitleMap = [
            'student'     => $this->formatStudentSubtitle($profile, $user->username),
            'supervisor'  => 'Company Supervisor · ' . $user->username,
            'faculty'     => 'Faculty Supervisor · ' . $user->username,
            'coordinator' => 'Practicum Supervisor · ' . $user->username,
            'director'    => 'PALD Director · ' . $user->username,
        ];

        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'role'        => $user->role,
            'name'        => $name,
            'email'       => $email,
            'contact'     => $contact,
            'program'     => $program,
            'position'    => $position,
            'company'     => $company,
            'avatar'      => $avatar,
            'avatarUrl'   => $this->resolveAvatarUrl($user->avatar_path),
            'subtitle'    => $subtitleMap[$user->role] ?? $user->username,
            'roleLabel'   => $roleLabels[$user->role] ?? ucfirst($user->role),
            'dashRoute'   => $roleRoutes[$user->role] ?? '/',
            'term'         => config('interntrack.current_term', 'AY 2024-2025, Sem 2'),
            'coordinator'  => $coord,
            'lastLoginAt'  => optional($user->last_login_at)?->toIso8601String(),
        ];
    }

    /**
     * Build a publicly reachable avatar URL from the current API request host.
     * Using the request host (not only APP_URL) avoids broken images when
     * `php artisan serve` runs on a port that APP_URL omits (e.g. :8001).
     */
    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $base.'/storage/'.ltrim($path, '/');
    }
}
