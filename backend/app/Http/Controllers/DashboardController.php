<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $student = $request->user();

        $hoursRendered = (float) $student->attendances()->sum('hours');
        $daysPresent = $student->attendances()->whereNotNull('time_out')->count();
        $journalEntries = $student->logbookEntries()->count();

        $requiredDocs = count(Document::REQUIRED_TYPES);
        $submittedTypes = $student->documents()
            ->whereIn('document_type', Document::REQUIRED_TYPES)
            ->distinct()
            ->count('document_type');
        $approvedTypes = $student->documents()
            ->whereIn('document_type', Document::REQUIRED_TYPES)
            ->where('status', 'approved')
            ->distinct()
            ->count('document_type');

        $requiredHours = max(1, (int) $student->required_hours);
        $completionPercent = round(min(100, $hoursRendered / $requiredHours * 100));

        $latestEvaluation = $student->evaluations()
            ->whereNotNull('score')
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first();

        // Weekly hours for the dashboard chart (ISO week, latest 8 weeks).
        $weeklyHours = $student->attendances()
            ->selectRaw('YEARWEEK(date, 3) as yearweek, SUM(hours) as total_hours')
            ->groupBy('yearweek')
            ->orderBy('yearweek')
            ->get()
            ->values()
            ->map(fn ($row, $i) => [
                'week' => 'Wk '.($i + 1),
                'hours' => (float) $row->total_hours,
            ])
            ->slice(-8)
            ->values();

        $recentActivity = collect()
            ->concat($student->attendances()->latest()->limit(5)->get()->map(fn ($a) => [
                'date' => $a->created_at,
                'activity' => 'Attendance Log - '.$a->date->format('M d, Y'),
                'type' => 'Attendance',
                'status' => ucfirst($a->status),
            ]))
            ->concat($student->logbookEntries()->latest()->limit(5)->get()->map(fn ($e) => [
                'date' => $e->created_at,
                'activity' => 'Journal Entry - '.$e->entry_date->format('M d, Y'),
                'type' => 'Logbook',
                'status' => ucfirst($e->status),
            ]))
            ->concat($student->documents()->latest()->limit(5)->get()->map(fn ($d) => [
                'date' => $d->created_at,
                'activity' => $d->document_type,
                'type' => 'Document',
                'status' => ucfirst($d->status),
            ]))
            ->concat($student->evaluations()->latest()->limit(5)->get()->map(fn ($ev) => [
                'date' => $ev->created_at,
                'activity' => $ev->evaluation_type,
                'type' => 'Evaluation',
                'status' => ucfirst($ev->status),
            ]))
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return response()->json([
            'student' => $student,
            'hours_rendered' => $hoursRendered,
            'required_hours' => (int) $student->required_hours,
            'completion_percent' => $completionPercent,
            'days_present' => $daysPresent,
            'journal_entries' => $journalEntries,
            'docs_submitted' => $submittedTypes,
            'docs_required' => $requiredDocs,
            'docs_approved' => $approvedTypes,
            'document_compliance_percent' => round($submittedTypes / $requiredDocs * 100),
            'evaluation_score' => $latestEvaluation?->score !== null ? (float) $latestEvaluation->score : null,
            'evaluation_max_score' => $latestEvaluation ? (float) $latestEvaluation->max_score : null,
            'weekly_hours' => $weeklyHours,
            'recent_activity' => $recentActivity,
        ]);
    }
}
