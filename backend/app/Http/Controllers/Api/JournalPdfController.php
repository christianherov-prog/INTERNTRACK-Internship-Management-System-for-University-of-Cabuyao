<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class JournalPdfController extends Controller
{
    /**
     * Generate Form 31 — Weekly Student Internship Journal PDF
     * GET /v1/student/journal/generate?internship_id=&week_number=
     * Optional: omit week_number to generate all weeks as a multi-page PDF.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'internship_id' => 'required|exists:internships,id',
            'week_number'   => 'nullable|integer|min:1',
        ]);

        $user       = auth()->user();
        $internship = Internship::with([
            'student.studentProfile',
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
        ])->findOrFail($request->internship_id);

        $this->authorizeInternshipAccess($user, $internship);

        $query = JournalEntry::where('internship_id', $internship->id)
            ->orderBy('week_number');

        if ($request->week_number) {
            $query->where('week_number', $request->week_number);
        }

        $journals = $query->get();

        if ($journals->isEmpty()) {
            return response()->json(['error' => 'No journal entries found for the selected period.'], 404);
        }

        $studentProfile  = $internship->student->studentProfile;
        $studentSignature = $this->getSignaturePath($internship->student);

        $pdf = Pdf::loadView('pdf.form31_journal', [
            'internship'       => $internship,
            'studentProfile'   => $studentProfile,
            'company'          => $internship->company,
            'journals'         => $journals,
            'studentSignature' => $studentSignature ? $this->signatureBase64($studentSignature) : null,
        ])->setPaper('letter', 'portrait');

        $weekSuffix = $request->week_number ? '_Week' . $request->week_number : '_All';
        $filename   = 'Journal_' . ($studentProfile->student_number ?? $internship->student_id) . $weekSuffix . '.pdf';

        return $pdf->download($filename);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function authorizeInternshipAccess($user, Internship $internship): void
    {
        \App\Support\InternshipAccess::abortUnlessCanView($user, $internship);
    }

    protected function getSignaturePath($user): ?string
    {
        $path = "signatures/{$user->id}_processed.png";
        return Storage::exists($path) ? $path : null;
    }

    protected function signatureBase64(string $storagePath): string
    {
        $data = Storage::get($storagePath);
        return 'data:image/png;base64,' . base64_encode($data);
    }
}
