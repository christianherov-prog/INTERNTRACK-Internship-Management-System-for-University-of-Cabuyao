<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MisdIntegrationService;
use App\Support\IenrollProfileLock;
use App\Support\NameParts;
use App\Support\NotificationPreferences;
use App\Support\SexOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        ///////////////////
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

        // Refresh iEnroll identity on login (best-effort; never block sign-in).
        $this->syncIenrollProfile($user);

        $user->update(['last_login_at' => now()]);
        $user->tokens()->delete();
        $token = $user->createToken('interntrack-session')->plainTextToken;

        audit_log($user->id, 'login', ['ip' => $request->ip()]);

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user->fresh()->load($this->userRelations())),
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

        $user->update([
            'password' => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);
        audit_log($user->id, 'change_password', []);

        return response()->json([
            'message' => 'Password updated successfully.',
            'user' => $this->formatUser($user->fresh()->load($this->userRelations())),
        ]);
    }

    /** POST /api/v1/auth/request-password-change */
    public function requestPasswordChange(Request $request)
    {
        $user = $request->user()->fresh(); // always fetch latest email from DB
        $token = \Illuminate\Support\Str::random(60);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Link must point to the FRONTEND app, not the backend API server
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $link = $frontendUrl . '/change-password-confirm?token=' . $token . '&email=' . urlencode($user->email);

        $displayName = $user->name ?: $user->username;

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Hello {$displayName},\n\nYou requested to change your password in INTERNTRACK. Click the link below to confirm and set your new password (valid for 60 minutes):\n\n{$link}\n\nIf you did not request this, please ignore this email.",
                function ($msg) use ($user) {
                    $msg->to($user->email)->subject('Confirm Password Change - INTERNTRACK');
                }
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Mail send failed for password change: " . $e->getMessage());
        }

        // In-app notification so the user can also click it from the bell
        \App\Models\Notification::notify(
            $user->id,
            'password_change_requested',
            'Password Change Confirmation Link',
            "We sent a confirmation link to {$user->email}. Click here to set your new password.",
            "/change-password-confirm?token={$token}&email=" . urlencode($user->email),
            ['token' => $token, 'email' => $user->email]
        );

        return response()->json([
            'message' => 'Password confirmation email sent. Please check your inbox (and system notifications).',
            'email'   => $user->email,
        ]);
    }

    /** POST /api/v1/auth/confirm-password-change */
    public function confirmPasswordChange(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#^()_+\-=\[\]{};\':"\\|,.<>\/?]/',
                'regex:/^\S*$/',
                'confirmed',
            ],
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record || !Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['The password reset link is invalid or expired.'],
            ]);
        }

        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'token' => ['The password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if (Hash::check($request->new_password, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password must be different from your current password.'],
            ]);
        }

        $lowerPass = strtolower($request->new_password);
        if ($user->first_name && strlen($user->first_name) >= 3 && str_contains($lowerPass, strtolower($user->first_name))) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password cannot contain your first name.'],
            ]);
        }
        if ($user->last_name && strlen($user->last_name) >= 3 && str_contains($lowerPass, strtolower($user->last_name))) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password cannot contain your last name.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        audit_log($user->id, 'confirm_password_change', []);

        return response()->json([
            'message' => 'Password changed successfully! You can now log in with your new password.',
            'user' => $this->formatUser($user->fresh()->load($this->userRelations())),
        ]);
    }

    /** GET /api/v1/auth/notification-preferences */
    public function notificationPreferences(Request $request)
    {
        $user = $request->user();
        $prefs = NotificationPreferences::mergeForUser($user->role, $user->notification_preferences);

        return response()->json([
            'preferences' => $prefs,
            'allowed_keys' => NotificationPreferences::allowedKeysForRole($user->role),
        ]);
    }

    /** PUT /api/v1/auth/notification-preferences */
    public function updateNotificationPreferences(Request $request)
    {
        $user = $request->user();
        $allowed = NotificationPreferences::allowedKeysForRole($user->role);

        $data = $request->validate([
            'preferences' => 'required|array',
        ]);

        $incoming = $data['preferences'];
        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $incoming)) {
                $filtered[$key] = (bool) $incoming[$key];
            }
        }

        $merged = NotificationPreferences::mergeForUser($user->role, array_merge(
            $user->notification_preferences ?? [],
            $filtered
        ));

        // Persist only allowed keys for this role
        $store = [];
        foreach ($allowed as $key) {
            $store[$key] = (bool) ($merged[$key] ?? true);
        }

        $user->update(['notification_preferences' => $store]);
        audit_log($user->id, 'update_notification_preferences', ['keys' => array_keys($store)]);

        $user->refresh()->load($this->userRelations());

        return response()->json([
            'message' => 'Notification preferences saved.',
            'preferences' => $store,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * PUT /api/v1/auth/profile — persist editable profile fields to the real profile tables.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user()->load(['studentProfile', 'facultyProfile', 'supervisorProfile']);

        $data = $request->validate([
            'first_name' => 'sometimes|nullable|string|max:100',
            'middle_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'suffix' => 'sometimes|nullable|string|max:30',
            'name' => 'sometimes|nullable|string|max:200',
            'email' => 'sometimes|nullable|email|max:255',
            'contact' => 'sometimes|nullable|string|max:40',
            'contact_number' => 'sometimes|nullable|string|max:40',
            'program' => 'sometimes|nullable|string|max:255',
            'position' => 'sometimes|nullable|string|max:255',
            'company' => 'sometimes|nullable|string|max:255',
            'sex' => SexOptions::validationRule(false),
        ]);

        // University accounts: identity is owned by iEnroll — reject any attempt to change it here.
        if (IenrollProfileLock::containsLockedFields($data, $user->role)) {
            return response()->json([
                'message' => 'Profile identity fields are managed by iEnroll and cannot be changed here.',
                'profile_editable' => false,
                'locked_source' => 'ienroll',
                'user' => $this->formatUser($user->load($this->userRelations())),
            ], 422);
        }

        // Sex is iEnroll-managed for all roles except industry supervisors.
        if (!SexOptions::isEditableRole($user->role)) {
            unset($data['sex']);
        }

        // Backward-compatible: single "name" field → first/last when parts not sent.
        if (!empty($data['name']) && empty($data['first_name']) && empty($data['last_name'])) {
            $parts = preg_split('/\s+/', trim($data['name']), 2);
            $data['first_name'] = $parts[0] ?? '';
            $data['last_name'] = $parts[1] ?? ($parts[0] ?? '');
        }

        $contact = $data['contact_number'] ?? $data['contact'] ?? null;

        if ($user->role === 'supervisor' && $user->supervisorProfile) {
            $payload = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'middle_name' => array_key_exists('middle_name', $data) ? ($data['middle_name'] ?: null) : null,
                'last_name' => $data['last_name'] ?? null,
                'suffix' => array_key_exists('suffix', $data) ? ($data['suffix'] ?: null) : null,
                'email' => $data['email'] ?? null,
                'contact_number' => $contact,
                'position' => $data['position'] ?? null,
                'sex' => array_key_exists('sex', $data) ? SexOptions::sanitize($data['sex']) : null,
            ], fn ($v) => $v !== null);

            // Allow clearing optional middle/suffix with empty string when keys present
            if (array_key_exists('middle_name', $data)) {
                $payload['middle_name'] = $data['middle_name'] !== '' ? $data['middle_name'] : null;
            }
            if (array_key_exists('suffix', $data)) {
                $payload['suffix'] = $data['suffix'] !== '' ? $data['suffix'] : null;
            }

            $user->supervisorProfile->fill($payload)->save();
        } else {
            return response()->json(['message' => 'No editable profile record found for this account.'], 422);
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

    private function resolveFacultyName(User $user): string
    {
        if ($user->activeInternship && $user->activeInternship->faculty) {
            $profile = $user->activeInternship->faculty->facultyProfile;
            if ($profile) {
                return trim("{$profile->first_name} {$profile->last_name}");
            }
        }

        $section = $user->studentProfile?->section;
        if ($section) {
            $assignment = \App\Models\FacultySectionAssignment::where('section', $section)
                ->with('faculty.facultyProfile')
                ->first();
            if ($assignment && $assignment->faculty && $assignment->faculty->facultyProfile) {
                $profile = $assignment->faculty->facultyProfile;
                return trim("{$profile->first_name} {$profile->last_name}");
            }
        }

        return 'Not Assigned';
    }

    private function formatUser(User $user): array
    {
        $profile = $user->studentProfile ?? $user->facultyProfile ?? $user->supervisorProfile;
        $firstName  = $profile?->first_name;
        $middleName = $profile?->middle_name;
        $lastName   = $profile?->last_name;
        $suffix     = $profile?->suffix ?? null;
        $name       = NameParts::display($firstName, $middleName, $lastName, $suffix);
        if ($name === '') {
            $name = $user->username;
        }
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
        $faculty = $this->resolveFacultyName($user);

        $avatarFirst = $firstName ?: ($name !== '' ? explode(' ', $name)[0] : 'U');
        $avatarLast  = $lastName ?: 'U';
        $avatar = strtoupper(substr($avatarFirst, 0, 1) . substr($avatarLast, 0, 1));

        $roleLabels = [
            'student'     => 'Student Account',
            'supervisor'  => 'Company Supervisor',
            'faculty'     => 'Faculty Supervisor',
            'coordinator' => 'Practicum Supervisor',
            'director'    => 'PALD Director',
            'admin'       => 'MISD Administrator',
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
            'admin'       => 'MISD Administrator · ' . $user->username,
        ];

        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'role'        => $user->role,
            'name'        => $name,
            'first_name'  => $firstName,
            'middle_name' => $middleName,
            'last_name'   => $lastName,
            'suffix'      => $suffix,
            'email'       => $email,
            'contact'     => $contact,
            'program'     => $program,
            'position'    => $position,
            'company'     => $company,
            'sex'         => $this->resolveSex($user),
            'sex_editable'=> SexOptions::isEditableRole($user->role),
            'profile_editable' => IenrollProfileLock::isProfileEditable($user->role),
            'locked_source'    => IenrollProfileLock::lockedSource($user->role),
            // Display-only iEnroll fields (never writable via PUT /auth/profile)
            'student_number'     => $user->studentProfile?->student_number,
            'section'            => $user->studentProfile?->section,
            'year_level'         => $user->studentProfile?->year_level,
            'college'            => $user->studentProfile?->college ?? $user->facultyProfile?->college,
            'employee_number'    => $user->facultyProfile?->employee_number,
            'employment_status'  => $user->facultyProfile?->employment_status,
            'avatar'      => $avatar,
            'avatarUrl'   => $this->resolveAvatarUrl($user->avatar_path),
            'subtitle'    => $subtitleMap[$user->role] ?? $user->username,
            'roleLabel'   => $roleLabels[$user->role] ?? ucfirst($user->role),
            'dashRoute'   => $roleRoutes[$user->role] ?? '/',
            'term'         => config('interntrack.current_term', 'AY 2025-2026, Sem 2'),
            'coordinator'  => $coord,
            'faculty'      => $faculty,
            'lastLoginAt'  => optional($user->last_login_at)?->toIso8601String(),
            'notificationPreferences' => NotificationPreferences::mergeForUser(
                $user->role,
                is_array($user->notification_preferences) ? $user->notification_preferences : []
            ),
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }

    /**
     * Best-effort refresh of iEnroll-owned identity on login.
     */
    private function syncIenrollProfile(User $user): void
    {
        if (!IenrollProfileLock::isIenrollRole($user->role)) {
            return;
        }

        try {
            if ($user->role === 'student') {
                $user->loadMissing('studentProfile');
                $this->misd->syncStudent($user);
            } else {
                $user->loadMissing('facultyProfile');
                $this->misd->syncFaculty($user);
            }
        } catch (\Throwable $e) {
            Log::warning('iEnroll login sync failed for [' . $user->username . ']: ' . $e->getMessage());
        }
    }

    /**
     * Resolve official sex for API responses.
     * Students/faculty staff: profile from iEnroll.
     * Supervisors: supervisor_profiles (manual).
     * Admin: users.sex fallback to faculty_profiles.
     */
    private function resolveSex(User $user): ?string
    {
        $sex = match ($user->role) {
            'student'    => $user->studentProfile?->sex,
            'supervisor' => $user->supervisorProfile?->sex,
            'faculty', 'coordinator', 'director' => $user->facultyProfile?->sex,
            'admin'      => $user->sex ?? $user->facultyProfile?->sex,
            default      => $user->sex,
        };

        return SexOptions::sanitize($sex);
    }

    /**
     * Build a publicly reachable avatar URL from the current API request host.
     * Prefer the API media route so avatars still load when the public/storage
     * symlink is broken (common with OneDrive-synced Windows projects).
     */
    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $filename = basename($normalized);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        // Served by PublicAvatarController — independent of public/storage.
        return $base.'/api/v1/media/avatars/'.$filename;
    }
}
