<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\AttendanceLog;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class PortfolioPdfController extends Controller
{
    /**
     * Generate Master Internship Portfolio DOCX/PDF from template
     * GET /v1/student/portfolio/generate?internship_id=&format=pdf|docx
     */
    public function generate(Request $request)
    {
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'format'        => 'nullable|string|in:docx,pdf',
        ]);

        $user = Auth::user();
        if ($request->filled('internship_id')) {
            $internship = Internship::with([
                'student.studentProfile',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'portfolio',
            ])->findOrFail($request->internship_id);
            $this->authorizeInternshipAccess($user, $internship);
        } elseif ($user->hasRole('student')) {
            $internship = $user->activeInternship()->with([
                'student.studentProfile',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'portfolio',
            ])->first() ?? $user->internshipsAsStudent()->with([
                'student.studentProfile',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'portfolio',
            ])->latest()->first();
            if (!$internship) {
                return response()->json(['error' => 'No active internship found.'], 404);
            }
        } else {
            return response()->json(['error' => 'internship_id is required.'], 400);
        }


        $student        = $internship->student;
        $studentProfile = $student->studentProfile;
        $facultyProfile = $internship->faculty?->facultyProfile;
        $portfolio      = $internship->portfolio;

        $templatePath = storage_path('app/templates/PORTFOLIO.docx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Portfolio master template not found on server.'], 404);
        }

        $tp = new TemplateProcessor($templatePath);

        // 1. Cover & Header Metadata
        $companyName    = $portfolio?->company_name ?: ($internship->company?->company_name ?? ($internship->company?->name ?? 'Host Establishment'));
        $companyAddress = $portfolio?->company_address ?: ($internship->company?->address ?? 'City of Cabuyao, Laguna');
        $course         = $studentProfile?->program?->name ?? ($internship->program ?? 'Bachelor of Science in Computer Science');

        $middleInitial = $studentProfile?->middle_name ? substr($studentProfile->middle_name, 0, 1) . '.' : null;
        $studentName   = trim(implode(' ', array_filter([$studentProfile?->first_name, $middleInitial, $studentProfile?->last_name]))) ?: ($student->name ?? 'Student Name');

        $section       = $studentProfile?->section ?? '4ITD';

        $instructorMid = $facultyProfile?->middle_name ? substr($facultyProfile->middle_name, 0, 1) . '.' : null;
        $instructor    = trim(implode(' ', array_filter([$facultyProfile?->first_name, $instructorMid, $facultyProfile?->last_name]))) ?: ($internship->faculty?->name ?? 'Asst. Prof. Arcelito Quiatchon');

        $monthYear     = now()->format('F Y');

        $tp->setValue('company_name', $this->escXml($companyName));
        $tp->setValue('company_address', $this->escXml($companyAddress));
        $tp->setValue('course', $this->escXml($course));
        $tp->setValue('student_name', $this->escXml($studentName));
        $tp->setValue('section', $this->escXml($section));
        $tp->setValue('internship_instructor', $this->escXml($instructor));
        $tp->setValue('submission_month_year', $this->escXml($monthYear));

        // 2. Chapter I: Introduction & Company Profile
        $tp->setValue('uc_vision', $this->escXml("A premier institution of higher learning in the region, recognized for excellence in academic programs, research, and community service that contribute to sustainable development."));
        $tp->setValue('uc_mission', $this->escXml("To provide quality, relevant, and accessible education that nurtures competent, ethical, and socially responsible professionals prepared for global competitiveness."));
        
        $cVision  = $portfolio?->company_vision ?: "To be an industry leader delivering exceptional IT, accounting, and professional technological services while nurturing future talent.";
        $cMission = $portfolio?->company_mission ?: "To provide reliable client-focused solutions through innovation, integrity, and continuous technological advancement.";
        $tp->setValue('company_vision', $this->escXml($cVision));
        $tp->setValue('company_mission', $this->escXml($cMission));
        
        $history = $portfolio?->company_history ?: ($internship->company?->notes ?: "Established with a commitment to excellence, {$companyName} has continuously evolved to serve diverse client needs while maintaining strong industry standards and fostering internship training programs.");
        $tp->setValue('company_history', $this->escXml($history));

        // 3. Chapter II: Weekly Progress Reports (Block Cloning)
        $journals = JournalEntry::where('internship_id', $internship->id)
            ->orderBy('week_number')
            ->get();

        if ($journals->count() > 0) {
            $tp->cloneBlock('week_block', $journals->count(), true, true);
            foreach ($journals as $index => $j) {
                $pos = $index + 1;
                $tp->setValue("week_num#{$pos}", $this->escXml($j->week_number ?? $pos));
                $tp->setValue("week_date#{$pos}", $this->escXml($j->date ? $j->date->format('M d, Y') : "Week {$pos}"));
                $tp->setValue("activities_summary#{$pos}", $this->escXml($j->activities_summary ?: 'Completed assigned technical and departmental tasks.'));
                $tp->setValue("learnings#{$pos}", $this->escXml($j->learnings ?: 'Acquired practical exposure in IT operations and professional workflow.'));
                $tp->setValue("challenges#{$pos}", $this->escXml($j->challenges ?: 'Overcame technical challenges through self-paced research and supervisor guidance.'));
            }
        } else {
            $tp->cloneBlock('week_block', 1, true, true);
            $tp->setValue('week_num#1', '1');
            $tp->setValue('week_date#1', $this->escXml(now()->format('M d, Y')));
            $tp->setValue('activities_summary#1', 'No weekly journals recorded yet in database.');
            $tp->setValue('learnings#1', 'Pending journal submission.');
            $tp->setValue('challenges#1', 'None.');
        }

        // 4. Chapter III: Assessment of the Program
        $ethical  = $portfolio?->assessment_ethical ?: "During my internship at {$companyName}, I learned that IT professionals must be responsible, trustworthy, and careful in handling systems, devices, and user information. Ensuring accuracy, respecting data privacy, and adhering to institutional protocols are vital to professional integrity.";
        $learn    = $portfolio?->assessment_learnings ?: "I acquired hands-on technical skills in system maintenance, software testing, network setup, and project workflow management. Furthermore, I developed strong problem-solving abilities and communication skills essential for real-world operations.";
        $exp      = $portfolio?->assessment_experience ?: "My interaction with supervisors, colleagues, and fellow interns was highly rewarding. Collaborating in a professional team environment improved my teamwork, interpersonal skills, and adaptability in workplace settings.";
        $std      = $portfolio?->assessment_standards ?: "I was exposed to industry-aligned best practices such as version control, systematic hardware diagnosis, structured agile workflows, and formal document formatting standards.";
        $rec      = $portfolio?->assessment_recommendations ?: "I recommend continuing continuous rotation across technical departments to provide future interns with broader learning exposure across different domains of Information Technology.";
        $adv      = $portfolio?->assessment_advice ?: "To future interns: always be proactive, ask questions when uncertain, maintain diligence in recording your daily achievements, and approach every technical challenge as a learning opportunity.";

        $tp->setValue('assessment_ethical', $this->escXml($ethical));
        $tp->setValue('assessment_learnings', $this->escXml($learn));
        $tp->setValue('assessment_experience', $this->escXml($exp));
        $tp->setValue('assessment_standards', $this->escXml($std));
        $tp->setValue('assessment_recommendations', $this->escXml($rec));
        $tp->setValue('assessment_advice', $this->escXml($adv));

        // 5. Appendices: CV Metadata

        $studentNumber = $studentProfile?->student_number ?? $student->id;
        $tp->setValue('student_number', $this->escXml($studentNumber));
        $tp->setValue('student_email', $this->escXml($student->email ?? 'student@uc.edu.ph'));
        $tp->setValue('student_phone', $this->escXml($studentProfile?->contact_number ?? 'N/A'));

        // 6. Appendices: DTR Table (Row Cloning)
        $logs = AttendanceLog::where('internship_id', $internship->id)
            ->orderBy('date')
            ->get();

        if ($logs->count() > 0) {
            $tp->cloneRow('dtr_date', $logs->count());
            foreach ($logs as $index => $log) {
                $pos = $index + 1;
                $tp->setValue("dtr_date#{$pos}", $this->escXml($log->date ? $log->date->format('Y-m-d') : 'N/A'));
                $tp->setValue("dtr_day#{$pos}", $this->escXml($log->date ? $log->date->format('D') : ''));
                $tp->setValue("am_in#{$pos}", $this->escXml($log->am_in ? substr($log->am_in, 0, 5) : '--'));
                $tp->setValue("am_out#{$pos}", $this->escXml($log->am_out ? substr($log->am_out, 0, 5) : '--'));
                $tp->setValue("pm_in#{$pos}", $this->escXml($log->pm_in ? substr($log->pm_in, 0, 5) : '--'));
                $tp->setValue("pm_out#{$pos}", $this->escXml($log->pm_out ? substr($log->pm_out, 0, 5) : '--'));
                $tp->setValue("dtr_hours#{$pos}", $this->escXml($log->rendered_hours ?? '--'));
                $tp->setValue("dtr_status#{$pos}", $this->escXml(ucfirst($log->status ?? 'present')));
            }
        } else {
            $tp->cloneRow('dtr_date', 1);
            $tp->setValue('dtr_date#1', $this->escXml(now()->format('Y-m-d')));
            $tp->setValue('dtr_day#1', $this->escXml(now()->format('D')));
            $tp->setValue('am_in#1', '--');
            $tp->setValue('am_out#1', '--');
            $tp->setValue('pm_in#1', '--');
            $tp->setValue('pm_out#1', '--');
            $tp->setValue('dtr_hours#1', '0');
            $tp->setValue('dtr_status#1', 'No records');
        }

        // 7. Appendices: OJT Documentation & Photos (Block Cloning)
        $docs = Document::where('internship_id', $internship->id)->get();
        if ($docs->count() > 0) {
            $tp->cloneBlock('photo_block', $docs->count(), true, true);
            foreach ($docs as $index => $docItem) {
                $pos = $index + 1;
                $caption = $docItem->remarks ?: ($docItem->document_type ? ucwords(str_replace('_', ' ', $docItem->document_type)) : 'OJT Document');
                $tp->setValue("photo_caption#{$pos}", $this->escXml($caption));
                $tp->setValue("photo_name#{$pos}", $this->escXml($docItem->file_name ?? 'Attachment'));
            }
        } else {
            $tp->cloneBlock('photo_block', 1, true, true);
            $tp->setValue('photo_caption#1', 'No OJT documentation uploaded yet.');
            $tp->setValue('photo_name#1', 'N/A');
        }

        // Save populated docx
        $baseFilename  = "Portfolio_{$studentNumber}_" . now()->format('Y-m-d');
        $tempDir       = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempDocx = "{$tempDir}/{$baseFilename}.docx";
        $tp->saveAs($tempDocx);

        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'docx') {
            return response()->download($tempDocx, "{$baseFilename}.docx")->deleteFileAfterSend(true);
        }

        // Convert populated DOCX to PDF with 100% fidelity
        $tempPdf = "{$tempDir}/{$baseFilename}.pdf";
        $pdfConverted = false;
        $conversionError = null;

        // Method 1: Try Python docx2pdf (Native Word rendering on Windows/Mac)
        try {
            $cmd = sprintf('python -c "from docx2pdf import convert; convert(r\'%s\', r\'%s\')" 2>&1', $tempDocx, $tempPdf);
            exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($tempPdf) && filesize($tempPdf) > 0) {
                $pdfConverted = true;
            }
        } catch (\Exception $e) {
            $conversionError = $e->getMessage();
        }

        // Method 2: Try LibreOffice Headless (Standard for Ubuntu/Linux production servers)
        if (!$pdfConverted) {
            try {
                $out2 = [];
                exec("soffice --headless --convert-to pdf --outdir " . escapeshellarg($tempDir) . " " . escapeshellarg($tempDocx) . " 2>&1", $out2, $ret2);
                if ($ret2 === 0 && file_exists($tempPdf) && filesize($tempPdf) > 0) {
                    $pdfConverted = true;
                } else {
                    exec("libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($tempDir) . " " . escapeshellarg($tempDocx) . " 2>&1", $out2, $ret3);
                    if ($ret3 === 0 && file_exists($tempPdf) && filesize($tempPdf) > 0) {
                        $pdfConverted = true;
                    }
                }
            } catch (\Exception $e) {
                $conversionError = $e->getMessage();
            }
        }

        // Method 3: Fallback to PHPWord + DomPDF (Basic HTML-based rendering if no native engine is available)
        if (!$pdfConverted) {
            try {
                Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
                Settings::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));
                $phpWord   = IOFactory::load($tempDocx, 'Word2007');
                $xmlWriter = IOFactory::createWriter($phpWord, 'PDF');
                $xmlWriter->save($tempPdf);
                if (file_exists($tempPdf) && filesize($tempPdf) > 0) {
                    $pdfConverted = true;
                }
            } catch (\Exception $e) {
                $conversionError = $e->getMessage();
            }
        }

        @unlink($tempDocx);

        if (!$pdfConverted || !file_exists($tempPdf)) {
            return response()->json([
                'error'   => 'PDF conversion failed. Please download as DOCX format.',
                'details' => $conversionError ?: 'No PDF converter (MS Word, LibreOffice, or DomPDF) succeeded.',
            ], 500);
        }

        return response()->download($tempPdf, "{$baseFilename}.pdf")->deleteFileAfterSend(true);
    }

    private function escXml($str): string
    {
        if (is_null($str)) {
            return '';
        }
        return htmlspecialchars((string) $str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
}
