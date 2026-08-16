# Report 8: Laravel 12 Backend Integration Guide

This guide provides the robust backend architecture for ingesting multipart form data, validating all 271 fields, injecting values into `Internship_Portfolio_Master_Template_Final.docx` via PHPWord `TemplateProcessor`, and converting the document to PDF using LibreOffice in headless mode.

## 1. Controller Implementation (`PortfolioController.php`)

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePortfolioRequest;
use App\Models\Intern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortfolioController extends Controller
{
    /**
     * Generate official internship portfolio DOCX and PDF.
     */
    public function generate(GeneratePortfolioRequest $request): BinaryFileResponse
    {
        $data = $request->validated();
        $studentNumber = $data['student_number'];
        $timestamp = now()->format('Ymd_His');

        // Define paths
        $templatePath = storage_path('app/templates/Internship_Portfolio_Master_Template_Final.docx');
        if (!file_exists($templatePath)) {
            abort(500, 'Master template not found on system.');
        }

        $outputDir = storage_path("app/public/portfolios/{$studentNumber}/");
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $docxPath = "{$outputDir}/{$studentNumber}_Portfolio_{$timestamp}.docx";
        $pdfPath  = str_replace('.docx', '.pdf', $docxPath);

        try {
            $tpl = new TemplateProcessor($templatePath);

            // 1. Map Cover & Profile Text Placeholders
            $tpl->setValue('student_name',          strtoupper($data['student_name']));
            $tpl->setValue('student_number',        $data['student_number']);
            $tpl->setValue('course',                $data['course']);
            $tpl->setValue('section',               $data['section']);
            $tpl->setValue('internship_instructor', strtoupper($data['instructor_name']));
            $tpl->setValue('submission_month_year', $data['submission_date']);
            $tpl->setValue('company_name',          strtoupper($data['company_name']));
            $tpl->setValue('company_address',       $data['company_address']);
            $tpl->setValue('company_vision_mission',$data['vision_mission']);
            $tpl->setValue('company_history',       $data['company_history']);
            $tpl->setValue('uc_vision',             $data['uc_vision']);
            $tpl->setValue('uc_mission',            $data['uc_mission']);

            // 2. Map Chapter III Assessment Placeholders
            $tpl->setValue('assessment_professional_ethics', $data['professional_ethics'] ?? '');
            $tpl->setValue('assessment_it_learnings',        $data['it_learnings'] ?? '');
            $tpl->setValue('assessment_people_experience',   $data['people_experience'] ?? '');
            $tpl->setValue('assessment_industry_standards',  $data['industry_standards'] ?? '');
            $tpl->setValue('assessment_recommendations',     $data['recommendations'] ?? '');
            $tpl->setValue('assessment_advice',              $data['advice'] ?? '');

            // 3. Map Weekly Progress Reports (Weeks 1 to 16)
            for ($w = 1; $w <= 16; $w++) {
                $tpl->setValue("week{$w}_start_date",         $data["week{$w}_start_date"] ?? '');
                $tpl->setValue("week{$w}_end_date",           $data["week{$w}_end_date"] ?? '');
                $tpl->setValue("week{$w}_objectives",         $data["week{$w}_objectives"] ?? '');
                $tpl->setValue("week{$w}_tasks",              $data["week{$w}_tasks"] ?? '');
                $tpl->setValue("week{$w}_skills",             $data["week{$w}_skills"] ?? '');
                $tpl->setValue("week{$w}_problems",           $data["week{$w}_problems"] ?? 'None');
                $tpl->setValue("week{$w}_solutions",          $data["week{$w}_solutions"] ?? 'None');
                $tpl->setValue("week{$w}_reflection",         $data["week{$w}_reflection"] ?? '');
                $tpl->setValue("week{$w}_faculty_remarks",    $data["week{$w}_faculty_remarks"] ?? 'N/A');
                $tpl->setValue("week{$w}_supervisor_remarks", $data["week{$w}_supervisor_remarks"] ?? 'N/A');
                $tpl->setValue("week{$w}_photo1_caption",     $data["week{$w}_photo1_caption"] ?? '');
                $tpl->setValue("week{$w}_photo2_caption",     $data["week{$w}_photo2_caption"] ?? '');

                // Weekly photos
                $this->injectImage($tpl, $request, "week{$w}_photo1", 300, 200);
                $this->injectImage($tpl, $request, "week{$w}_photo2", 300, 200);
            }

            // 4. Map Profile Images
            $this->injectImage($tpl, $request, 'student_photo', 120, 120);
            $this->injectImage($tpl, $request, 'company_logo', 150, 80);
            $this->injectImage($tpl, $request, 'org_chart', 400, 250, 'organizational_chart');

            // 5. Map Appendices Images (26 Forms)
            $appendices = [
                'registration_form', 'medical_result', 'psychological_result',
                'application_letter', 'curriculum_vitae', 'recommendation_letter',
                'acceptance_form', 'consent_form', 'training_plan',
                'daily_time_record', 'performance_evaluation', 'memorandum_of_agreement',
                'visitation_form', 'certificate_completion', 'host_evaluation',
                'program_evaluation', 'ojt_photos', 'training_certificate',
                'training_pretest', 'training_posttest', 'training_documentation1',
                'training_documentation2', 'certification_exam', 'certification',
                'exam_documentation1', 'exam_documentation2'
            ];

            foreach ($appendices as $appx) {
                $this->injectImage($tpl, $request, $appx, 450, 600);
            }

            // Save populated DOCX
            $tpl->saveAs($docxPath);
            Log::info("DOCX successfully generated at: {$docxPath}");

            // Convert to PDF using LibreOffice Headless
            $this->convertToPdf($docxPath, $outputDir);

            if (file_exists($pdfPath)) {
                return response()->download($pdfPath, "{$studentNumber}_Official_Portfolio.pdf", [
                    'Content-Type' => 'application/pdf',
                ]);
            }

            // Fallback to DOCX if PDF conversion failed
            return response()->download($docxPath, "{$studentNumber}_Official_Portfolio.docx");

        } catch (\Exception $e) {
            Log::error("Portfolio generation failed for {$studentNumber}: " . $e->getMessage());
            abort(500, "Document automation error: " . $e->getMessage());
        }
    }

    /**
     * Helper to safely inject images or fallback text.
     */
    private function injectImage(TemplateProcessor $tpl, Request $request, string $fileKey, int $w, int $h, ?string $placeholder = null): void
    {
        $targetPlaceholder = $placeholder ?? $fileKey;
        if ($request->hasFile($fileKey) && $request->file($fileKey)->isValid()) {
            $file = $request->file($fileKey);
            $tpl->setImageValue($targetPlaceholder, [
                'path'   => $file->getPathname(),
                'width'  => $w,
                'height' => $h,
                'ratio'  => true,
            ]);
        } else {
            // Replace image placeholder with text note if not uploaded
            $tpl->setValue($targetPlaceholder, '[Document/Photo Not Uploaded]');
        }
    }

    /**
     * Execute headless LibreOffice conversion.
     */
    private function convertToPdf(string $docxPath, string $outputDir): void
    {
        $libreoffice = config('app.libreoffice_path', 'libreoffice');
        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($libreoffice),
            escapeshellarg($outputDir),
            escapeshellarg($docxPath)
        );

        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            Log::warning("LibreOffice PDF conversion exited with code {$returnCode}: " . implode("\n", $output));
        }
    }
}
```
