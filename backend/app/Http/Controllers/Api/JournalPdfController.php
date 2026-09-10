<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Services\OfficialFormDataService;
use App\Support\InternshipAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class JournalPdfController extends Controller
{
    /**
     * Generate Form 31 — Weekly Student Internship Journal PDF
     * GET /v1/student/journal/generate?internship_id=&week_number=
     * GET /v1/official-forms/{internship}/journal.pdf
     */
    public function generate(Request $request, ?Internship $internship = null)
    {
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'week_number' => 'nullable|integer|min:1',
        ]);

        if (! $internship) {
            $internship = Internship::findOrFail($request->integer('internship_id'));
        }

        InternshipAccess::abortUnlessCanView($request->user(), $internship);

        $pdfData = app(OfficialFormDataService::class)->pdfJournal($internship);
        $journals = collect($pdfData['journals'] ?? []);
        if ($request->filled('week_number')) {
            $journals = $journals->where('week_number', (int) $request->week_number)->values();
        }

        if ($journals->isEmpty()) {
            return response()->json(['error' => 'No journal entries found for the selected period.'], 404);
        }

        $pdfData['journals'] = $journals;
        $studentNumber = $pdfData['identity']['student_number'] ?? $internship->student_id;
        $weekSuffix = $request->week_number ? '_Week'.$request->week_number : '_All';
        $filename = 'Journal_'.$studentNumber.$weekSuffix.'.pdf';

        return Pdf::loadView('pdf.form31_journal', $pdfData)
            ->setPaper('letter', 'portrait')
            ->download($filename);
    }
}
