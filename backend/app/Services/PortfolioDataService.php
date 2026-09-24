<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\ManilaAttendanceClock;
use App\Support\ManilaTime;
use App\Support\NameParts;
use App\Support\SignatureCapture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles the student Portfolio payload from authoritative InternTrack records.
 * The official form layout lives in the frontend; this service only supplies data.
 */
class PortfolioDataService
{
    public function payload(Internship $internship, User $viewer, bool $includeUnapprovedJournals = false): array
    {
        $internship->loadMissing([
            'company',
            'portfolio',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
            'coordinator.facultyProfile',
            'student.studentProfile.program',
            'student.studentProfile.department',
        ]);

        $student = $internship->student;
        $profile = $student?->studentProfile;
        $company = $internship->company;
        $portfolio = $internship->portfolio;

        $companyName = $this->firstNonEmpty(
            $portfolio?->company_name,
            $company?->company_name,
            $company?->name
        );
        $companyAddress = $this->firstNonEmpty(
            $portfolio?->company_address,
            $company?->address
        );

        $photos = $this->documents($internship);
        $moa = $this->resolveMoa($internship);
        if ($moa && ! $photos->contains(fn ($doc) => ($doc['file_path'] ?? null) === $moa['path'])) {
            $photos->prepend([
                'id' => 'moa-placement',
                'internship_id' => $internship->id,
                'file_path' => $moa['path'],
                'file_name' => $moa['name'],
                'type' => 'moa_document',
                'document_type' => 'Memorandum of Agreement',
                'original_type' => 'Memorandum of Agreement',
                'label' => $moa['name'],
                'remarks' => $moa['name'],
                'week_number' => null,
                'created_at' => null,
            ]);
        }

        $logoPath = $this->pickCompanyLogoPath($photos);
        $orgDoc = $photos->first(fn ($d) => in_array($d['type'] ?? '', ['org_chart', 'chart'], true));
        $vmDoc = $photos->first(fn ($d) => in_array($d['type'] ?? '', ['vision_mission', 'company_vision_mission', 'vision_mission_photo'], true));

        $portfolioData = $portfolio ? $portfolio->toArray() : [
            'internship_id' => $internship->id,
            'user_id' => $internship->student_id,
        ];

        $vision = $portfolio?->company_vision;
        $mission = $portfolio?->company_mission;
        $history = $portfolio?->company_history ?: ($company?->notes ?: null);

        $portfolioData['company_background'] = $history ?? '';
        $portfolioData['company_history'] = $history ?? '';
        $portfolioData['company_vision'] = $vision ?? '';
        $portfolioData['company_mission'] = $mission ?? '';
        $portfolioData['prof_ethical_responsibilities'] = $portfolio?->assessment_ethical ?? '';
        $portfolioData['assessment_ethical'] = $portfolio?->assessment_ethical ?? '';
        $portfolioData['things_learned'] = $portfolio?->assessment_learnings ?? '';
        $portfolioData['assessment_learnings'] = $portfolio?->assessment_learnings ?? '';
        $portfolioData['experience_with_people'] = $portfolio?->assessment_experience ?? '';
        $portfolioData['assessment_experience'] = $portfolio?->assessment_experience ?? '';
        $portfolioData['industry_best_practices'] = $portfolio?->assessment_standards ?? '';
        $portfolioData['assessment_standards'] = $portfolio?->assessment_standards ?? '';
        $portfolioData['recommendations'] = $portfolio?->assessment_recommendations ?? '';
        $portfolioData['assessment_recommendations'] = $portfolio?->assessment_recommendations ?? '';
        $portfolioData['advice'] = $portfolio?->assessment_advice ?? '';
        $portfolioData['assessment_advice'] = $portfolio?->assessment_advice ?? '';
        $portfolioData['company_logo_path'] = $logoPath;
        $portfolioData['org_chart_path'] = $orgDoc['file_path'] ?? null;
        $portfolioData['vision_mission_path'] = $vmDoc['file_path'] ?? null;
        $portfolioData['photos'] = $photos->values();
        $portfolioData['company_name'] = $companyName;
        $portfolioData['company_address'] = $companyAddress;

        $identity = $this->identity($internship, ['company_logo_path' => $logoPath]);
        $journals = JournalEntry::where('internship_id', $internship->id)
            ->academic()
            ->when(! $includeUnapprovedJournals, fn ($query) => $query->where('status', 'approved'))
            ->orderBy('date')
            ->orderBy('week_number')
            ->get()
            ->map(fn (JournalEntry $j) => $this->serializeJournal($j));
        $rawAttendance = AttendanceLog::where('internship_id', $internship->id)
            ->orderBy('date')
            ->get();
        $attendance = $rawAttendance
            ->map(fn (AttendanceLog $log) => $this->serializeAttendance($log, $identity))
            ->values()
            ->all();
        $attendance = app(AttendanceDayResolver::class)
            ->mergeFo30Attendance($internship, $attendance, $rawAttendance);
        $attendance = collect($attendance);
        $evaluations = Evaluation::where('internship_id', $internship->id)
            ->where('form_type', '!=', 'faculty_eval')
            ->with(['evaluator.supervisorProfile', 'evaluator.facultyProfile', 'evaluator.studentProfile'])
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (Evaluation $e) => $this->serializeEvaluation($e, $identity));

