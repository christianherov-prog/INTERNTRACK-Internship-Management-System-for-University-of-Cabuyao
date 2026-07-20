<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Support\InternshipStatuses;
use App\Support\SimpleCertificatePdf;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    /**
     * GET /api/v1/student/certificates/completion
     * GET /api/v1/{coordinator|director}/internships/{id}/certificate
     *
     * Generates a PDF from live internship/student data when status is completed.
     */
    public function completion(Request $request, ?int $id = null)
    {
        $user = $request->user();

        if ($id) {
            if (!in_array($user->role, ['coordinator', 'director'], true)) {
                abort(403);
            }
            $internship = Internship::with([
                'student.studentProfile',
                'company',
                'coordinator.facultyProfile',
            ])->findOrFail($id);
        } else {
            if ($user->role !== 'student') {
                abort(403);
            }
            $internship = Internship::with([
                'student.studentProfile',
                'company',
                'coordinator.facultyProfile',
            ])
                ->where('student_id', $user->id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if (!$internship) {
                return response()->json([
                    'message' => 'No completed internship found. A certificate can only be generated after status is set to Completed.',
                ], 422);
            }
        }

        if (InternshipStatuses::normalize($internship->status) !== 'completed') {
            return response()->json([
                'message' => 'Certificate can only be generated when internship status is Completed.',
                'status' => InternshipStatuses::normalize($internship->status),
            ], 422);
        }

        $profile = $internship->student?->studentProfile;
        $studentName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''))
            ?: ($internship->student?->username ?? 'Student');
        $program = $profile?->program ?: ($profile?->course_name ?: '—');
        $term = $internship->term ?: config('interntrack.current_term', 'AY 2025-2026, Sem 2');
        $company = $internship->company?->company_name ?: '—';
        $studentNo = $profile?->student_number ?: ($internship->student?->username ?? '—');
        $hours = (float) $internship->total_hours_rendered;
        $issued = now()->format('F j, Y');

        $coord = $internship->coordinator?->facultyProfile;
        $coordName = $coord
            ? trim($coord->first_name.' '.$coord->last_name)
            : 'Internship Coordinator';

        $viewData = [
            'studentName' => $studentName,
            'studentNo' => $studentNo,
            'program' => $program,
            'term' => $term,
            'company' => $company,
            'hours' => $hours,
            'issued' => $issued,
            'coordName' => $coordName,
        ];

        $filename = 'completion-certificate-'.$studentNo.'.pdf';

        audit_log($user->id, 'generate_completion_certificate', [
            'internship_id' => $internship->id,
        ]);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.completion', $viewData)
                ->setPaper('a4', 'landscape');

            return $pdf->download($filename);
        }

        return SimpleCertificatePdf::download($viewData, $filename);
    }
}
