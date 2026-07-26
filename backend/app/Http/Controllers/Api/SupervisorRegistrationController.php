<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupervisorRegistrationController extends Controller
{
    // ─── STUDENT: Generate an invite token + QR link ──────────────────────────

    public function generateInvite(Request $request)
    {
        $internship = $request->user()
            ->activeInternship()
            ->first();

        if (!$internship) {
            return response()->json(['message' => 'No active internship found.'], 404);
        }

        if ($internship->supervisor_id) {
            return response()->json(['message' => 'A supervisor is already assigned to your internship.'], 422);
        }

        // Expire any previous pending tokens for this internship
        SupervisorInviteToken::where('internship_id', $internship->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $token = Str::random(48);

        $invite = SupervisorInviteToken::create([
            'internship_id' => $internship->id,
            'student_id'    => $request->user()->id,
            'token'         => $token,
            'expires_at'    => now()->addDays(7),
            'status'        => 'pending',
        ]);

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $registerUrl = "{$frontendUrl}/register/supervisor?token={$token}";

        return response()->json([
            'message'      => 'Invite generated successfully.',
            'token'        => $token,
            'register_url' => $registerUrl,
            'expires_at'   => $invite->expires_at->toDateTimeString(),
        ]);
    }

    // ─── STUDENT: Get current invite status ───────────────────────────────────

    public function inviteStatus(Request $request)
    {
        $internship = $request->user()->activeInternship()
            ->with(['supervisor.supervisorProfile'])
            ->first();

        if (!$internship) {
            return response()->json([
                'invite' => null,
                'has_supervisor' => false,
                'state' => 'none',
                'supervisor' => null,
            ]);
        }

        $invite = SupervisorInviteToken::where('internship_id', $internship->id)
            ->whereIn('status', ['pending', 'registered', 'approved', 'rejected'])
            ->latest()
            ->first();

        if ($invite && $invite->status === 'pending' && $invite->isExpired()) {
            $invite->update(['status' => 'expired']);
            $invite = null;
        }

        // Assigned only when internship.supervisor_id is set (faculty approved / placed).
        // Pending/registered invites must NOT be treated as "already assigned".
        $hasSupervisor = (bool) $internship->supervisor_id;

        $state = 'none';
        if ($hasSupervisor) {
            $state = 'assigned';
        } elseif ($invite?->status === 'registered') {
            $state = 'pending_approval';
        } elseif ($invite?->status === 'pending') {
            $state = 'invite_pending';
        } elseif ($invite?->status === 'rejected') {
            $state = 'rejected';
        }

        $supervisor = null;
        if ($hasSupervisor && $internship->supervisor) {
            $p = $internship->supervisor->supervisorProfile;
            $supervisor = [
                'id' => $internship->supervisor->id,
                'username' => $internship->supervisor->username,
                'name' => $p ? trim("{$p->first_name} {$p->last_name}") : $internship->supervisor->username,
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

        if (!$invite) {
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
        if ($internshipCompany && !$companies->contains('id', $internshipCompany->id)) {
            $companies->prepend($internshipCompany->only(['id', 'company_name']));
        }

        return response()->json([
            'valid'              => true,
            'student_name'       => $studentProfile
                ? trim("{$studentProfile->first_name} {$studentProfile->last_name}")
                : $invite->student?->username,
            'program'            => $studentProfile?->course_name ?? $studentProfile?->program ?? '—',
            'term'               => $invite->internship?->term,
            'company_name'       => $internshipCompany?->company_name,
            'prefill_company_id' => $internshipCompany?->id,
            'company_locked'     => (bool) $internshipCompany?->id,
            'companies'          => $companies->values(),
        ]);
    }

    // ─── PUBLIC: Supervisor self-registration ─────────────────────────────────

    public function register(Request $request)
    {
        $request->validate([
            'token'          => 'required|string',
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:30',
            'email'          => 'required|email|max:255',
            'contact_number' => 'required|string|max:30',
            'position'       => 'required|string|max:255',
            'sex'            => \App\Support\SexOptions::validationRule(true),
            'company_id'     => 'required|exists:companies,id',
            'password'       => 'required|string|min:8|confirmed',
        ]);

        $invite = SupervisorInviteToken::where('token', $request->token)->first();

        if (!$invite || !$invite->isUsable()) {
            return response()->json(['message' => 'Invalid or expired invite link.'], 422);
        }

        // Check for existing account with same email
        $existing = User::where('email', $request->email)->first();
        if ($existing) {
            return response()->json([
                'message' => 'An account with this email already exists. Please contact the coordinator.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $invite) {
            $nextId  = (User::where('role', 'supervisor')->count()) + 1;
            $supCode = 'SUP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            // Prevent collision
            while (User::where('username', $supCode)->exists()) {
                $nextId++;
                $supCode = 'SUP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            $user = User::create([
                'username'  => $supCode,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'role'      => 'supervisor',
                'is_active' => false, // Requires coordinator approval
            ]);

            SupervisorProfile::create([
                'user_id'        => $user->id,
                'first_name'     => $request->first_name,
                'middle_name'    => $request->middle_name ?: null,
                'last_name'      => $request->last_name,
                'suffix'         => $request->suffix ?: null,
                'email'          => $request->email,
                'contact_number' => $request->contact_number,
                'sex'            => \App\Support\SexOptions::sanitize($request->sex),
                'position'       => $request->position,
            ]);

            $invite->update([
                'status'             => 'registered',
                'supervisor_user_id' => $user->id,
                'first_name'         => $request->first_name,
                'middle_name'        => $request->middle_name ?: null,
                'last_name'          => $request->last_name,
                'suffix'             => $request->suffix ?: null,
                'email'              => $request->email,
                'contact_number'     => $request->contact_number,
                'position'           => $request->position,
                'company_id'         => $request->company_id,
            ]);

            $displayName = \App\Support\NameParts::display(
                $request->first_name,
                $request->middle_name,
                $request->last_name,
                $request->suffix
            );

            // Notify assigned faculty (or all faculty if not yet assigned)
            $internship = Internship::find($invite->internship_id);
            $facultyQuery = User::where('role', 'faculty')->where('is_active', true);
            if ($internship?->faculty_id) {
                $facultyQuery->where('id', $internship->faculty_id);
            }
            foreach ($facultyQuery->pluck('id') as $facultyId) {
                Notification::notify(
                    $facultyId,
                    'supervisor_registration',
                    'New Supervisor Registration',
                    "{$displayName} has registered as a supervisor and is awaiting your approval.",
                    '/faculty/supervisor-approvals'
                );
            }

            return response()->json([
                'message'     => 'Registration submitted successfully. Your account will be activated once the Faculty Supervisor approves it.',
                'username'    => $supCode,
            ], 201);
        });
    }

    // ─── FACULTY: List pending supervisor registrations ───────────────────────

    public function pendingList(Request $request)
    {
        $user = $request->user();

        $pendingQuery = SupervisorInviteToken::where('status', 'registered')
            ->with([
                'student.studentProfile',
                'supervisor.supervisorProfile',
                'company',
                'internship',
            ])
            ->orderByDesc('updated_at');

        // Faculty only sees invites for their assigned internships (or unassigned faculty).
        if ($user->role === 'faculty') {
            $pendingQuery->whereHas('internship', function ($q) use ($user) {
                $q->where('faculty_id', $user->id)
                    ->orWhereNull('faculty_id');
            });
        }

        $invites = $pendingQuery->get();

        $historyQuery = SupervisorInviteToken::whereIn('status', ['approved', 'rejected'])
            ->with([
                'student.studentProfile',
                'supervisor.supervisorProfile',
                'company',
                'reviewer',
            ])
            ->orderByDesc('reviewed_at')
            ->limit(20);

        if ($user->role === 'faculty') {
            $historyQuery->where(function ($q) use ($user) {
                $q->where('reviewed_by', $user->id)
                    ->orWhereHas('internship', fn ($iq) => $iq->where('faculty_id', $user->id));
            });
        }

        $history = $historyQuery->get();

        return response()->json([
            'pending' => $invites,
            'history' => $history,
        ]);
    }

    // ─── FACULTY: Approve a supervisor registration ───────────────────────────

    public function approve(Request $request, int $id)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $invite = SupervisorInviteToken::where('status', 'registered')->findOrFail($id);
        $this->assertFacultyMayReview($request->user(), $invite);

        return DB::transaction(function () use ($request, $invite) {
            // Activate the supervisor account
            $supervisorUser = User::findOrFail($invite->supervisor_user_id);
            $supervisorUser->update(['is_active' => true]);

            // Assign the supervisor to the internship
            $internship = Internship::findOrFail($invite->internship_id);
            $internship->update([
                'supervisor_id' => $supervisorUser->id,
                'company_id'    => $invite->company_id ?? $internship->company_id,
            ]);

            $invite->update([
                'status'         => 'approved',
                'reviewed_by'    => $request->user()->id,
                'reviewed_at'    => now(),
                'review_remarks' => $request->remarks,
            ]);

            // Notify the student
            Notification::notify(
                $invite->student_id,
                'supervisor_approved',
                'Supervisor Approved',
                "Your supervisor {$invite->first_name} {$invite->last_name} has been approved and assigned to your internship.",
                '/student/dashboard'
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
                'invite_id'     => $invite->id,
                'supervisor_id' => $supervisorUser->id,
            ]);

            return response()->json([
                'message' => "Supervisor {$invite->first_name} {$invite->last_name} approved and assigned successfully.",
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
            'status'         => 'rejected',
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
            'review_remarks' => $request->remarks,
        ]);

        // Notify the student
        Notification::notify(
            $invite->student_id,
            'supervisor_rejected',
            'Supervisor Registration Rejected',
            "The supervisor registration for {$invite->first_name} {$invite->last_name} was rejected: {$request->remarks}",
            '/student/dashboard'
        );

        audit_log($request->user()->id, 'reject_supervisor', ['invite_id' => $invite->id]);

        return response()->json([
            'message' => "Supervisor registration rejected.",
        ]);
    }

    /** Faculty may review invites for their advisees (or unassigned faculty on the internship). */
    private function assertFacultyMayReview($user, SupervisorInviteToken $invite): void
    {
        if ($user->role !== 'faculty') {
            abort(403, 'Only faculty supervisors may approve supervisor registrations.');
        }

        $internship = Internship::find($invite->internship_id);
        if (!$internship) {
            abort(404, 'Internship not found for this registration.');
        }

        if ($internship->faculty_id !== null && (int) $internship->faculty_id !== (int) $user->id) {
            abort(403, 'You may only review supervisor registrations for your assigned students.');
        }
    }
}