        $internshipData = $internship->toArray();
        $internshipData['portfolio'] = $portfolioData;
        $internshipData['journals'] = $journals;
        $internshipData['attendance'] = $attendance;
        $internshipData['attendance_logs'] = $attendance;
        $internshipData['company'] = $company;
        $internshipData['evaluations'] = $evaluations;

        $studentPayload = $student
            ? $student->loadMissing(['studentProfile.program', 'studentProfile.department'])
            : $viewer->loadMissing(['studentProfile.program', 'studentProfile.department']);

        return [
            'identity' => $identity,
            'portfolio' => $portfolioData,
            'internship' => $internshipData,
            'user' => $studentPayload,
            'stats' => [
                'journals_count' => $journals->count(),
                'dtr_count' => $attendance->count(),
                'photos_count' => $photos->count(),
                'total_docs_count' => $photos->count(),
            ],
            'photos' => $photos->values(),
            'timezone' => ManilaTime::TZ,
        ];
    }

    public function identity(Internship $internship, array $extras = []): array
    {
        $internship->loadMissing([
            'student.studentProfile.program',
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
            'coordinator.facultyProfile',
        ]);

        $student = $internship->student;
        $profile = $student?->studentProfile;
        $program = $profile?->program?->name
            ?: $profile?->course_name
            ?: $internship->program;
        $ay = $this->firstNonEmpty($internship->school_year, $profile?->school_year, $internship->academic_year);
        $semester = $this->firstNonEmpty($internship->semester, $profile?->semester);
        $start = $internship->start_date;
        $end = $internship->end_date;
        $trainingPeriod = '';
        if ($start && $end) {
            $trainingPeriod = $start->timezone(ManilaTime::TZ)->format('M j, Y')
                .' – '.$end->timezone(ManilaTime::TZ)->format('M j, Y');
        } elseif ($start) {
            $trainingPeriod = $start->timezone(ManilaTime::TZ)->format('M j, Y');
        }

        $supervisor = $internship->supervisor;
        $faculty = $internship->faculty;
        $coordinator = $internship->coordinator;

        $studentName = $this->lastFirst($profile) ?: NameParts::fromProfile($profile);
        $hours = $internship->target_hours;
        if ($hours === null || (float) $hours <= 0) {
            $hours = ProgramRequirementService::targetHoursForProfile($profile);
        }

        return [
            'student_name' => $studentName,
            'student_name_natural' => NameParts::fromProfile($profile),
            'student_number' => $profile?->student_number ?: $student?->student_number,
            'program' => $program,
            'section' => $profile?->section,
            'academic_year' => $ay,
            'semester' => $this->semesterLabel($semester),
            'semester_raw' => $semester,
            'company_name' => $this->firstNonEmpty($internship->company?->company_name, $internship->company?->name),
            'company_address' => $internship->company?->address,
            'company_logo_path' => array_key_exists('company_logo_path', $extras)
                ? $extras['company_logo_path']
                : $this->companyLogoPath($internship),
            'supervisor_name' => $this->lastFirst($supervisor?->supervisorProfile) ?: NameParts::fromProfile($supervisor?->supervisorProfile),
            'supervisor_faculty_number' => $supervisor?->faculty_number,
            'supervisor_position' => $supervisor?->supervisorProfile?->position,
            'faculty_name' => $this->lastFirst($faculty?->facultyProfile) ?: NameParts::fromProfile($faculty?->facultyProfile),
            'coordinator_name' => $this->lastFirst($coordinator?->facultyProfile) ?: NameParts::fromProfile($coordinator?->facultyProfile),
            'training_period' => $trainingPeriod,
            'target_hours' => $hours,
            'student_signature_path' => $student ? SignatureCapture::profilePath($student) : null,
            'supervisor_signature_path' => $supervisor ? SignatureCapture::profilePath($supervisor) : null,
            'faculty_signature_path' => $faculty ? SignatureCapture::profilePath($faculty) : null,
            'timezone' => ManilaTime::TZ,
        ];
    }

    /**
     * HTE logo for this internship only: the intern's authorized company_logo
     * (or logo) document. Never another company's file and never Company::first().
     */
    public function companyLogoPath(Internship $internship): ?string
    {
        return $this->pickCompanyLogoPath($this->documents($internship));
    }

    public function pickCompanyLogoPath(Collection $photos): ?string
    {
        $logoDoc = $photos->first(fn ($d) => in_array($d['type'] ?? '', ['company_logo', 'logo'], true)
            || in_array($d['document_type'] ?? '', ['company_logo', 'logo'], true));
        $path = $logoDoc['file_path'] ?? null;
        if (! filled($path) || ! $this->fileExists((string) $path)) {
            return null;
        }

        return $path;
    }

    /**
     * FO-30 AM/PM columns come from ManilaAttendanceClock::fo30Columns —
     * one shared mapper for every role preview / print / PDF / portfolio.
     *
     * Hours stay on AttendanceLog.hours_rendered (canonical credited hours).
     */
    public function serializeAttendance(AttendanceLog $log, array $identity = []): array
    {
        $inRaw = $log->clock_in ?: $log->am_time_in;
        $inAt = ManilaTime::fromStoredDateAndTime($log->date, $inRaw);
        $manilaDate = ManilaTime::dateString($log->date)
            ?: ManilaTime::manilaDateString($log->date)
            ?: $inAt?->toDateString();

        $columns = ManilaAttendanceClock::fo30Columns($log);

        $validated = $log->status === 'validated';
        $htePath = $log->hte_signature_path
            ?: ($validated ? ($identity['supervisor_signature_path'] ?? null) : null);
        $studentPath = $log->student_signature_path
            ?: ($identity['student_signature_path'] ?? null);

        return [
            'id' => $log->id,
            'date' => $manilaDate,
            'timezone' => ManilaTime::TZ,
            'am_time_in' => $columns['am_time_in'],
            'am_time_out' => $columns['am_time_out'],
            'pm_time_in' => $columns['pm_time_in'],
            'pm_time_out' => $columns['pm_time_out'],
            'am_absent' => false,
            'day_absent' => false,
            'hours_rendered' => $log->hours_rendered !== null ? (float) $log->hours_rendered : null,
            'status' => $log->status,
            'validated' => $validated,
            'validated_at' => $log->validated_at
                ? $log->validated_at->copy()->timezone(ManilaTime::TZ)->toIso8601String()
                : null,
            'hte_signature_path' => $htePath,
            'student_signature_path' => $studentPath,
            'hte_signed_name' => $log->hte_signed_name ?: ($validated ? ($identity['supervisor_name'] ?? null) : null),
            'student_signed_name' => $log->student_signed_name ?: ($identity['student_name'] ?? null),
        ];
    }

    private function serializeJournal(JournalEntry $journal): array
    {
        $date = ManilaTime::manilaDateString($journal->date) ?: ManilaTime::dateString($journal->date);
        $end = $journal->end_date
            ? (ManilaTime::manilaDateString($journal->end_date) ?: ManilaTime::dateString($journal->end_date))
            : null;

        return [
            'id' => $journal->id,
            'week_number' => $journal->week_number,
            'week' => $journal->week_number,
            'date' => $date,
            'end_date' => $end,
            'activities_summary' => $journal->activities_summary,
            'accomplishment' => $journal->activities_summary,
            'challenges' => $journal->challenges,
            'difficulties' => $journal->challenges,
            'learnings' => $journal->learnings,
            'insights' => $journal->learnings,
            'file_path' => $journal->file_path,
            'status' => $journal->status,
        ];
    }

    private function serializeEvaluation(Evaluation $evaluation, array $identity): array
    {
        $responses = is_array($evaluation->responses) ? $evaluation->responses : [];
        if ($evaluation->form_type === 'FO-24') {
            $weights = [
                'c1' => 0.25, 'c2' => 0.125, 'c3' => 0.125, 'c4' => 0.10, 'c5' => 0.10,
                'c6' => 0.10, 'c7' => 0.05, 'c8' => 0.05, 'c9' => 0.05, 'c10' => 0.05,
            ];
            foreach ($weights as $key => $weight) {
                $eqKey = 'eq'.substr($key, 1);
                if (isset($responses[$key]) && is_numeric($responses[$key])) {
                    $responses[$eqKey] = round((float) $responses[$key] * $weight, 2);
                }
            }
        }

        if ($evaluation->form_type === 'FO-22' && $evaluation->average_score !== null) {
            $responses['interpretation'] = $responses['interpretation']
                ?? $this->fo22Interpretation((float) $evaluation->average_score);
        }

        $evaluator = $evaluation->evaluator;
        $evaluatorName = $this->evaluatorDisplayName($evaluation, $identity);
        $signaturePath = $evaluation->signature_path;
        if (! $signaturePath && $evaluator) {
            $signaturePath = SignatureCapture::profilePath($evaluator);
        }

        $submitted = $evaluation->submitted_at
            ? $evaluation->submitted_at->copy()->timezone(ManilaTime::TZ)->toIso8601String()
            : null;

        return [
            'id' => $evaluation->id,
            'internship_id' => $evaluation->internship_id,
            'evaluator_type' => $evaluation->evaluator_type,
            'evaluated_by' => $evaluation->evaluated_by,
            'evaluation_period' => $evaluation->evaluation_period,
            'form_type' => $evaluation->form_type,
            'responses' => $responses,
            'total_score' => $evaluation->total_score,
            'average_score' => $evaluation->average_score,
            'rating' => $evaluation->rating,
            'general_comments' => $evaluation->general_comments,
            'submitted_at' => $submitted,
            'status' => $evaluation->submitted_at ? 'completed' : 'pending',
            'signer_name' => $evaluation->signer_name ?: $evaluatorName,
            'signature_path' => $signaturePath,
            'evaluator_name' => $evaluatorName,
        ];
    }

    private function documents(Internship $internship): Collection
    {
        $typeMap = [
            'Curriculum Vitae (PNC:AA-FO-27)' => 'student_cv',
            'Curriculum Vitae' => 'student_cv',
            'Medical Clearance' => 'medical_result',
            'Psychological Assessment Certificate' => 'psychological_result',
            'Application Letter' => 'application_letter',
            'Recommendation Letter' => 'recommendation_request',
            'Notarized Student Internship Consent Form (PNC:AA-FO-28)' => 'consent_form',
            'Notarized Student Internship Consent Form (PNC: AA-FO-28)' => 'consent_form',
            'Student Internship Acceptance Form (PNC:AA-FO-29)' => 'acceptance_form',
            'Student Internship Acceptance Form (PNC: AA-FO-29)' => 'acceptance_form',
            'Training Plan' => 'training_plan',
            'Internship Training Plan' => 'training_plan',
            'MOA / LOA / TOR' => 'moa_document',
            'Memorandum of Agreement' => 'moa_document',
            'Certificate of Completion' => 'completion_certificate',
            'Certification of Completion' => 'completion_certificate',
            'Daily Time Record' => 'dtr_form',
            'Midterm Evaluation' => 'performance_eval',
            'Final Report' => 'performance_eval',
            'Host Evaluation' => 'hte_evaluation',
            'Internship / OJT Visitation Form' => 'visitation_form',
            'OJT Photos' => 'ojt_photo',
            'Registration Form' => 'registration_form',
            'Student Registration Form' => 'registration_form',
            'Medical Result' => 'medical_result',
            'Psychological Test Result' => 'psychological_result',
            'Recommendation Letter Request' => 'recommendation_request',
            'Internship Consent Form' => 'consent_form',
            'Internship Acceptance Form' => 'acceptance_form',
        ];

        $portfolioUploads = [
            'ojt_photo', 'org_chart', 'company_logo', 'logo', 'vision_mission',
            'company_vision_mission', 'training_certificate', 'training_test_result',
            'training_documentation', 'exam_certificate', 'exam_test_result',
            'exam_documentation', 'dtr_form', 'registration_form',
        ];

        return Document::where('internship_id', $internship->id)
            ->with('attachments')
            ->where(function ($q) use ($portfolioUploads) {
                $q->whereIn('status', ['approved', 'completed'])
                    ->orWhere('current_stage', 'completed')
                    ->orWhereIn('document_type', $portfolioUploads);
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'rejected');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Document $doc) use ($typeMap) {
                $rawType = $doc->document_type ?? 'portfolio_photo';
                $mappedType = $typeMap[$rawType] ?? $rawType;
                $attachment = $doc->attachments->first();
                $filePath = $doc->getAttribute('file_path') ?: $attachment?->file_path;
                $fileName = $doc->file_name ?: $attachment?->file_name;

                if (! $filePath) {
                    return null;
                }

                return [
                    'id' => $doc->id,
                    'internship_id' => $doc->internship_id,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'type' => $mappedType,
                    'document_type' => $rawType,
                    'original_type' => $rawType,
                    'label' => $doc->remarks ?? $fileName,
                    'remarks' => $doc->remarks,
                    'week_number' => $doc->week_number,
                    'created_at' => $doc->created_at,
                    'status' => $doc->status,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveMoa(Internship $internship): ?array
    {
        if ($internship->company_id) {
            $application = InternshipApplication::query()
                ->where('student_id', $internship->student_id)
                ->where('company_id', $internship->company_id)
                ->whereNotNull('moa_path')
                ->where('moa_path', '!=', '')
                ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
                ->latest('id')
                ->first();
            if ($application?->moa_path && $this->fileExists($application->moa_path)) {
                return [
                    'path' => $application->moa_path,
                    'name' => $application->moa_original_name ?: 'Memorandum of Agreement',
                ];
            }

            $request = HteRequest::query()
                ->where('student_id', $internship->student_id)
                ->whereNotNull('moa_path')
                ->where('moa_path', '!=', '')
                ->when($internship->company?->company_name, function ($q) use ($internship) {
                    $q->where('company_name', $internship->company->company_name);
                })
                ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
                ->latest('id')
                ->first();
            if ($request?->moa_path && $this->fileExists($request->moa_path)) {
                return [
                    'path' => $request->moa_path,
                    'name' => $request->moa_original_name ?? 'Memorandum of Agreement',
                ];
            }
        }

        $companyMoa = $internship->company?->moa_file_path;
        if ($companyMoa && $this->fileExists($companyMoa)) {
            return [
                'path' => $companyMoa,
                'name' => 'Memorandum of Agreement',
            ];
        }

        return null;
    }

    private function fileExists(string $path): bool
    {
        return Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path);
    }

    private function lastFirst(?object $profile): string
    {
        if (! $profile) {
            return '';
        }
        $last = trim((string) ($profile->last_name ?? ''));
        $first = trim((string) ($profile->first_name ?? ''));
        if ($last === '' && $first === '') {
            return '';
        }
        $mi = trim((string) ($profile->middle_name ?? ''));
        $middle = $mi !== '' ? ' '.mb_strtoupper(mb_substr($mi, 0, 1)).'.' : '';

        return mb_strtoupper($last).', '.mb_strtoupper($first).$middle;
    }

    private function semesterLabel(mixed $raw): string
    {
        $blob = strtolower(trim((string) $raw));
        if ($blob === '2' || str_contains($blob, '2nd') || str_contains($blob, 'second')) {
            return '2nd';
        }
        if ($blob === '1' || str_contains($blob, '1st') || str_contains($blob, 'first')) {
            return '1st';
        }
        if (str_contains($blob, 'mid') || str_contains($blob, 'summer') || $blob === '3') {
            return 'Midyear';
        }

        return trim((string) $raw);
    }

    private function evaluatorDisplayName(Evaluation $evaluation, array $identity): ?string
    {
        $evaluator = $evaluation->evaluator;
        if ($evaluator) {
            $profile = $evaluator->supervisorProfile
                ?? $evaluator->facultyProfile
                ?? $evaluator->studentProfile;
            $named = $this->lastFirst($profile) ?: NameParts::fromProfile($profile);
            if ($named !== '') {
                return $named;
            }
        }

        return match ($evaluation->evaluator_type) {
            'supervisor' => $identity['supervisor_name'] ?? null,
            'faculty' => $identity['faculty_name'] ?? null,
            default => $evaluation->signer_name,
        };
    }

    private function fo22Interpretation(float $avg): string
    {
        return match (true) {
            $avg >= 4.5 => 'Outstanding',
            $avg >= 3.5 => 'Very Satisfactory',
            $avg >= 2.75 => 'Satisfactory',
            $avg >= 2.0 => 'Unsatisfactory',
            default => 'Poor',
        };
    }

    private function firstNonEmpty(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }
}
