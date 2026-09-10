<?php

namespace App\Services;

use App\Mail\PasswordChangeMail;
use App\Models\Notification;
use App\Models\User;
use App\Support\IenrollProfileLock;
use App\Support\NotificationPreferences;
use App\Support\SexOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * AuthService
 *
 * Encapsulates all authentication and account-management business logic.
 * The controller delegates to this service and only handles HTTP concerns.
 */
class AuthService
{
    /** Eager-loads needed to build a complete UserResource payload. */
    public const USER_RELATIONS = [
        'studentProfile.program.department',
        'studentProfile.department',
        'facultyProfile.department',
        'supervisorProfile.company',
        'activeInternship.company',
        'activeInternship.coordinator.facultyProfile.department',
        'activeInternship.faculty.facultyProfile.department',
    ];

    public function __construct(
        protected MisdIntegrationService $misd,
    ) {}

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * Authenticate a user: provision from MISD if needed, check password,
     * verify the account is active, rotate token, and return it.
     *
     * @throws ValidationException
     */
    public function login(string $username, string $password, string $ip): array
    {
        $raw = trim($username);
        $upper = strtoupper($raw);
        $looksLikeEmail = filter_var($raw, FILTER_VALIDATE_EMAIL) !== false;

        $user = User::query()
            ->with(['facultyProfile', 'studentProfile'])
            ->where(function ($q) use ($raw, $upper, $looksLikeEmail) {
                $q->where('student_number', $upper)
                    ->orWhere('faculty_number', $upper)
                    ->orWhereHas('facultyProfile', fn ($p) => $p->where('faculty_number', $upper))
                    ->orWhereHas('studentProfile', fn ($p) => $p->where('student_number', $upper));
                if ($looksLikeEmail) {
                    $q->orWhereRaw('LOWER(email) = ?', [strtolower($raw)]);
                }
            })
            ->first();

        if ($user && blank($user->faculty_number) && filled($user->facultyProfile?->faculty_number)) {
            $user->forceFill(['faculty_number' => $user->facultyProfile->faculty_number])->save();
        }

        if ($user && blank($user->student_number) && filled($user->studentProfile?->student_number)) {
            $user->forceFill(['student_number' => $user->studentProfile->student_number])->save();
        }

        if (app()->runningUnitTests()) {
            Log::info('AuthService Login Dump: '.json_encode($user));
            Log::info('Username queried: '.$raw);
        }

        // Auto-provision from iEnroll only for campus IDs — never for email logins.
        if (! $user) {
            if ($looksLikeEmail) {
                throw ValidationException::withMessages([
                    'username' => ['Invalid credentials. Please check your ID and password.'],
                ]);
            }

            $user = $this->misd->provision($upper, $password);
            if (! $user) {
                throw ValidationException::withMessages([
                    'username' => ['Invalid credentials. Please check your ID and password.'],
                ]);
            }
        }

        if (! $this->passwordMatches($user, $password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials. Please check your ID and password.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account has been deactivated. Please contact your coordinator.'],
            ]);
        }

        // Best-effort iEnroll sync — never blocks sign-in.
        $this->syncIenrollProfile($user);

        // Backfill missing coordinator_id on existing internships (runs at login only, not on every /me).
        $this->backfillCoordinatorId($user);

        $user->update(['last_login_at' => now()]);

        // Single active session: revoke old tokens, issue a fresh one.
        $user->tokens()->delete();
        $token = $user->createToken('interntrack-session')->plainTextToken;

        audit_log($user->id, 'login', ['ip' => $ip]);

