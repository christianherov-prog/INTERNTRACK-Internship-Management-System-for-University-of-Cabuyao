<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CertificateEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateController extends Controller
{
    /**
     * GET /api/v1/student/certificates/completion
     */
    public function downloadCompletion(Request $request)
    {
        $user = $request->user();
        $internship = $user->internshipsAsStudent()
            ->where('status', 'completed')
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'documents', 'evaluations'])
            ->latest('id')
            ->firstOrFail();

        if (!CertificateEligibilityService::isEligible($internship)) {
            return response()->json([
                'message'   => 'You are not yet eligible to download a completion certificate.',
                'checklist' => CertificateEligibilityService::checklist($internship),
            ], 403);
        }

        if (!$internship->certificate_issued_at) {
            $internship->update([
                'certificate_eligible' => true,
                'certificate_issued_at' => now(),
            ]);
        }

        $profile  = $user->studentProfile;
        $name     = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        $company  = $internship->company?->company_name ?? 'N/A';
        
        // Return a mock PDF payload (since no pdf generator is fully configured in this context)
        // Note: Real PDF generation would use DomPDF/Snappy.
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="completion-certificate.pdf"',
        ]);
    }
}
