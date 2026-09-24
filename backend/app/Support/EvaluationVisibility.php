<?php

namespace App\Support;

use App\Models\Evaluation;
use App\Models\User;

/**
 * Student visibility of supervisor-submitted evaluation forms.
 *
 * - FO-24 Student Internship Performance Evaluation: the Student sees that it
 *   was completed, but the details stay hidden until the internship's assigned
 *   Faculty releases them.
 * - FO-03 HTE Evaluation of the University Internship Program: completion only,
 *   until the Director releases it.
 *
 * Every Student-facing payload that contains evaluations must pass through
 * forStudent(), so changing URLs or parameters cannot reveal unreleased data.
 * Staff views (Faculty, Coordinator, Director, Supervisor) are unaffected.
 */
final class EvaluationVisibility
{
    /** form_type => role that authorizes Student visibility. */
    public const RELEASE_AUTHORITY = [
        'FO-24' => 'faculty',
        'FO-03' => 'director',
    ];

    public static function authorityFor(?string $formType): ?string
    {
        return self::RELEASE_AUTHORITY[$formType] ?? null;
    }

    public static function isReleased(Evaluation|array $evaluation): bool
    {
        $value = $evaluation instanceof Evaluation
            ? $evaluation->released_to_student_at
            : ($evaluation['released_to_student_at'] ?? null);

        return $value !== null && $value !== '';
    }

    /** True when the Student may not see this evaluation's details yet. */
    public static function isHiddenFromStudent(Evaluation|array $evaluation): bool
    {
        $formType = $evaluation instanceof Evaluation ? $evaluation->form_type : ($evaluation['form_type'] ?? null);

        return self::authorityFor($formType) !== null && ! self::isReleased($evaluation);
    }

    public static function viewerIsStudent(?User $viewer): bool
    {
        return $viewer !== null && $viewer->role === 'student';
    }

    /**
     * Student-safe representation. Released or student-authored forms are
     * returned as-is (plus release metadata); unreleased FO-24/FO-03 keep only
     * completion metadata: no scores, ratings, answers, comments, or signatures.
     */
    public static function forStudent(Evaluation|array $evaluation): array
    {
        $data = $evaluation instanceof Evaluation ? $evaluation->toArray() : $evaluation;
        $formType = $data['form_type'] ?? null;
        $authority = self::authorityFor($formType);

        $meta = [
            'release_authority' => $authority,
            'released' => $authority === null || self::isReleased($data),
            'details_locked' => false,
        ];

        if (! self::isHiddenFromStudent($data)) {
            return $data + $meta;
        }

        $submittedAt = $data['submitted_at'] ?? null;

        return [
            'id' => $data['id'] ?? null,
            'internship_id' => $data['internship_id'] ?? null,
            'form_type' => $formType,
            'evaluator_type' => $data['evaluator_type'] ?? null,
            'evaluation_period' => $data['evaluation_period'] ?? null,
            'submitted_at' => $submittedAt,
            // Completion status only — a draft the supervisor has not submitted stays pending.
            'status' => ($submittedAt !== null && $submittedAt !== '') ? 'completed' : 'pending',
            'release_authority' => $authority,
            'released' => false,
            'details_locked' => true,
            'locked_message' => ($submittedAt === null || $submittedAt === '')
                ? 'Not yet completed by the industry supervisor.'
                : ($authority === 'faculty'
                    ? 'Completed. Details are available after your Faculty Supervisor releases them.'
                    : 'Completed. Details are available after the Director releases them.'),
        ];
    }

    /**
     * @param  iterable<Evaluation|array>  $evaluations
     * @return array<int, array>
     */
    public static function listForStudent(iterable $evaluations): array
    {
        $out = [];
        foreach ($evaluations as $evaluation) {
            $out[] = self::forStudent($evaluation);
        }

        return $out;
    }

    /** Apply forStudent() only when the viewer is a Student. */
    public static function listForViewer(iterable $evaluations, ?User $viewer): array
    {
        if (self::viewerIsStudent($viewer)) {
            return self::listForStudent($evaluations);
        }

        $out = [];
        foreach ($evaluations as $evaluation) {
            $out[] = $evaluation instanceof Evaluation ? $evaluation->toArray() : $evaluation;
        }

        return $out;
    }
}
