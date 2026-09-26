<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PNC:AA-FO-30 Daily Time Record</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
        .meta { text-align: right; font-size: 10px; margin: 0 0 4px; }
        .header { width: 100%; }
        .header td { vertical-align: middle; }
        .header .side { width: 82px; text-align: center; }
        .header .center { text-align: center; padding: 0 8px; }
        .header img { max-width: 78px; max-height: 78px; width: auto; height: auto; object-fit: contain; }
        .logo-slot { width: 78px; height: 78px; }
        .univ { color: #0B5D2A; font-size: 20px; line-height: 1.1; }
        h1 { text-align: center; font-size: 12px; background: #cccccc; padding: 6px 8px; margin: 8px 0 10px; }
        .info { margin-bottom: 10px; }
        .info .row { padding: 4px 0; }
        .info .label { display: inline-block; min-width: 120px; }
        .info .val { border-bottom: 1px solid #000; display: inline-block; min-width: 280px; font-weight: bold; text-transform: uppercase; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px; text-align: center; font-size: 8.5px; }
        table.grid th { font-weight: bold; }
        .weekend { background: #f9f9f9; }
        .sig-table { width: 100%; margin-top: 12px; }
        .sig-table td { width: 50%; vertical-align: top; padding: 0 12px; }
        .sig-cap { font-weight: bold; font-size: 10px; margin-bottom: 6px; }
        .sig-mark { border-bottom: 1px solid #000; min-height: 58px; text-align: center; padding: 4px; }
        .sig-mark img { max-height: 40px; max-width: 160px; background: transparent; }
        .sig-name { font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .sig-hint { font-size: 9px; text-align: center; margin-top: 3px; }
        .privacy { font-size: 7px; margin-top: 10px; text-align: justify; }
        .hte-sig img { max-height: 18px; max-width: 70px; background: transparent; }
    </style>
</head>
<body>
@php
    $fo30 = $fo30 ?? [];
    $studentName = $fo30['student_name'] ?? '';
    $programName = $fo30['program'] ?? '';
    $companyName = $fo30['company_name'] ?? '';
    $supervisorName = $fo30['supervisor_name'] ?? '';
@endphp
<div class="meta">PNC:AA-FO-30 rev.1 09022025</div>
<table class="header">
    <tr>
        <td class="side">
            @if (!empty($university_logo))
                <img src="{{ $university_logo }}" alt="UC Logo">
            @endif
        </td>
        <td class="center">
            <div>Republic of the Philippines</div>
            <div class="univ">Pamantasan ng Cabuyao</div>
            <div>(UNIVERSITY OF CABUYAO)</div>
            <div><strong>Academic Affairs Division</strong></div>
            <div>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</div>
        </td>
        {{-- HTE logo removed from FO-30; the empty cell keeps the header centered. --}}
        <td class="side"><div class="logo-slot"></div></td>
    </tr>
</table>
<h1>STUDENT INTERNSHIP DAILY TIME RECORD (DTR) FORM</h1>
<div class="info">
    <div class="row"><span class="label">Name of Student:</span> <span class="val">{{ $studentName !== '' ? $studentName : 'Not available' }}</span></div>
    <div class="row"><span class="label">Program:</span> <span class="val" style="font-weight:normal;text-transform:none">{{ $programName !== '' ? $programName : 'Not available' }}</span></div>
    <div class="row"><span class="label">Company/School:</span> <span class="val" style="font-weight:normal;text-transform:none">{{ $companyName !== '' ? $companyName : 'Not available' }}</span></div>
</div>
<table class="grid">
    <thead>
        <tr>
            <th rowspan="2">Date</th>
            <th colspan="2">AM</th>
            <th colspan="2">PM</th>
            <th rowspan="2">Daily<br>Hours</th>
            <th rowspan="2">HTE<br>Signature</th>
        </tr>
        <tr>
            <th>Time in</th>
            <th>Time Out</th>
            <th>Time in</th>
            <th>Time Out</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        @php
            $weekend = in_array((int) ($row['weekday'] ?? -1), [0, 6], true);
            $emptyMark = $weekend ? '—' : '';
        @endphp
        <tr class="{{ $weekend ? 'weekend' : '' }}">
            <td>{{ \Carbon\Carbon::parse($row['date'])->timezone('UTC')->format('M d, D') }}</td>
            <td>{{ $row['am_time_in'] ? \App\Support\OfficialFormAsset::clockLabel($row['am_time_in']) : $emptyMark }}</td>
            <td>{{ $row['am_time_out'] ? \App\Support\OfficialFormAsset::clockLabel($row['am_time_out']) : $emptyMark }}</td>
            <td>{{ $row['pm_time_in'] ? \App\Support\OfficialFormAsset::clockLabel($row['pm_time_in']) : $emptyMark }}</td>
            <td>{{ $row['pm_time_out'] ? \App\Support\OfficialFormAsset::clockLabel($row['pm_time_out']) : $emptyMark }}</td>
            <td>{{ $row['hours_rendered'] !== null && $row['hours_rendered'] !== '' ? number_format((float) $row['hours_rendered'], 2) : '' }}</td>
            <td class="hte-sig">
                @if (!empty($row['validated']) && !empty($row['hte_signature']))
                    <img src="{{ $row['hte_signature'] }}" alt="">
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7">No attendance logs for this period.</td></tr>
    @endforelse
    </tbody>
</table>
<table class="sig-table">
    <tr>
        <td>
            <div class="sig-cap">Prepared by:</div>
            <div class="sig-mark">
                @if (!empty($student_signature))
                    <img src="{{ $student_signature }}" alt="">
                @endif
                <div class="sig-name">{{ $studentName }}</div>
            </div>
            <div class="sig-hint">Signature over printed name of Student Intern</div>
        </td>
        <td>
            <div class="sig-cap">Verified by:</div>
            <div class="sig-mark">
                @if (!empty($supervisor_signature))
                    <img src="{{ $supervisor_signature }}" alt="">
                @endif
                <div class="sig-name">{{ $supervisorName }}</div>
            </div>
            <div class="sig-hint">Signature over printed name</div>
            <div class="sig-hint">HTE IN-CHARGE/HEAD/SUPERVISOR</div>
        </td>
    </tr>
</table>
<div class="privacy">
    I agree to the collection and processing of my data for the purpose of recording my daily time record to satisfy the requirements of the Internship Program. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, and that I am required to provide truthful information.
</div>
</body>
</html>
