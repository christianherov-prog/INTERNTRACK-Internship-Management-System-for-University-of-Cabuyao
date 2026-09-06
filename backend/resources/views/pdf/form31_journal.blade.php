<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PNC:AA-FO-31 Weekly Student Internship Journal</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
        .meta { text-align: right; font-size: 9px; margin-bottom: 8px; }
        h1 { text-align: center; font-size: 14px; background: #e5e7eb; padding: 8px; margin: 0 0 10px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header .univ { color: #0B5D2A; font-size: 18px; font-weight: bold; }
        .box { border: 1px solid #333; margin-bottom: 10px; }
        .row { display: table; width: 100%; border-bottom: 1px solid #333; }
        .row:last-child { border-bottom: 0; }
        .cell { display: table-cell; padding: 6px 8px; width: 50%; vertical-align: top; }
        .label { font-size: 8px; letter-spacing: 0.04em; color: #444; }
        .value { font-size: 12px; font-weight: bold; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #333; padding: 8px; vertical-align: top; }
        table.grid th { background: #f3f4f6; font-size: 10px; }
        table.grid td { min-height: 90px; }
        .sig { margin-top: 18px; }
        .sig img { max-height: 42px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $studentName = trim(($studentProfile->last_name ?? '') . ', ' . ($studentProfile->first_name ?? ''));
    $programName = $studentProfile?->program?->name
        ?? $internship->program
        ?? '';
@endphp
@foreach ($journals as $journal)
<div class="{{ !$loop->last ? 'page-break' : '' }}">
    <div class="meta">PNC:AA-FO-31 rev.0 02012023</div>
    <div class="header">
        <div>Republic of the Philippines</div>
        <div class="univ">University of Cabuyao</div>
        <div>Pamantasan ng Cabuyao</div>
    </div>
    <h1>WEEKLY STUDENT INTERNSHIP JOURNAL</h1>
    <div class="box">
        <div class="row">
            <div class="cell">
                <div class="label">STUDENT INTERN</div>
                <div class="value">{{ $studentName !== ',' ? $studentName : 'Not available' }}</div>
            </div>
            <div class="cell">
                <div class="label">PROGRAM</div>
                <div class="value">{{ $programName !== '' ? $programName : 'Not available' }}</div>
            </div>
        </div>
        <div class="row">
            <div class="cell">
                <div class="label">DATE</div>
                <div class="value">{{ optional($journal->date)->toDateString() ?? $journal->date }}</div>
            </div>
            <div class="cell">
                <div class="label">WEEK</div>
                <div class="value">WEEK {{ $journal->week_number ?? $journal->entry_number }}</div>
            </div>
        </div>
    </div>
    <table class="grid">
        <thead>
            <tr>
                <th>ACCOMPLISHMENT</th>
                <th>DIFFICULTIES ENCOUNTERED</th>
                <th>NEW LEARNING / INSIGHTS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $journal->activities_summary }}</td>
                <td>{{ $journal->challenges }}</td>
                <td>{{ $journal->learnings }}</td>
            </tr>
        </tbody>
    </table>
    @if (!empty($studentSignature))
        <div class="sig">
            <div class="label">Student signature</div>
            <img src="{{ $studentSignature }}" alt="Student signature">
        </div>
    @endif
</div>
@endforeach
</body>
</html>
