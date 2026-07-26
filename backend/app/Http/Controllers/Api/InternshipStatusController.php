<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Internship;
use App\Models\InternshipStatusHistory;
use App\Models\Notification;
use App\Services\AbsorptionService;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InternshipStatusController extends Controller
{
    private const OCCUPYING = ['active', 'ongoing', 'placed', 'for_evaluation', 'suspended'];

    private const FREEING = ['completed', 'expelled', 'deferred', 'terminated', 'failed'];

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

        try {
            DB::transaction(function () use ($request, $internship, $from, $to, $reason) {
                if ($internship->company_id) {
                    $company = Company::whereKey($internship->company_id)->lockForUpdate()->first();
                    if ($company) {
                        $this->adjustSlotsForTransition($company, $from, $to);
                    }
                }

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
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

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

    private function adjustSlotsForTransition(Company $company, string $from, string $to): void
    {
        $wasOccupying = in_array($from, self::OCCUPYING, true);
        $willOccupy = in_array($to, self::OCCUPYING, true);
        $wasFreeing = in_array($from, self::FREEING, true);
        $willFree = in_array($to, self::FREEING, true);

        if ($wasOccupying && $willFree) {
            $company->releaseSlot();
            return;
        }

        if ($wasFreeing && $willOccupy) {
            if (!$company->isEligibleForPlacement()) {
                throw new \RuntimeException($company->ineligibilityReason());
            }
            $company->consumeSlot();
        }
    }

    private function assertCanManage(Request $request, Internship $internship): void
    {
        $role = $request->user()->role;
        if ($role === 'director') {
            return;
        }

        if ($role === 'coordinator') {
            // Own assigned internships, or unassigned (null) so a coordinator can place/manage.
            if ($internship->coordinator_id !== null
                && (int) $internship->coordinator_id !== (int) $request->user()->id) {
                abort(403, 'You may only manage internships assigned to you as coordinator.');
            }

            return;
        }

        abort(403, 'Only coordinators and directors may change internship status.');
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
