<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PNC:AA-FO-31 Weekly Student Internship Journal</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
        .sheet { display: flex; flex-direction: column; min-height: 265mm; }
        .meta { text-align: right; font-size: 10px; margin: 0 0 4px; line-height: 1.2; }
        .header { display: table; width: 100%; margin-bottom: 8px; }
        .header .side { display: table-cell; width: 82px; vertical-align: middle; text-align: center; }
        .header .center { display: table-cell; vertical-align: middle; text-align: center; padding: 0 8px; }
        .header .univ { color: #0B5D2A; font-size: 20px; line-height: 1.1; }
        h1 { text-align: center; font-size: 12px; background: #cccccc; padding: 6px 8px; margin: 0 0 8px; }
        .box { border: 1px solid #000; margin-bottom: 8px; }
        .row { display: table; width: 100%; border-bottom: 1px solid #000; }
        .row:last-child { border-bottom: 0; }
        .cell { display: table-cell; padding: 6px 8px; width: 50%; vertical-align: middle; }
        .cell.left { width: 55%; border-right: 1px solid #000; }
        .cell.right { width: 45%; }
        .label { font-size: 10px; white-space: nowrap; }
        .value { font-size: 11px; font-weight: bold; text-transform: uppercase; overflow-wrap: anywhere; word-break: break-word; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid col { width: 33.333%; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 8px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; white-space: pre-wrap; }
        table.grid th { background: #fff; font-size: 10px; text-align: center; vertical-align: middle; }
        table.grid td { height: 88mm; font-size: 10px; }
        .sig { margin-top: 12px; text-align: center; }
        .sig-box { border: 1px solid #000; width: 280px; margin: 0 auto 12px; }
        .sig-box .cap { border-bottom: 1px solid #000; font-weight: bold; padding: 3px 0; }
        .sig-box .mark { min-height: 58px; border-bottom: 1px solid #000; padding: 4px; }
        .sig-box img { max-height: 40px; max-width: 180px; width: auto; height: auto; background: transparent; }
        .sig-box .hint { font-size: 9px; font-weight: bold; padding: 3px 0; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $identity = $identity ?? [];
    $studentName = $identity['student_name'] ?? '';
    $programName = $identity['program'] ?? '';
@endphp
@foreach ($journals as $journal)
@php
    $jDate = data_get($journal, 'date');
    $jEnd = data_get($journal, 'end_date');
    $jWeek = data_get($journal, 'week_number', data_get($journal, 'week'));
    $accomplishment = data_get($journal, 'activities_summary', data_get($journal, 'accomplishment'));
    $difficulties = data_get($journal, 'challenges', data_get($journal, 'difficulties'));
    $insights = data_get($journal, 'learnings', data_get($journal, 'insights'));
@endphp
<div class="sheet {{ !$loop->last ? 'page-break' : '' }}">
    <div class="meta">PNC:AA-FO-31 rev.0 02012023</div>
    <div class="header">
        <div class="side">
            @if (!empty($university_logo))
                <img src="{{ $university_logo }}" alt="UC Logo" style="max-width:78px;max-height:78px;">
            @endif
        </div>
        <div class="center">
            <div>Republic of the Philippines</div>
            <div class="univ">Pamantasan ng Cabuyao</div>
            <div>(UNIVERSITY OF CABUYAO)</div>
            <div><strong>Academic Affairs Division</strong></div>
        </div>
        {{-- HTE logo removed from FO-31; the empty slot keeps the header centered. --}}
        <div class="side"><div style="width:78px;height:78px;"></div></div>
    </div>
    <h1>WEEKLY STUDENT INTERNSHIP JOURNAL</h1>
    <div class="box">
        <div class="row">
            <div class="cell left">
                <span class="label">STUDENT INTERN:</span>
                <span class="value">{{ $studentName !== '' ? $studentName : 'Not available' }}</span>
            </div>
            <div class="cell right">
                <span class="label">PROGRAM:</span>
                <span class="value">{{ $programName !== '' ? $programName : 'Not available' }}</span>
            </div>
        </div>
        <div class="row">
            <div class="cell left">
                <span class="label">DATE:</span>
                <span class="value">{{ \App\Support\Fo31JournalPresenter::dateRange($jDate, $jEnd) }}</span>
            </div>
            <div class="cell right">
                <span class="label">WEEK:</span>
                <span class="value">{{ \App\Support\Fo31JournalPresenter::weekLabel($jWeek) }}</span>
            </div>
        </div>
    </div>
    <table class="grid">
        <colgroup>
            <col><col><col>
        </colgroup>
        <thead>
            <tr>
                <th>ACCOMPLISHMENT</th>
                <th>DIFFICULTIES ENCOUNTERED</th>
                <th>NEW LEARNING / INSIGHTS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $accomplishment }}</td>
                <td>{{ $difficulties }}</td>
                <td>{{ $insights }}</td>
            </tr>
        </tbody>
    </table>
    <div class="sig">
        <div class="sig-box">
            <div class="cap">STUDENT-TRAINEE</div>
            <div class="mark">
                @if (!empty($student_signature))
                    <img src="{{ $student_signature }}" alt="">
                @endif
                <div class="value">{{ $studentName }}</div>
            </div>
            <div class="hint">(signature over printed name)</div>
        </div>
    </div>
</div>
@endforeach
</body>
</html>
