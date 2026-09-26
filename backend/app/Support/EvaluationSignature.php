<?php

namespace App\Support;

use App\Models\Evaluation;

/**
 * Authoritative signer of a submitted evaluation form (FO-24, FO-03, ...).
 *
 * The signer is always the user who submitted the form (evaluations.evaluated_by):
 * the signature captured on the form, else that evaluator's saved My Signature.
 * Never the internship's current supervisor, a company's first supervisor, or
 * the signed-in viewer, so every surface (Student Portfolio, Faculty /
 * Coordinator / Director / Supervisor previews, Print) shows the same signature.
 */
final class EvaluationSignature
{
    public static function path(Evaluation $evaluation): ?string
    {
        $owner = (int) ($evaluation->evaluated_by ?? 0);
        $stored = $evaluation->signature_path;

        if (filled($stored)) {
            // A saved-profile signature belongs to exactly one user; a stored
            // reference to someone else's is never rendered as this signer's.
            if (! preg_match('#^signatures/(\d+)_processed\.png$#', $stored, $m) || (int) $m[1] === $owner) {
                return $stored;
            }
        }

        return $owner > 0 ? SignatureCapture::profilePathForUserId($owner) : null;
    }

    /** "LAST, FIRST M." of the submitting evaluator, from already-loaded relations only. */
    public static function evaluatorName(Evaluation $evaluation): ?string
    {
        if (! $evaluation->relationLoaded('evaluator') || ! $evaluation->evaluator) {
            return null;
        }

        $evaluator = $evaluation->evaluator;
        foreach (['supervisorProfile', 'facultyProfile', 'studentProfile'] as $relation) {
            if (! $evaluator->relationLoaded($relation) || ! $evaluator->{$relation}) {
                continue;
            }
            $named = NameParts::lastFirst($evaluator->{$relation}) ?: NameParts::fromProfile($evaluator->{$relation});
            if ($named !== '') {
                return $named;
            }
        }

        return null;
    }

    /** Relations evaluatorName() reads; eager-load them with the evaluations. */
    public static function evaluatorRelations(string $prefix = ''): array
    {
        return array_map(
            fn (string $relation) => $prefix.$relation,
            ['evaluator.supervisorProfile', 'evaluator.facultyProfile', 'evaluator.studentProfile'],
        );
    }

    /**
     * Serialize the authoritative signature and signer name with each loaded
     * Evaluation model (resolved_signature_path, evaluator_name).
     *
     * @param  iterable<\App\Models\Internship|Evaluation>  $models
     */
    public static function present(iterable $models): void
    {
        foreach ($models as $model) {
            if ($model instanceof Evaluation) {
                $model->append(['resolved_signature_path', 'evaluator_name']);

                continue;
            }
            if (is_object($model) && method_exists($model, 'relationLoaded') && $model->relationLoaded('evaluations')) {
                self::present($model->evaluations);
            }
        }
    }
}
