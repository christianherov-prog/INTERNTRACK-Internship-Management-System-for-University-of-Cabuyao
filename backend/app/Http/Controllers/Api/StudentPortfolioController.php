<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\StudentPortfolio;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentPortfolioController extends Controller
{
    private function getInternship(Request $request)
    {
        $user = auth()->user();
        if ($request->filled('internship_id')) {
            $internship = Internship::findOrFail($request->internship_id);
            if ($user->hasRole('student') && $internship->student_id !== $user->id) {
                abort(403, 'Unauthorized access to this internship.');
            }
            return $internship;
        }

        if ($user->hasRole('student')) {
            $internship = $user->activeInternship()->first() ?? $user->internshipsAsStudent()->latest()->first();
            if (!$internship) {
                abort(404, 'No active internship found for your account.');
            }
            return $internship;
        }

        abort(400, 'internship_id is required.');
    }

    /**
     * Get portfolio builder data for an internship
     * GET /v1/student/portfolio/builder?internship_id=
     */
    public function show(Request $request)
    {
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
        ]);

        $internship = $this->getInternship($request);
        $internship->load(['company', 'portfolio', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile', 'student.studentProfile.program', 'student.studentProfile.department']);

        $portfolio = $internship->portfolio;
        $companyName = $internship->company?->company_name ?? ($internship->company?->name ?? 'Host Establishment');

        if (!$portfolio) {
            $portfolio = new StudentPortfolio([
                'internship_id' => $internship->id,
                'user_id' => $internship->student_id,
                'company_name' => $companyName,
                'company_address' => $internship->company?->address ?? 'City of Cabuyao, Laguna',
                'company_vision' => "To be an industry leader delivering exceptional IT, accounting, and professional technological services while nurturing future talent.",
                'company_mission' => "To provide reliable client-focused solutions through innovation, integrity, and continuous technological advancement.",
                'company_history' => $internship->company?->notes ?: "Established with a commitment to excellence, {$companyName} has continuously evolved to serve diverse client needs while maintaining strong industry standards and fostering internship training programs.",
                'assessment_ethical' => "During my internship at {$companyName}, I learned that IT professionals must be responsible, trustworthy, and careful in handling systems, devices, and user information. Ensuring accuracy, respecting data privacy, and adhering to institutional protocols are vital to professional integrity.",
                'assessment_learnings' => "I acquired hands-on technical skills in system maintenance, software testing, network setup, and project workflow management. Furthermore, I developed strong problem-solving abilities and communication skills essential for real-world operations.",
                'assessment_experience' => "My interaction with supervisors, colleagues, and fellow interns was highly rewarding. Collaborating in a professional team environment improved my teamwork, interpersonal skills, and adaptability in workplace settings.",
                'assessment_standards' => "I was exposed to industry-aligned best practices such as version control, systematic hardware diagnosis, structured agile workflows, and formal document formatting standards.",
                'assessment_recommendations' => "I recommend continuing continuous rotation across technical departments to provide future interns with broader learning exposure across different domains of Information Technology.",
                'assessment_advice' => "To future interns: always be proactive, ask questions when uncertain, maintain diligence in recording your daily achievements, and approach every technical challenge as a learning opportunity.",
            ]);
        }

        $journals = JournalEntry::where('internship_id', $internship->id)
            ->orderBy('date', 'asc')
            ->get();

        $typeMap = [
            'Curriculum Vitae (PNC:AA-FO-27)' => 'student_cv',
            'Medical Clearance' => 'medical_result',
            'Psychological Assessment Certificate' => 'psychological_result',
            'Application Letter' => 'application_letter',
            'Recommendation Letter' => 'recommendation_request',
            'Notarized Student Internship Consent Form (PNC:AA-FO-28)' => 'consent_form',
            'Notarized Student Internship Consent Form (PNC: AA-FO-28)' => 'consent_form',
            'Student Internship Acceptance Form (PNC:AA-FO-29)' => 'acceptance_form',
            'Student Internship Acceptance Form (PNC: AA-FO-29)' => 'acceptance_form',
            'Training Plan' => 'training_plan',
            'MOA / LOA / TOR' => 'moa_document',
            'Certificate of Completion' => 'completion_certificate',
            'Midterm Evaluation' => 'performance_eval',
            'Final Report' => 'performance_eval',
        ];

        $allDocs = Document::where('internship_id', $internship->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($doc) use ($typeMap) {
                $rawType = $doc->document_type ?? 'portfolio_photo';
                $mappedType = $typeMap[$rawType] ?? $rawType;
                return [
                    'id' => $doc->id,
                    'internship_id' => $doc->internship_id,
                    'file_path' => $doc->file_path,
                    'file_name' => $doc->file_name,
                    'type' => $mappedType,
                    'document_type' => $rawType,
                    'original_type' => $rawType,
                    'label' => $doc->remarks ?? $doc->file_name,
                    'remarks' => $doc->remarks,
                    'week_number' => $doc->week_number,
                    'created_at' => $doc->created_at,
                ];
            });

        $logoDoc = Document::where('internship_id', $internship->id)
            ->whereIn('document_type', ['company_logo', 'logo'])
            ->orderBy('created_at', 'desc')
            ->first();

        $orgDoc = Document::where('internship_id', $internship->id)
            ->whereIn('document_type', ['org_chart', 'chart'])
            ->orderBy('created_at', 'desc')
            ->first();

        $vmDoc = Document::where('internship_id', $internship->id)
            ->whereIn('document_type', ['vision_mission', 'company_vision_mission', 'vision_mission_photo'])
            ->orderBy('created_at', 'desc')
            ->first();

        $portfolioData = $portfolio->toArray();
        $portfolioData['company_background'] = $portfolio->company_history ?? ($portfolioData['company_history'] ?? '');
        $portfolioData['company_history'] = $portfolio->company_history ?? ($portfolioData['company_background'] ?? '');
        $portfolioData['company_vision'] = $portfolio->company_vision ?? '';
        $portfolioData['company_mission'] = $portfolio->company_mission ?? '';
        $portfolioData['prof_ethical_responsibilities'] = $portfolio->assessment_ethical ?? ($portfolioData['assessment_ethical'] ?? '');
        $portfolioData['assessment_ethical'] = $portfolio->assessment_ethical ?? ($portfolioData['prof_ethical_responsibilities'] ?? '');
        $portfolioData['things_learned'] = $portfolio->assessment_learnings ?? ($portfolioData['assessment_learnings'] ?? '');
        $portfolioData['assessment_learnings'] = $portfolio->assessment_learnings ?? ($portfolioData['things_learned'] ?? '');
        $portfolioData['experience_with_people'] = $portfolio->assessment_experience ?? ($portfolioData['assessment_experience'] ?? '');
        $portfolioData['assessment_experience'] = $portfolio->assessment_experience ?? ($portfolioData['experience_with_people'] ?? '');
        $portfolioData['industry_best_practices'] = $portfolio->assessment_standards ?? ($portfolioData['assessment_standards'] ?? '');
        $portfolioData['assessment_standards'] = $portfolio->assessment_standards ?? ($portfolioData['industry_best_practices'] ?? '');
        $portfolioData['recommendations'] = $portfolio->assessment_recommendations ?? ($portfolioData['assessment_recommendations'] ?? '');
        $portfolioData['assessment_recommendations'] = $portfolio->assessment_recommendations ?? ($portfolioData['recommendations'] ?? '');
        $portfolioData['advice'] = $portfolio->assessment_advice ?? ($portfolioData['assessment_advice'] ?? '');
        $portfolioData['assessment_advice'] = $portfolio->assessment_advice ?? ($portfolioData['advice'] ?? '');
        $portfolioData['company_logo_path'] = $logoDoc ? $logoDoc->file_path : null;
        $portfolioData['org_chart_path'] = $orgDoc ? $orgDoc->file_path : null;
        $portfolioData['vision_mission_path'] = $vmDoc ? $vmDoc->file_path : null;
        $portfolioData['photos'] = $allDocs;
        // Expose editable company name/address — fall back to HTE data if not customized
        $portfolioData['company_name'] = $portfolio->company_name
            ?? $internship->company?->company_name
            ?? $internship->company?->name
            ?? 'Host Training Establishment';
        $portfolioData['company_address'] = $portfolio->company_address
            ?? $internship->company?->address
            ?? 'City of Cabuyao, Laguna';

        $evaluations = \App\Models\Evaluation::where('internship_id', $internship->id)->get();

        $internshipData = $internship->toArray();
        $internshipData['portfolio'] = $portfolioData;
        $internshipData['journals'] = $journals;
        $internshipData['company'] = $internship->company;
        $internshipData['evaluations'] = $evaluations;

        return response()->json([
            'portfolio' => $portfolioData,
            'internship' => $internshipData,
            'user' => ($request->user() ?? auth()->user())?->load(['studentProfile.program', 'studentProfile.department']),
            'stats' => [
                'journals_count' => $journals->count(),
                'dtr_count' => AttendanceLog::where('internship_id', $internship->id)->count(),
                'photos_count' => $allDocs->count(),
                'total_docs_count' => $allDocs->count(),
            ],
            'photos' => $allDocs,
        ]);
    }

    /**
     * Save or update portfolio builder text inputs
     * POST /v1/student/portfolio/builder
     */
    public function update(Request $request)
    {
        $internship = $this->getInternship($request);

        $companyHistory = $request->input('company_history', $request->input('company_background'));
        $assessmentEthical = $request->input('assessment_ethical', $request->input('prof_ethical_responsibilities'));
        $assessmentLearnings = $request->input('assessment_learnings', $request->input('things_learned'));
        $assessmentExperience = $request->input('assessment_experience', $request->input('experience_with_people'));
        $assessmentStandards = $request->input('assessment_standards', $request->input('industry_best_practices'));
        $assessmentRecommendations = $request->input('assessment_recommendations', $request->input('recommendations'));
        $assessmentAdvice = $request->input('assessment_advice', $request->input('advice'));

        $existing = StudentPortfolio::where('internship_id', $internship->id)->first();
        $customFields = $existing?->custom_fields;
        if (! is_array($customFields)) {
            $customFields = [];
        }
        if ($request->exists('custom_fields')) {
            $incoming = $request->input('custom_fields', []);
            if (! is_array($incoming)) {
                $incoming = [];
            }
            $mergedSpecial = false;
            foreach (['psychology', 'nursing'] as $bucket) {
                if (isset($incoming[$bucket])) {
                    $customFields[$bucket] = $incoming[$bucket];
                    $mergedSpecial = true;
                }
            }
            if (! $mergedSpecial) {
                $customFields = $incoming;
            }
        }

        $payload = [
            'user_id' => $internship->student_id,
            'custom_fields' => $customFields,
        ];
        if ($request->exists('company_name')) {
            $payload['company_name'] = $request->input('company_name', $internship->company?->company_name ?? 'Host Establishment');
        } elseif (! $existing) {
            $payload['company_name'] = $internship->company?->company_name ?? 'Host Establishment';
        }
        if ($request->exists('company_address')) {
            $payload['company_address'] = $request->input('company_address', $internship->company?->address ?? '');
        }
        if ($request->exists('company_vision') || $request->exists('company_mission') || $request->exists('company_history') || $request->exists('company_background')) {
            $payload['company_vision'] = $request->input('company_vision');
            $payload['company_mission'] = $request->input('company_mission');
            $payload['company_history'] = $companyHistory;
        }
        if ($request->exists('assessment_ethical') || $request->exists('prof_ethical_responsibilities')) {
            $payload['assessment_ethical'] = $assessmentEthical;
            $payload['assessment_learnings'] = $assessmentLearnings;
            $payload['assessment_experience'] = $assessmentExperience;
            $payload['assessment_standards'] = $assessmentStandards;
            $payload['assessment_recommendations'] = $assessmentRecommendations;
            $payload['assessment_advice'] = $assessmentAdvice;
        }

        $portfolio = StudentPortfolio::updateOrCreate(
            ['internship_id' => $internship->id],
            $payload
        );

        return response()->json([
            'message' => 'Portfolio details saved successfully!',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Upload photo or certificate for the portfolio
     * POST /v1/student/portfolio/photos
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,pdf,docx|max:10240',
            'caption' => 'nullable|string|max:255',
        ]);

        $internship = $this->getInternship($request);

        $file = $request->file('file');
        $docType = $request->input('type', $request->input('document_type', 'portfolio_photo'));
        $label = $request->input('label', $request->input('caption', $file->getClientOriginalName()));
        $weekNumber = $request->input('week_number') ? (int) $request->input('week_number') : null;

        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file->getClientOriginalName());
        $filePath = $file->storeAs('documents/' . $internship->id, $fileName, 'public');

        $document = Document::create([
            'internship_id' => $internship->id,
            'document_type' => $docType,
            'week_number'   => $weekNumber,
            'file_path'     => $filePath,
            'file_name'     => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'status'        => 'approved',
            'current_stage' => 'completed',
            'remarks'       => $label,
            'submitted_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Photo uploaded successfully!',
            'document' => [
                'id' => $document->id,
                'file_path' => $document->file_path,
                'file_name' => $document->file_name,
                'type' => $document->document_type,
                'document_type' => $document->document_type,
                'label' => $document->remarks ?? $document->file_name,
                'remarks' => $document->remarks,
            ],
        ], 201);
    }

    /**
     * Delete an uploaded portfolio photo
     * DELETE /v1/student/portfolio/photos/{id}
     */
    public function deletePhoto($id)
    {
        $user = auth()->user();
        $document = Document::findOrFail($id);
        $internship = $document->internship;

        if ($user->hasRole('student') && $internship->student_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->forceDelete();

        return response()->json(['message' => 'Photo removed from portfolio successfully.']);
    }
}
