<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Services\OfficialFormDataService;
use App\Support\InternshipAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DtrPdfController extends Controller
{
    /**
     * Generate Form 30 — Student Internship Daily Time Record (DTR)
     * GET /v1/student/dtr/generate?internship_id=&month=YYYY-MM
     * GET /v1/official-forms/{internship}/dtr.pdf
     */
    public function generate(Request $request, ?Internship $internship = null)
    {
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'month' => 'nullable|date_format:Y-m',
        ]);

        if (! $internship) {
            $internship = Internship::findOrFail($request->integer('internship_id'));
        }

        InternshipAccess::abortUnlessCanView($request->user(), $internship);

        $data = app(OfficialFormDataService::class)->pdfDtr(
            $internship,
            $request->input('month')
        );
        $studentNumber = $data['fo30']['student_name'] ?? 'student';
        $filename = 'DTR_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) (
            $internship->student?->student_number
            ?: $internship->student?->studentProfile?->student_number
            ?: $studentNumber
        )).'.pdf';

        return Pdf::loadView('pdf.form30_dtr', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
