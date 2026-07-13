<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    /**
     * Read-only: evaluations are authored by supervisors, not students.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $evaluations = $student->evaluations()
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->get();

        $scored = $evaluations->whereNotNull('score');
        $latest = $scored->first();

        return response()->json([
            'evaluations' => $evaluations,
            'stats' => [
                'current_score' => $latest?->score !== null ? (float) $latest->score : null,
                'max_score' => $latest ? (float) $latest->max_score : null,
                'forms_received' => $evaluations->where('status', 'received')->count(),
                'forms_total' => $evaluations->count(),
                'pending' => $evaluations->where('status', 'pending')->count(),
                'breakdown' => $latest ? [
                    'work_quality' => $latest->work_quality !== null ? (float) $latest->work_quality : null,
                    'punctuality' => $latest->punctuality !== null ? (float) $latest->punctuality : null,
                    'communication' => $latest->communication !== null ? (float) $latest->communication : null,
                    'initiative' => $latest->initiative !== null ? (float) $latest->initiative : null,
                ] : null,
            ],
        ]);
    }
}
