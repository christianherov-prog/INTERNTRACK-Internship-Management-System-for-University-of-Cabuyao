<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Internship;
use App\Models\StudentPortfolio;
use App\Services\PortfolioDataService;
use App\Support\InternshipAccess;
use App\Support\InternshipProvisioning;
use App\Support\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentPortfolioController extends Controller
{
    private function getInternship(Request $request)
    {
        $user = auth()->user();
        if ($request->filled('internship_id')) {
            $internship = Internship::findOrFail($request->internship_id);
            if ($user->hasRole('student')) {
                if ((int) $internship->student_id !== (int) $user->id) {
                    abort(403, 'Unauthorized access to this internship.');
                }
            } else {
                InternshipAccess::abortUnlessCanView($user, $internship);
            }

            return $internship;
        }

        $requestedId = $request->header('X-Internship-Id');
        if ($requestedId) {
            $internship = Internship::find($requestedId);
            if ($internship) {
                if ($user->hasRole('student')) {
                    if ((int) $internship->student_id !== (int) $user->id) {
                        abort(403, 'Unauthorized access to this internship.');
                    }
                } else {
                    InternshipAccess::abortUnlessCanView($user, $internship);
                }

                return $internship;
            }
        }

        if ($user->hasRole('student')) {
            $internship = InternshipProvisioning::openForStudent($user->id)
                ?? $user->internshipsAsStudent()->latest('id')->first();
            if (! $internship) {
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

        return response()->json(app(PortfolioDataService::class)->payload($internship, $request->user()));
    }

    /**
     * Save or update portfolio builder text inputs
     * POST /v1/student/portfolio/builder
     */
    public function update(Request $request)
    {
        // Column limits: company_* are VARCHAR(255); essays are TEXT.
        $essay = 'nullable|string|max:10000';
        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_vision' => $essay,
            'company_mission' => $essay,
            'company_history' => $essay,
            'company_background' => $essay,
            'assessment_ethical' => $essay,
            'prof_ethical_responsibilities' => $essay,
            'assessment_learnings' => $essay,
            'things_learned' => $essay,
            'assessment_experience' => $essay,
            'experience_with_people' => $essay,
            'assessment_standards' => $essay,
            'industry_best_practices' => $essay,
            'assessment_recommendations' => $essay,
            'recommendations' => $essay,
            'assessment_advice' => $essay,
            'advice' => $essay,
            'custom_fields' => 'nullable|array',
        ]);

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
     *
     * Files are stored on the private local disk as document_attachments.
     * The documents table no longer has file_path (dropped 2026-08-25).
     */
    public function uploadPhoto(Request $request)
    {
        $docType = (string) $request->input('type', $request->input('document_type', 'portfolio_photo'));
        $maxKb = UploadLimits::maxKb();
        $mimes = $this->allowedMimesForType($docType);

        $request->validate([
            'internship_id' => 'nullable|exists:internships,id',
            'file' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'type' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 _:-]+$/'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'caption' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'week_number' => [
                Rule::requiredIf(fn () => $this->requiresWeek($docType)),
                'nullable',
                'integer',
                'min:1',
                'max:60',
            ],
        ], [
            'file.mimes' => 'Please upload a JPG, PNG, WEBP, or GIF image only.',
            'file.max' => 'The file must not be larger than '.UploadLimits::maxMb().' MB.',
            'week_number.required' => 'Please enter a week number for this photo.',
        ]);

        $internship = $this->getInternship($request);
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $label = $request->input('label', $request->input('caption', $originalName));
        $weekNumber = $request->filled('week_number') ? (int) $request->input('week_number') : null;

        $document = DB::transaction(function () use ($internship, $file, $docType, $label, $weekNumber, $originalName) {
            if (! $this->allowsMultiple($docType)) {
                $internship->documents()
                    ->where('document_type', $docType)
                    ->get()
                    ->each(fn (Document $old) => $this->purgePortfolioDocument($old));
            }

            $document = $internship->documents()->create([
                'document_type' => $docType,
                'week_number' => $weekNumber,
                'status' => 'approved',
                'current_stage' => 'completed',
                'remarks' => $label,
                'submitted_at' => now(),
            ]);

            $safeName = substr((string) preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName), 0, 80) ?: 'upload';
            $storedName = Str::uuid()->toString().'_'.$safeName;
            $path = $file->storeAs("internships/{$internship->id}/portfolio", $storedName, 'local');

            $document->attachments()->create([
                'file_path' => $path,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return $document->load('attachments');
        });

        $attachment = $document->attachments->first();

        return response()->json([
            'message' => 'Photo uploaded successfully!',
            'document' => [
                'id' => $document->id,
                'file_path' => $attachment?->file_path,
                'file_name' => $attachment?->file_name,
                'file_size' => $attachment?->file_size,
                'mime_type' => $attachment?->mime_type,
                'type' => $document->document_type,
                'document_type' => $document->document_type,
                'label' => $document->remarks ?? $attachment?->file_name,
                'remarks' => $document->remarks,
                'week_number' => $document->week_number,
                'status' => $document->status,
                'submitted_at' => $document->submitted_at,
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
        $document = Document::with(['internship', 'attachments'])->findOrFail($id);
        $internship = $document->internship;

        if (! $internship) {
            abort(404, 'Internship not found for this file.');
        }

        if ($user->hasRole('student')) {
            if ((int) $internship->student_id !== (int) $user->id) {
                abort(403, 'Unauthorized.');
            }
        } else {
            InternshipAccess::abortUnlessCanView($user, $internship);
        }

        $this->purgePortfolioDocument($document);

        return response()->json(['message' => 'Photo removed from portfolio successfully.']);
    }

    private function allowedMimesForType(string $type): string
    {
        return 'jpeg,jpg,png,webp,gif';
    }

    private function isImageOnlyType(string $type): bool
    {
        return true;
    }

    private function allowsMultiple(string $type): bool
    {
        return in_array($type, [
            'ojt_photo', 'ojt_photos', 'training_documentation', 'exam_documentation',
            'work_samples', 'experience_photos', 'lesson_plan',
            'org_chart',
            'training_certificate', 'training_test_result', 'exam_certificate', 'exam_test_result',
        ], true);
    }

    private function requiresWeek(string $type): bool
    {
        return in_array($type, ['ojt_photo', 'ojt_photos'], true);
    }

    private function purgePortfolioDocument(Document $document): void
    {
        $document->loadMissing('attachments');

        foreach ($document->attachments as $attachment) {
            if ($attachment->file_path) {
                foreach (['local', 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($attachment->file_path)) {
                        Storage::disk($disk)->delete($attachment->file_path);
                    }
                }
            }
            $attachment->delete();
        }

        $legacyPath = $document->getAttributes()['file_path'] ?? null;
        if (is_string($legacyPath) && $legacyPath !== '') {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($legacyPath)) {
                    Storage::disk($disk)->delete($legacyPath);
                }
            }
        }

        $document->forceDelete();
    }
}
