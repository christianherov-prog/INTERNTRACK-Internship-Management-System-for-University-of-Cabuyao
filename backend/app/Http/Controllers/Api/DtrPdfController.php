<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DtrPdfController extends Controller
{
    /**
     * Generate Form 30 — Student Internship Daily Time Record (DTR)
     * GET /v1/student/dtr/generate?internship_id=&month=YYYY-MM
     * Also accessible by faculty & coordinator scoped via internship ownership checks.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'internship_id' => 'required|exists:internships,id',
            'month'         => 'required|date_format:Y-m', // e.g. 2025-06
        ]);

        $user = auth()->user();
        $internship = Internship::with([
            'student.studentProfile',
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
        ])->findOrFail($request->internship_id);

        // Authorization: student can only view their own; supervisor/faculty can view their assigned
        $this->authorizeInternshipAccess($user, $internship);

        [$year, $month] = explode('-', $request->month);
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        $logs = AttendanceLog::where('internship_id', $internship->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $student       = $internship->student;
        $studentProfile = $student->studentProfile;
        $supervisorProfile = $internship->supervisor?->supervisorProfile;
        $facultyProfile    = $internship->faculty?->facultyProfile;

        // Signature paths — already background-removed, stored in storage
        $studentSignaturePath    = $this->getSignaturePath($student);
        $supervisorSignaturePath = $internship->supervisor ? $this->getSignaturePath($internship->supervisor) : null;

        $pdf = Pdf::loadView('pdf.form30_dtr', [
            'internship'              => $internship,
            'studentProfile'          => $studentProfile,
            'supervisorProfile'       => $supervisorProfile,
            'facultyProfile'          => $facultyProfile,
            'company'                 => $internship->company,
            'logs'                    => $logs,
            'month'                   => $startDate->format('F Y'),
            'studentSignature'        => $studentSignaturePath ? $this->signatureBase64($studentSignaturePath) : null,
            'supervisorSignature'     => $supervisorSignaturePath ? $this->signatureBase64($supervisorSignaturePath) : null,
        ])->setPaper('letter', 'portrait');

        $filename = 'DTR_' . ($studentProfile->student_number ?? $student->id) . '_' . $request->month . '.pdf';

        return $pdf->download($filename);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function authorizeInternshipAccess($user, Internship $internship): void
    {
        $allowed = match ($user->role) {
            'student'     => $internship->student_id === $user->id,
            'supervisor'  => $internship->supervisor_id === $user->id,
            'faculty'     => $internship->faculty_id === $user->id,
            'coordinator',
            'director',
            'admin'       => true,
            default       => false,
        };

        abort_if(!$allowed, 403, 'Access denied to this internship.');
    }

    protected function getSignaturePath($user): ?string
    {
        // Signature stored at: signatures/{user_id}_processed.png
        $path = "signatures/{$user->id}_processed.png";
        return Storage::exists($path) ? $path : null;
    }

    protected function signatureBase64(string $storagePath): string
    {
        $data = Storage::get($storagePath);
        return 'data:image/png;base64,' . base64_encode($data);
    }
}
