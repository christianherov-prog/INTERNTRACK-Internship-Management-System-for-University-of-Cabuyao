<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $records = $student->attendances()->orderByDesc('date')->get();

        return response()->json([
            'attendance' => $records,
            'stats' => [
                'days_present' => $student->attendances()->whereNotNull('time_out')->count(),
                'hours_logged' => (float) $student->attendances()->sum('hours'),
                'validated_days' => $student->attendances()->where('status', 'validated')->count(),
                'hours_remaining' => max(0, (int) $student->required_hours - (float) $student->attendances()->sum('hours')),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user();

        $data = $request->validate([
            'date' => [
                'required', 'date',
                Rule::unique('attendances')->where('student_id', $student->id),
            ],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $data['hours'] = $this->computeHours($data['time_in'], $data['time_out'] ?? null);

        $attendance = $student->attendances()->create($data);

        return response()->json(['attendance' => $attendance], 201);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorizeOwnership($request, $attendance);

        $data = $request->validate([
            'date' => [
                'sometimes', 'date',
                Rule::unique('attendances')->where('student_id', $request->user()->id)->ignore($attendance->id),
            ],
            'time_in' => ['sometimes', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $timeIn = $data['time_in'] ?? substr($attendance->time_in, 0, 5);
        $timeOut = array_key_exists('time_out', $data)
            ? $data['time_out']
            : ($attendance->time_out ? substr($attendance->time_out, 0, 5) : null);

        $data['hours'] = $this->computeHours($timeIn, $timeOut);

        $attendance->update($data);

        return response()->json(['attendance' => $attendance]);
    }

    public function destroy(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorizeOwnership($request, $attendance);

        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted.']);
    }

    private function computeHours(string $timeIn, ?string $timeOut): float
    {
        if (! $timeOut) {
            return 0;
        }

        $minutes = Carbon::createFromFormat('H:i', $timeIn)
            ->diffInMinutes(Carbon::createFromFormat('H:i', $timeOut));

        return round(max(0, $minutes) / 60, 2);
    }

    private function authorizeOwnership(Request $request, Attendance $attendance): void
    {
        abort_if($attendance->student_id !== $request->user()->id, 403, 'Not your record.');
    }
}