        return [
            'token' => $token,
            'user'  => $user->fresh()->load(self::USER_RELATIONS),
        ];
    }

    /**
     * Seeded campus accounts historically used interntrack123 while config
     * default is InternTrack123!. On local provision, accept either and
     * rehash to the canonical default.
     */
    private function passwordMatches(User $user, string $password): bool
    {
        if (Hash::check($password, $user->password)) {
            return true;
        }

        if (! config('interntrack.allow_default_password_provision')) {
            return false;
        }

        $canonical = (string) config('interntrack.default_password');
        $legacy = 'interntrack123';
        $typedDefault = in_array($password, [$canonical, $legacy], true);
        $storedDefault = Hash::check($canonical, $user->password) || Hash::check($legacy, $user->password);

        if ($typedDefault && $storedDefault) {
            if (! Hash::check($canonical, $user->password)) {
                $user->forceFill(['password' => Hash::make($canonical)])->save();
            }

            return true;
        }

        return false;
    }

    // ─── Password Management ──────────────────────────────────────────────────

    /**
     * Change password for authenticated users who know their current password.
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password'            => Hash::make($newPassword),
            'must_change_password'=> false,
        ]);

        audit_log($user->id, 'change_password', []);

        return $user->fresh()->load(self::USER_RELATIONS);
    }

    /**
     * Generate a time-limited password-reset token, persist it, then dispatch
     * a confirmation email and an in-app notification.
     */
    public function requestPasswordChange(User $user): void
    {
        $user  = $user->fresh();
        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $link        = $frontendUrl . '/change-password-confirm?token=' . $token . '&email=' . urlencode($user->email);

        // Send email via proper Mailable (testable, queueable).
        try {
            Mail::send(new PasswordChangeMail($user, $link));
        } catch (\Exception $e) {
            Log::warning('Mail send failed for password change: ' . $e->getMessage());
        }

        // In-app notification fallback.
        Notification::notify(
            $user->id,
            'password_change_requested',
            'Password Change Confirmation Link',
            "We sent a confirmation link to {$user->email}. Click here to set your new password.",
            '/change-password-confirm?token=' . $token . '&email=' . urlencode($user->email),
            ['token' => $token, 'email' => $user->email]
        );
    }

    /**
     * Handle public "forgot password" request by identifier (Student Number, Employee ID, or Email).
     * Dispatches password reset token and email if user exists.
     * Always returns consistent success response to prevent user enumeration.
     */
    public function forgotPassword(string $identifier): array
    {
        $raw = trim($identifier);
        $upper = strtoupper($raw);

        // Find user by student_number, faculty_number, or email
        $user = User::where('student_number', $upper)
            ->orWhere('faculty_number', $upper)
            ->orWhere('email', $raw)
            ->first();

        // If not found in local DB and mock MISD is active, attempt provisioning check
        if (!$user && config('interntrack.misd_use_mock', true)) {
            try {
                $role = MisdIntegrationService::detectRole($upper);
                if ($role) {
                    $defaultPw = config('interntrack.default_password', 'interntrack123');
                    $user = $this->misd->provision($upper, $defaultPw);
                }
            } catch (\Throwable $e) {
                Log::info('Forgot password MISD fallback lookup error: ' . $e->getMessage());
            }
        }

        $debugResetUrl = null;

        if ($user && $user->is_active) {
            // Ensure user has an email
            if (empty($user->email)) {
                $identifierKey = $user->student_number ?: $user->faculty_number ?: ('user_' . $user->id);
                $user->update(['email' => strtolower(str_replace('-', '_', $identifierKey)) . '@pnc.edu.ph']);
                $user->refresh();
            }

            $token = Str::random(60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            $link = $frontendUrl . '/change-password-confirm?token=' . $token . '&email=' . urlencode($user->email);
            $debugResetUrl = $link;

            try {
                Mail::send(new PasswordChangeMail($user, $link));
            } catch (\Exception $e) {
                Log::warning('Mail send failed for forgot password: ' . $e->getMessage());
            }

            Notification::notify(
                $user->id,
                'password_change_requested',
                'Password Reset Requested',
                "A password reset request was initiated for your account ({$user->email}).",
                '/change-password-confirm?token=' . $token . '&email=' . urlencode($user->email),
                ['token' => $token, 'email' => $user->email]
            );

            audit_log($user->id, 'forgot_password_requested', ['identifier' => $raw]);
        }

        $result = [
            'success' => true,
            'message' => 'If an account exists with the provided ID or email, password reset instructions have been sent to the registered email address.',
        ];

        if (app()->environment('local') && $debugResetUrl) {
            $result['debug_reset_url'] = $debugResetUrl;
        }

        return $result;
    }

    /**
     * Validate a password-reset token, apply business rules, and update the password.
     *
     * @throws ValidationException
     */
    public function confirmPasswordChange(string $email, string $token, string $newPassword): User
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['The password reset link is invalid or expired.'],
            ]);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'token' => ['The password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $email)->firstOrFail();

        $this->assertPasswordNotReused($user, $newPassword);
        $this->assertPasswordNotContainsName($user, $newPassword);

        $user->update([
            'password'            => Hash::make($newPassword),
            'must_change_password'=> false,
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        audit_log($user->id, 'confirm_password_change', []);

        return $user->fresh()->load(self::USER_RELATIONS);
    }

    // ─── Avatar ───────────────────────────────────────────────────────────────

    /**
     * Replace the user's avatar, cleaning up the old file.
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = Storage::disk('public')->putFile('avatars', $file);
        $user->forceFill(['avatar_path' => $path])->save();

        audit_log($user->id, 'update_avatar', []);

        return $user->refresh()->load(self::USER_RELATIONS);
    }

    // ─── Profile ──────────────────────────────────────────────────────────────

    /**
     * Update the editable fields on the user's profile table.
     * Only the industry supervisor role can freely edit their profile.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->load(['studentProfile', 'facultyProfile', 'supervisorProfile']);

        $contactProvided = array_key_exists('contact', $data) || array_key_exists('contact_number', $data);
        $data = IenrollProfileLock::stripLocked($data, $user->role);

        // Sex is iEnroll-managed for non-supervisor roles.
        if (!SexOptions::isEditableRole($user->role)) {
            unset($data['sex']);
        }

        // Backward-compatible: single "name" → first/last.
        if (!empty($data['name']) && empty($data['first_name']) && empty($data['last_name'])) {
            $parts            = preg_split('/\s+/', trim($data['name']), 2);
            $data['first_name'] = $parts[0] ?? '';
            $data['last_name']  = $parts[1] ?? ($parts[0] ?? '');
        }

        $contact = $data['contact_number'] ?? $data['contact'] ?? null;

        if (IenrollProfileLock::isIenrollRole($user->role)) {
            if (! $contactProvided) {
                abort(403, 'Profile identity fields are managed by iEnroll and cannot be changed here.');
            }

            $normalizedContact = preg_replace('/[\s-]/', '', (string) $contact);
            if ($normalizedContact !== '' && ! preg_match('/^(\+63|0)9\d{9}$/', $normalizedContact)) {
                abort(422, 'Enter a valid Philippine mobile number (e.g. 09XXXXXXXXX).');
            }

            $contactValue = $normalizedContact === '' ? null : $normalizedContact;

            if ($user->studentProfile) {
                $user->studentProfile->update(['contact_number' => $contactValue]);
            } elseif ($user->facultyProfile) {
                $user->facultyProfile->update(['contact_number' => $contactValue]);
            } else {
                abort(422, 'No editable profile record found for this account.');
            }

            audit_log($user->id, 'update_profile', ['fields' => ['contact_number']]);

            return $user->refresh()->load(self::USER_RELATIONS);
        }

        if ($user->role === 'supervisor' && $user->supervisorProfile) {
            $payload = array_filter([
                'first_name'     => $data['first_name'] ?? null,
                'middle_name'    => array_key_exists('middle_name', $data) ? ($data['middle_name'] ?: null) : null,
                'last_name'      => $data['last_name'] ?? null,
                'suffix'         => array_key_exists('suffix', $data) ? ($data['suffix'] ?: null) : null,
                'email'          => $data['email'] ?? null,
                'contact_number' => $contact,
                'position'       => $data['position'] ?? null,
                'sex'            => array_key_exists('sex', $data) ? SexOptions::sanitize($data['sex']) : null,
                'company_id'     => array_key_exists('company_id', $data) ? $data['company_id'] : null,
            ], fn ($v) => $v !== null);

            // Allow explicitly clearing optional fields.
            if (array_key_exists('middle_name', $data)) {
                $payload['middle_name'] = $data['middle_name'] !== '' ? $data['middle_name'] : null;
            }
            if (array_key_exists('suffix', $data)) {
                $payload['suffix'] = $data['suffix'] !== '' ? $data['suffix'] : null;
            }

            $user->supervisorProfile->fill($payload)->save();
        } else {
            abort(422, 'No editable profile record found for this account.');
        }

        if (!empty($data['email'])) {
            $user->forceFill(['email' => $data['email']])->save();
        }

        audit_log($user->id, 'update_profile', []);

        return $user->refresh()->load(self::USER_RELATIONS);
    }

    // ─── Notification Preferences ─────────────────────────────────────────────

    /**
     * Merge, filter, and persist notification preferences for the authenticated user.
     */
    public function updateNotificationPreferences(User $user, array $incoming): array
    {
        $allowed  = NotificationPreferences::allowedKeysForRole($user->role);

        // Only accept keys that are valid for this role.
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

        $store = [];
        foreach ($allowed as $key) {
            $store[$key] = (bool) ($merged[$key] ?? true);
        }

        $user->update(['notification_preferences' => $store]);
        audit_log($user->id, 'update_notification_preferences', ['keys' => array_keys($store)]);

        return $store;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Best-effort iEnroll identity refresh. Never throws — runs on login only.
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
            Log::warning("iEnroll login sync failed for [{$user->username}]: " . $e->getMessage());
        }
    }

    /**
     * Backfill missing coordinator_id on internships at login time.
     * Moved out of GET /auth/user to eliminate the side-effect on read operations.
     */
    private function backfillCoordinatorId(User $user): void
    {
        if ($user->role !== 'student') {
            return;
        }

        $internship = $user->activeInternship;
        if (! $internship) {
            return;
        }

        $deptCoordId = \App\Support\DepartmentScope::coordinatorIdForStudent(
            $user,
            $internship->coordinator_id ? (int) $internship->coordinator_id : null
        );

        if ($internship->coordinator_id) {
            $current = User::query()->find($internship->coordinator_id);
            if ($current && \App\Support\DepartmentScope::facultyMatchesStudent($current, $user)) {
                return;
            }

            $internship->forceFill(['coordinator_id' => $deptCoordId])->saveQuietly();

            return;
        }

        if ($deptCoordId) {
            $internship->forceFill(['coordinator_id' => $deptCoordId])->saveQuietly();
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertPasswordNotReused(User $user, string $newPassword): void
    {
        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password must be different from your current password.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertPasswordNotContainsName(User $user, string $newPassword): void
    {
        $lower = strtolower($newPassword);

        if ($user->first_name && strlen($user->first_name) >= 3 && str_contains($lower, strtolower($user->first_name))) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password cannot contain your first name.'],
            ]);
        }

        if ($user->last_name && strlen($user->last_name) >= 3 && str_contains($lower, strtolower($user->last_name))) {
            throw ValidationException::withMessages([
                'new_password' => ['The new password cannot contain your last name.'],
            ]);
        }
    }
}
