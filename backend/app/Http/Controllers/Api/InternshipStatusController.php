<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\InternshipStatusHistory;
use App\Models\Notification;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternshipStatusController extends Controller
{
    /** GET /api/v1/{coordinator|director}/internships/{id}/status-history */
    public function history(Request $request, int $id)
    {
        $internship = Internship::with(['student.studentProfile', 'company'])->findOrFail($id);
        $this->assertCanManage($request, $internship);

        $history = InternshipStatusHistory::where('internship_id', $internship->id)
            ->with('changer.facultyProfile')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'from_label' => InternshipStatuses::label($h->from_status),
                'to_status' => $h->to_status,
                'to_label' => InternshipStatuses::label($h->to_status),
                'reason' => $h->reason,
                'changed_by' => $h->changer?->facultyProfile
                    ? trim($h->changer->facultyProfile->first_name.' '.$h->changer->facultyProfile->last_name)
                    : ($h->changer?->username ?? '—'),
                'changed_at' => optional($h->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'internship' => [
                'id' => $internship->id,
                'status' => InternshipStatuses::normalize($internship->status),
                'status_label' => InternshipStatuses::label($internship->status),
                'status_reason' => $internship->status_reason,
                'student_name' => trim(($internship->student?->studentProfile?->first_name ?? '').' '.($internship->student?->studentProfile?->last_name ?? '')) ?: $internship->student?->username,
                'company_name' => $internship->company?->company_name,
            ],
            'data' => $history,
        ]);
    }

    /**
     * PATCH /api/v1/{coordinator|director}/internships/{id}/status
     * Requires a non-empty reason for every change (Scope).
     */
    public function update(Request $request, int $id)
    {
        $internship = Internship::with('student')->findOrFail($id);
        $this->assertCanManage($request, $internship);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(InternshipStatuses::SCOPE)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $to = InternshipStatuses::normalize($data['status']);
        $from = InternshipStatuses::normalize($internship->status);

        if ($from === $to) {
            return response()->json([
                'message' => 'Internship is already in this status.',
                'internship' => $this->payload($internship),
            ], 422);
        }

        $reason = trim($data['reason']);

        $internship->update([
            'status' => $to,
            'status_reason' => $reason,
            'termination_reason' => in_array($to, ['expelled', 'terminated', 'failed'], true) ? $reason : $internship->termination_reason,
            'end_date' => $to === 'completed' ? ($internship->end_date ?? now()) : $internship->end_date,
        ]);

        InternshipStatusHistory::create([
            'internship_id' => $internship->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $request->user()->id,
        ]);

        if ($to === 'completed') {
            AbsorptionService::initializePending($internship->fresh());
        }

        if ($internship->student_id) {
            Notification::notify(
                $internship->student_id,
                'internship_status_changed',
                'Internship status updated',
                'Your internship status is now '.InternshipStatuses::label($to).'. Reason: '.$reason,
                '/student/dashboard',
                ['status' => $to, 'reason' => $reason]
            );
        }

        audit_log($request->user()->id, 'change_internship_status', [
            'internship_id' => $internship->id,
            'from' => $from,
            'to' => $to,
        ]);

        $internship->refresh()->load(['student.studentProfile', 'company']);

        return response()->json([
            'message' => 'Internship status updated.',
            'internship' => $this->payload($internship),
        ]);
    }

    private function assertCanManage(Request $request, Internship $internship): void
    {
        $role = $request->user()->role;
        if (!in_array($role, ['coordinator', 'director'], true)) {
            abort(403, 'Only coordinators and directors may change internship status.');
        }

        // Coordinators may manage internships they coordinate or any pending/active in roster.
        if ($role === 'coordinator' && $internship->coordinator_id && $internship->coordinator_id !== $request->user()->id) {
            // Still allow if coordinator_id was never set / reassigned — open for active coords.
        }
    }

    private function payload(Internship $internship): array
    {
        return [
            'id' => $internship->id,
            'status' => InternshipStatuses::normalize($internship->status),
            'status_label' => InternshipStatuses::label($internship->status),
            'status_reason' => $internship->status_reason,
            'student_id' => $internship->student_id,
            'company_name' => $internship->company?->company_name,
        ];
    }
}
