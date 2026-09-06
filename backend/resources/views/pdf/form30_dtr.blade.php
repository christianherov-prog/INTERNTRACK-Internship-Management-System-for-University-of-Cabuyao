<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PNC:AA-FO-30 Daily Time Record</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
        .meta { text-align: right; font-size: 9px; margin-bottom: 8px; }
        h1 { text-align: center; font-size: 14px; background: #e5e7eb; padding: 8px; margin: 0 0 10px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header .univ { color: #0B5D2A; font-size: 18px; font-weight: bold; }
        .box { border: 1px solid #333; margin-bottom: 10px; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px; text-align: center; }
        th { background: #f3f4f6; }
        .sig img { max-height: 42px; }
        .muted { color: #666; }
    </style>
</head>
<body>
@php
    $studentName = trim(($studentProfile->last_name ?? '') . ', ' . ($studentProfile->first_name ?? ''));
    $programName = $studentProfile?->program?->name ?? $internship->program ?? '';
    $companyName = $company?->company_name ?? '';
@endphp
<div class="meta">PNC:AA-FO-30 Daily Time Record</div>
<div class="header">
    <div>Republic of the Philippines</div>
    <div class="univ">University of Cabuyao</div>
    <div>Pamantasan ng Cabuyao</div>
</div>
<h1>STUDENT INTERNSHIP DAILY TIME RECORD</h1>
<div class="box">
    <strong>Student Intern:</strong> {{ $studentName !== ',' ? $studentName : 'Not available' }}<br>
    <strong>Program:</strong> {{ $programName !== '' ? $programName : 'Not available' }}<br>
    <strong>Host Training Establishment:</strong> {{ $companyName !== '' ? $companyName : 'Not available' }}<br>
    <strong>Period:</strong> {{ $month }}
</div>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Hours</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ optional($log->date)->toDateString() ?? $log->date }}</td>
                <td>{{ $log->time_in ?? '—' }}</td>
                <td>{{ $log->time_out ?? '—' }}</td>
                <td>{{ $log->hours_rendered ?? '—' }}</td>
                <td>{{ $log->status }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No attendance logs for this month.</td></tr>
        @endforelse
    </tbody>
</table>
<p>
    @if (!empty($studentSignature))
        <span class="sig">Student: <img src="{{ $studentSignature }}" alt="Student signature"></span>
    @endif
    @if (!empty($supervisorSignature))
        <span class="sig">Supervisor: <img src="{{ $supervisorSignature }}" alt="Supervisor signature"></span>
    @endif
</p>
</body>
</html>
