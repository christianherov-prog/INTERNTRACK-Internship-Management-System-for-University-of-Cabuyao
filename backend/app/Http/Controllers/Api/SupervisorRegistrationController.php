<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Internship;
use App\Models\InternshipPlacement;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Support\DepartmentScope;
use App\Support\InternshipProvisioning;
use App\Support\NameParts;
use App\Support\SexOptions;
use App\Support\SupervisorIds;
use App\Support\UniqueWrite;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupervisorRegistrationController extends Controller
{
    // ─── STUDENT: Generate an invite token + QR link ──────────────────────────

    public function generateInvite(Request $request)
    {
        $internship = InternshipProvisioning::resolveForStudent($request->user());

        if (! $internship) {
            return response()->json(['message' => 'No active internship found.'], 404);
        }

        try {
            $invite = DB::transaction(function () use ($request, $internship) {
                $locked = Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                if ($locked->supervisor_id) {
                    throw new \RuntimeException('A supervisor is already assigned to your internship.');
                }

                SupervisorInviteToken::where('internship_id', $locked->id)
                    ->whereIn('status', ['pending', 'pending_accept'])
                    ->update(['status' => 'expired']);

                return SupervisorInviteToken::create([
                    'internship_id' => $locked->id,
                    'student_id' => $request->user()->id,
                    'token' => Str::random(48),
                    'expires_at' => now()->addDays(7),
                    'status' => 'pending',
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $invite->token;

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $registerUrl = "{$frontendUrl}/register/supervisor?token={$token}";

        return response()->json([
            'message' => 'Invite generated successfully.',
            'token' => $token,
            'register_url' => $registerUrl,
            'expires_at' => $invite->expires_at->toDateTimeString(),
        ]);
    }

    // ─── STUDENT: Get current invite status ───────────────────────────────────

    public function inviteStatus(Request $request)
    {
        $requestedId = $request->header('X-Internship-Id') ?: $request->input('internship_id');
        $internship = InternshipProvisioning::resolveForStudent($request->user(), $requestedId);
        $internship?->load(['supervisor.supervisorProfile', 'currentPlacement']);

        if (! $internship) {
            return response()->json([
                'invite' => null,
                'has_supervisor' => false,
                'state' => 'none',
                'supervisor' => null,
            ]);
        }

        $invite = SupervisorInviteToken::where('internship_id', $internship->id)
            ->whereIn('status', ['pending', 'pending_accept', 'registered', 'approved', 'rejected', 'declined'])
            ->latest()
            ->first();

        if ($invite && $invite->status === 'pending' && $invite->isExpired()) {
            $invite->update(['status' => 'expired']);
            $invite = null;
        }

        // Assigned only when an approved HTE supervisor is on the current internship
        // (or current placement). Pending/registered invites must NOT count as assigned.
        $hasSupervisor = (bool) $internship->hasApprovedHteSupervisor();

        $state = 'none';
        if ($hasSupervisor) {
            $state = 'assigned';
        } elseif ($invite?->status === 'registered') {
            $state = 'pending_approval';
        } elseif ($invite?->status === 'pending_accept') {
            $state = 'awaiting_supervisor';
        } elseif ($invite?->status === 'pending') {
            $state = 'invite_pending';
        } elseif ($invite?->status === 'rejected' || $invite?->status === 'declined') {
            $state = $invite->status === 'declined' ? 'declined' : 'rejected';
        }

        $supervisor = null;
        if ($hasSupervisor && $internship->supervisor) {
            $p = $internship->supervisor->supervisorProfile;
            $supervisor = [
                'id' => $internship->supervisor->id,
                'username' => $internship->supervisor->username,
                'name' => $p ? trim("{$p->last_name}, {$p->first_name}") : $internship->supervisor->username,
                'email' => $p?->email ?? $internship->supervisor->email,
                'position' => $p?->position,
            ];
        }

        return response()->json([
            'invite' => $invite,
            'has_supervisor' => $hasSupervisor,
            'state' => $state,
            'supervisor' => $supervisor,
        ]);
    }

    // ─── PUBLIC: Validate a token (used by the registration page) ─────────────

    public function validateToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $invite = SupervisorInviteToken::where('token', $request->token)
            ->with(['student.studentProfile', 'internship.company'])
            ->first();

        if (! $invite) {
            return response()->json(['valid' => false, 'message' => 'Invalid invite link.'], 404);
        }

        if ($invite->isExpired()) {
            $invite->update(['status' => 'expired']);

            return response()->json(['valid' => false, 'message' => 'This invite link has expired. Please ask the student for a new one.'], 410);
        }

        if ($invite->status !== 'pending') {
            return response()->json(['valid' => false, 'message' => 'This invite has already been used.'], 409);
        }

        $studentProfile = $invite->student?->studentProfile;
        $internshipCompany = $invite->internship?->company;
        $companies = Company::where('moa_status', 'active')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        // Prefill / lock to the student's placement company when already assigned.
        if ($internshipCompany && ! $companies->contains('id', $internshipCompany->id)) {
            $companies->prepend($internshipCompany->only(['id', 'company_name']));
        }

        return response()->json([
            'valid' => true,
            'student_name' => $studentProfile
                ? trim("{$studentProfile->last_name}, {$studentProfile->first_name}")
                : $invite->student?->username,
            'program' => $studentProfile?->course_name ?? $studentProfile?->program ?? '—',
            'term' => $invite->internship?->term,
            'company_name' => $internshipCompany?->company_name,
            'prefill_company_id' => $internshipCompany?->id,
            'company_locked' => (bool) $internshipCompany?->id,
            'companies' => $companies->values(),
        ]);
    }

    // ─── PUBLIC: Supervisor self-registration ─────────────────────────────────

    public function register(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:30',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:30',
            'position' => 'required|string|max:255',
            'sex' => SexOptions::validationRule(true),
            'company_id' => 'required|exists:companies,id',
            'password' => 'required|string|min:8|confirmed',
            'acceptance_forms' => 'required|array|min:1',
            'acceptance_forms.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        return DB::transaction(function () use ($request) {
            $invite = SupervisorInviteToken::where('token', $request->token)->lockForUpdate()->first();

            if (! $invite || ! $invite->isUsable()) {
                return response()->json(['message' => 'Invalid or expired invite link.'], 422);
            }

            if ($invite->supervisor_user_id) {
                return response()->json(['message' => 'This invite has already been used.'], 409);
            }

            $existing = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($request->email))])
                ->lockForUpdate()
                ->first();

            $conflict = $this->existingEmailRegistrationConflict($existing);
            if ($conflict) {
                return response()->json([
                    'code' => $conflict['code'],
                    'message' => $conflict['message'],
                ], 409);
            }

            $reapplied = false;
            try {
                if ($existing && $this->supervisorMayReapply($existing)) {
                    $reapplied = true;
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update([
                        'email' => $request->email,
                        'password' => Hash::make($request->password),
                        'role' => 'supervisor',
                        'is_active' => false,
                    ]);
                    $user = $existing->fresh();
                } else {
                    $user = User::create([
                        'faculty_number' => null,
                        'email' => $request->email,
                        'password' => Hash::make($request->password),
                        'role' => 'supervisor',
                        'is_active' => false, // Requires faculty approval
                    ]);
                }
            } catch (QueryException $e) {
                if (UniqueWrite::isDuplicate($e)) {
                    return response()->json([
                        'code' => 'existing_account',
                        'message' => 'An account with this email already exists. Please sign in with your Supervisor ID instead of registering again.',
                    ], 409);
                }
                throw $e;
            }

            $supCode = SupervisorIds::ensureFor($user);

            SupervisorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name ?: null,
                    'last_name' => $request->last_name,
                    'suffix' => $request->suffix ?: null,
                    'email' => $request->email,
                    'contact_number' => $request->contact_number,
                    'sex' => SexOptions::sanitize($request->sex),
                    'position' => $request->position,
                    'company_id' => $request->company_id,
                ]
            );

            $forms = [];
            foreach ($request->file('acceptance_forms') as $file) {
                $forms[] = [
                    'path' => $file->store("internships/{$invite->internship_id}/supervisor-invites/{$invite->id}", 'local'),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }

            $invite->update([
                'status' => 'registered',
                'supervisor_user_id' => $user->id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name ?: null,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix ?: null,
                'email' => $request->email,
                'contact_number' => $request->contact_number,
                'position' => $request->position,
                'company_id' => $request->company_id,
                'fo29_file_path' => $forms[0]['path'] ?? null,
                'acceptance_form_paths' => $forms,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_remarks' => null,
            ]);

            $displayName = NameParts::display(
                $request->first_name,
                $request->middle_name,
                $request->last_name,
                $request->suffix
            );

            $this->notifyReviewersOfPendingRegistration(
                Internship::find($invite->internship_id),
                $reapplied
                    ? "{$displayName} resubmitted a supervisor registration after a previous rejection and is awaiting your approval."
                    : "{$displayName} has registered as a supervisor and is awaiting your approval.",
                $reapplied ? 'Supervisor Registration Resubmitted' : 'New Supervisor Registration'
            );

            return response()->json([
                'message' => $reapplied
                    ? 'Your previous registration was rejected. This new request has been recorded and is awaiting Faculty Supervisor approval.'
                    : 'Registration submitted successfully. Your account will be activated once the Faculty Supervisor approves it.',
                'username' => $supCode,
                'reapplied' => $reapplied,
            ], 201);
        });
    }

    // ─── FACULTY: List pending supervisor registrations ───────────────────────

    public function pendingList(Request $request)
    {
        $user = $request->user();

        $pendingQuery = SupervisorInviteToken::where('status', 'registered')
            ->with([
                'student.studentProfile.program.department',
                'student.studentProfile.department',
                'supervisor.supervisorProfile',
                'company',
                'internship',
            ])
            ->orderByDesc('updated_at');

        $this->constrainReviewableInvites($pendingQuery, $user);

        $invites = $pendingQuery->get();

        $historyQuery = SupervisorInviteToken::whereIn('status', ['approved', 'rejected'])
            ->with([
                'student.studentProfile.program.department',
                'student.studentProfile.department',
                'supervisor.supervisorProfile',
                'company',
                'reviewer.facultyProfile',
                'internship',
            ])
            ->orderByDesc('reviewed_at')
            ->limit(20);

        $this->constrainReviewableInvites($historyQuery, $user, includeHistory: true);

        $history = $historyQuery->get();

        return response()->json([
            'pending' => $invites->map(fn (SupervisorInviteToken $invite) => $this->serializeReviewInvite($invite))->values(),
            'history' => $history->map(fn (SupervisorInviteToken $invite) => $this->serializeReviewInvite($invite))->values(),
        ]);
    }

    // ─── FACULTY: Approve a supervisor registration ───────────────────────────

    public function approve(Request $request, int $id)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $invite = SupervisorInviteToken::where('status', 'registered')->findOrFail($id);
        $this->assertFacultyMayReview($request->user(), $invite);

        return DB::transaction(function () use ($request, $invite) {
            $lockedInvite = SupervisorInviteToken::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            if ($lockedInvite->status !== 'registered') {
                return response()->json(['message' => 'This registration has already been reviewed.'], 409);
            }

            $supervisorUser = $this->resolveSupervisorUser($lockedInvite);
            if (! $supervisorUser) {
                return response()->json([
                    'message' => 'This registration is missing a supervisor account. Ask the supervisor to register or sign in again.',
                ], 422);
            }

            $supervisorUser->update(['is_active' => true]);

            $internship = Internship::whereKey($lockedInvite->internship_id)->lockForUpdate()->firstOrFail();
            if ($internship->supervisor_id && (int) $internship->supervisor_id !== (int) $supervisorUser->id) {
                return response()->json(['message' => 'This student already has an assigned supervisor.'], 409);
            }

            $internshipPayload = [
                'supervisor_id' => $supervisorUser->id,
                'company_id' => $lockedInvite->company_id ?? $internship->company_id,
            ];
            if (in_array($internship->status, ['pending_placement', 'placed'], true)) {
                $internshipPayload['status'] = 'ongoing';
            }
            $internship->update($internshipPayload);

            $this->syncPlacementSupervisor($internship->fresh(), $supervisorUser->id, $lockedInvite->company_id);

            $lockedInvite->update([
                'status' => 'approved',
                'supervisor_user_id' => $supervisorUser->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_remarks' => $request->remarks,
            ]);
            $invite = $lockedInvite;

            // Notify the student
            Notification::notify(
                $invite->student_id,
                'supervisor_approved',
                'Supervisor Approved',
                "Your supervisor {$invite->last_name}, {$invite->first_name} has been approved and assigned to your internship.",
                '/student/attendance'
            );

            // Notify the supervisor
            Notification::notify(
                $supervisorUser->id,
                'account_activated',
                'Account Activated',
                "Your InternTrack account has been approved. You can now log in with your ID: {$supervisorUser->username}",
                '/supervisor/dashboard'
            );

            audit_log($request->user()->id, 'approve_supervisor', [
                'invite_id' => $invite->id,
                'supervisor_id' => $supervisorUser->id,
            ]);

            return response()->json([
                'message' => "Supervisor {$invite->last_name}, {$invite->first_name} approved and assigned successfully.",
            ]);
        });
    }

    // ─── FACULTY: Reject a supervisor registration ────────────────────────────

    public function reject(Request $request, int $id)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        $invite = SupervisorInviteToken::where('status', 'registered')->findOrFail($id);
        $this->assertFacultyMayReview($request->user(), $invite);

        $invite->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_remarks' => $request->remarks,
        ]);

        // Notify the student
        Notification::notify(
            $invite->student_id,
            'supervisor_rejected',
            'Supervisor Registration Rejected',
            "The supervisor registration for {$invite->last_name}, {$invite->first_name} was rejected: {$request->remarks}",
            '/student/attendance'
        );

        audit_log($request->user()->id, 'reject_supervisor', ['invite_id' => $invite->id]);

        return response()->json([
            'message' => 'Supervisor registration rejected.',
        ]);
    }

    /**
     * Faculty review their own advisees. Coordinators review any registration
     * in their college — assigning a section faculty must not hide the queue.
     */
    private function assertFacultyMayReview($user, SupervisorInviteToken $invite): void
    {
        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            abort(403, 'Only faculty supervisors may approve supervisor registrations.');
        }

        $internship = Internship::find($invite->internship_id);
        if (! $internship) {
            abort(404, 'Internship not found for this registration.');
        }

        DepartmentScope::abortUnlessInternshipInDepartment($user, $internship);

        if ($user->role === 'coordinator') {
            return;
        }

        if ($internship->faculty_id !== null && (int) $internship->faculty_id !== (int) $user->id) {
            abort(403, 'You may only review supervisor registrations for your assigned students.');
        }
    }

    private function constrainReviewableInvites($query, $user, bool $includeHistory = false): void
    {
        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            return;
        }

        $query->whereHas('internship', function ($q) use ($user) {
            $q->inDepartment();
            if ($user->role === 'faculty') {
                $q->where(function ($sub) use ($user) {
                    $sub->where('faculty_id', $user->id)
                        ->orWhereNull('faculty_id');
                });
            }
        });

        if ($includeHistory && $user->role === 'faculty') {
            $query->where(function ($q) use ($user) {
                $q->where('reviewed_by', $user->id)
                    ->orWhereHas('internship', fn ($iq) => $iq->where('faculty_id', $user->id));
            });
        }
    }

    private function resolveSupervisorUser(SupervisorInviteToken $invite): ?User
    {
        if ($invite->supervisor_user_id) {
            $user = User::find($invite->supervisor_user_id);
            if ($user) {
                return $user;
            }
        }

        $email = mb_strtolower(trim((string) $invite->email));
        if ($email === '') {
            return null;
        }

        return User::where('role', 'supervisor')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function notifyReviewersOfPendingRegistration(?Internship $internship, string $body, string $title): void
    {
        $ids = collect();
        if ($internship?->faculty_id) {
            $ids->push((int) $internship->faculty_id);
        }

        $internship?->loadMissing('student.studentProfile');
        $ids = $ids->merge(DepartmentScope::coordinatorIdsForStudent($internship?->student));

        foreach ($ids->unique()->filter() as $userId) {
            Notification::notify(
                (int) $userId,
                'supervisor_registration',
                $title,
                $body,
                '/faculty/supervisor-approvals'
            );
        }
    }

    /** POST /api/v1/supervisor/invites/bind — claim a pending invite after login */
    public function bindInvite(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $user = $request->user();

        if ($user->role !== 'supervisor') {
            return response()->json(['message' => 'This invite is for industry supervisors only.'], 403);
        }

        $invite = SupervisorInviteToken::where('token', $request->token)
            ->with(['internship', 'student.studentProfile'])
            ->first();

        if (! $invite || $invite->isExpired()) {
            if ($invite && $invite->status === 'pending') {
                $invite->update(['status' => 'expired']);
            }

            return response()->json(['message' => 'Invalid or expired invite link.'], 422);
        }

        if ($invite->internship?->supervisor_id) {
            return response()->json(['message' => 'This student already has an assigned supervisor.'], 409);
        }

        if (in_array($invite->status, ['pending_accept', 'approved'], true)
            && (int) $invite->supervisor_user_id === (int) $user->id) {
            return response()->json([
                'message' => 'Invite already linked to your account.',
                'invite' => $this->formatPendingInvite($invite->fresh(['internship.company', 'student.studentProfile'])),
            ]);
        }

        if ($invite->status !== 'pending') {
            return response()->json(['message' => 'This invite has already been used.'], 409);
        }

        $profile = $user->supervisorProfile;
        $invite->update([
            'status' => 'pending_accept',
            'supervisor_user_id' => $user->id,
            'first_name' => $profile?->first_name,
            'middle_name' => $profile?->middle_name,
            'last_name' => $profile?->last_name,
            'suffix' => $profile?->suffix,
            'email' => $profile?->email ?? $user->email,
            'contact_number' => $profile?->contact_number,
            'position' => $profile?->position,
            'company_id' => $invite->internship?->company_id ?? $profile?->company_id,
        ]);

        return response()->json([
            'message' => 'Please accept or decline this student invitation.',
            'invite' => $this->formatPendingInvite($invite->fresh(['internship.company', 'student.studentProfile'])),
        ]);
    }

    /** GET /api/v1/supervisor/invites/pending */
    public function pendingInvites(Request $request)
    {
        $invites = SupervisorInviteToken::where('supervisor_user_id', $request->user()->id)
            ->where('status', 'pending_accept')
            ->with(['internship.company', 'student.studentProfile'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($invite) => $this->formatPendingInvite($invite));

        return response()->json(['invites' => $invites->values()]);
    }

    /** POST /api/v1/supervisor/invites/{id}/accept */
    public function acceptInvite(Request $request, int $id)
    {
        $invite = SupervisorInviteToken::where('status', 'pending_accept')->findOrFail($id);
        $this->assertInviteOwner($request->user(), $invite);

        return DB::transaction(function () use ($request, $invite) {
            $internship = Internship::whereKey($invite->internship_id)->lockForUpdate()->firstOrFail();

            if ($internship->supervisor_id && (int) $internship->supervisor_id !== (int) $request->user()->id) {
                return response()->json(['message' => 'This student already has an assigned supervisor.'], 409);
            }

            $profile = $request->user()->supervisorProfile;
            $name = NameParts::display(
                $profile?->first_name,
                $profile?->middle_name,
                $profile?->last_name,
                $profile?->suffix
            ) ?: 'Supervisor';

            $invite->update([
                'status' => 'registered',
                'supervisor_user_id' => $request->user()->id,
                'company_id' => $invite->company_id ?? $internship->company_id ?? $profile?->company_id,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_remarks' => null,
            ]);

            Notification::notify(
                $invite->student_id,
                'supervisor_registration',
                'Supervisor Awaiting Faculty Approval',
                "{$name} accepted your invite and is waiting for Faculty Supervisor approval.",
                '/student/attendance'
            );

            $this->notifyReviewersOfPendingRegistration(
                $internship,
                "{$name} accepted a student invitation and is awaiting your approval.",
                'Supervisor Invitation Pending Approval'
            );

            return response()->json([
                'message' => 'Invitation accepted. The intern will be linked after Faculty Supervisor approval.',
            ]);
        });
    }

    /** POST /api/v1/supervisor/invites/{id}/decline */
    public function declineInvite(Request $request, int $id)
    {
        $invite = SupervisorInviteToken::where('status', 'pending_accept')->findOrFail($id);
        $this->assertInviteOwner($request->user(), $invite);

        $invite->update([
            'status' => 'declined',
            'reviewed_at' => now(),
            'review_remarks' => $request->input('reason'),
        ]);

        Notification::notify(
            $invite->student_id,
            'supervisor_rejected',
            'Supervisor Declined Invite',
            'The invited supervisor declined this internship. You can generate a new invite.',
            '/student/attendance'
        );

        return response()->json(['message' => 'Invitation declined. The student can send a new invite.']);
    }

    /**
     * Block duplicate registration except when the email belongs to an inactive
     * supervisor whose previous invite was rejected (eligible to re-apply).
     *
     * @return array{code:string,message:string}|null
     */
    private function existingEmailRegistrationConflict(?User $existing): ?array
    {
        if (! $existing) {
            return null;
        }

        if ($this->supervisorMayReapply($existing)) {
            return null;
        }

        if ($existing->role === 'supervisor'
            && SupervisorInviteToken::where('supervisor_user_id', $existing->id)
                ->where('status', 'registered')
                ->exists()) {
            return [
                'code' => 'pending_approval',
                'message' => 'A registration with this email is already awaiting faculty approval. Please wait for the review outcome.',
            ];
        }

        return [
            'code' => 'existing_account',
            'message' => 'An account with this email already exists. Please sign in with your Supervisor ID instead of registering again.',
        ];
    }

    /**
     * Rejected, never-approved supervisors may reuse the same email on a new student invite.
     */
    private function supervisorMayReapply(User $user): bool
    {
        if ($user->role !== 'supervisor') {
            return false;
        }

        if ($user->is_active && ! $user->trashed()) {
            return false;
        }

        if (Internship::where('supervisor_id', $user->id)->exists()) {
            return false;
        }

        if (SupervisorInviteToken::where('supervisor_user_id', $user->id)
            ->where('status', 'approved')
            ->exists()) {
            return false;
        }

        if (SupervisorInviteToken::where('supervisor_user_id', $user->id)
            ->where('status', 'registered')
            ->exists()) {
            return false;
        }

        return SupervisorInviteToken::where('supervisor_user_id', $user->id)
            ->where('status', 'rejected')
            ->exists()
            || SupervisorInviteToken::whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $user->email))])
                ->where('status', 'rejected')
                ->exists();
    }

    private function assertInviteOwner($user, SupervisorInviteToken $invite): void
    {
        if ((int) $invite->supervisor_user_id !== (int) $user->id) {
            abort(403, 'You can only respond to invitations sent to your account.');
        }
    }

    private function formatPendingInvite(SupervisorInviteToken $invite): array
    {
        $studentProfile = $invite->student?->studentProfile;

        return [
            'id' => $invite->id,
            'status' => $invite->status,
            'student_name' => $studentProfile
                ? trim("{$studentProfile->last_name}, {$studentProfile->first_name}")
                : $invite->student?->username,
            'term' => $invite->internship?->term,
            'company_name' => $invite->internship?->company?->company_name,
            'expires_at' => optional($invite->expires_at)?->toDateTimeString(),
        ];
    }

    /**
     * Keep the current placement row aligned with internship.supervisor_id.
     */
    private function syncPlacementSupervisor(Internship $internship, int $supervisorUserId, mixed $companyId = null): void
    {
        if (! Schema::hasTable('internship_placements')) {
            return;
        }

        $query = InternshipPlacement::query()
            ->where('internship_id', $internship->id)
            ->lockForUpdate();

        $placement = $internship->current_placement_id
            ? (clone $query)->whereKey($internship->current_placement_id)->first()
            : null;

        if (! $placement) {
            $placement = (clone $query)
                ->where('status', 'active')
                ->orderBy('id')
                ->first()
                ?: (clone $query)->orderBy('id')->first();
        }

        if (! $placement) {
            return;
        }

        $payload = ['supervisor_id' => $supervisorUserId];
        if ($companyId) {
            $payload['company_id'] = $companyId;
        }
        $placement->update($payload);
    }

    /**
     * Faculty review payload: structured student/program fields without raw
     * invite tokens or private storage columns.
     */
    private function serializeReviewInvite(SupervisorInviteToken $invite): array
    {
        $invite->makeHidden(['token', 'fo29_file_path', 'acceptance_form_paths']);

        $profile = $invite->student?->studentProfile;
        $program = $profile?->program;
        $department = $profile?->department ?? $program?->department;
        $studentName = $profile
            ? (NameParts::fromProfile($profile) ?: trim($profile->first_name.' '.$profile->last_name))
            : ($invite->student?->username);

        $payload = $invite->toArray();
        $payload['inviting_student_name'] = $studentName ?: null;
        $payload['student_program'] = $program?->code ?: $program?->name;
        $payload['student_department'] = $department?->code ?: $department?->name;
        $payload['status_label'] = match ($invite->status) {
            'registered' => 'Pending Faculty Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => $invite->status ? ucwords(str_replace('_', ' ', $invite->status)) : null,
        };

        return $payload;
    }
}
