<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Time Record – PNC:AA-FO-30</title>
<style>
    /* Reset and Base Styles */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        color: #000;
        background: #ffffff;
    }

    .page {
        width: 100%;
        padding: 45px 56px 30px 56px; /* Matches ~0.5in margins */
        background: #ffffff;
    }

    /* ─── Header Section ─────────────────────────────── */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2px;
    }
    .header-table td { vertical-align: middle; }
    
    .logo-cell { width: 85px; text-align: left; }
    .logo-cell img { width: 75px; height: 75px; }
    
    .title-cell { text-align: center; }
    .title-cell .republic { font-size: 11px; line-height: 1.2; margin-bottom: 2px; }
    .title-cell .uni-name { font-size: 14px; font-weight: bold; line-height: 1.2; letter-spacing: 0.2px; }
    .title-cell .uni-alt { font-size: 11px; font-style: italic; line-height: 1.2; margin-top: 1px; }
    .title-cell .office { font-size: 11px; font-style: italic; line-height: 1.2; margin-top: 1px; }
    .title-cell .uni-address { font-size: 10px; line-height: 1.2; margin-top: 1px; }
    
    .doc-code-cell { width: 110px; text-align: left; font-size: 9px; line-height: 1.4; vertical-align: top; padding-top: 5px; }

    /* Divider */
    .divider { border-top: 2px solid #005a2b; margin: 5px 0 10px 0; }

    /* Form Title */
    .form-title {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        text-decoration: underline;
        margin: 5px 0 2px 0;
        letter-spacing: 0.5px;
    }
    .form-code {
        text-align: center;
        font-size: 10px;
        margin-bottom: 12px;
    }

    /* ─── Student Info Section ───────────────────────── */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        font-size: 11px;
    }
    .info-table td { padding: 3px 2px; vertical-align: bottom; }
    
    .info-label { font-weight: bold; white-space: nowrap; }
    .info-value { border-bottom: 1px solid #000; text-align: left; padding-left: 5px; }

    /* ─── DTR Log Table ──────────────────────────── */
    .dtr-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        font-size: 10px;
    }
    .dtr-table th, .dtr-table td {
        border: 1px solid #000;
        padding: 4px 2px;
        text-align: center;
        vertical-align: middle;
    }
    .dtr-table th { 
        background-color: #e6e6e6; 
        font-weight: bold; 
        font-size: 10px; 
    }
    
    /* Column Widths */
    .dtr-table .col-date { width: 14%; }
    .dtr-table .col-time { width: 12%; }
    .dtr-table .col-hours { width: 10%; }
    .dtr-table .col-hte { width: 16%; }

    tr.weekend td { background-color: #f9f9f9; color: #666; font-style: italic; }

    /* Totals Row */
    .totals-row td { 
        font-weight: bold; 
        background-color: #f2f2f2; 
        padding-top: 5px;
        padding-bottom: 5px;
    }

    /* ─── Signatures Section ─────────────────────────── */
    .signature-section {
        width: 100%;
        margin-top: 15px;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    .signature-section td { width: 50%; padding: 5px 15px; text-align: center; vertical-align: bottom; }
    .sig-label { font-weight: bold; font-size: 11px; text-align: left; margin-bottom: 25px; }
    
    .sig-img-wrap { height: 45px; text-align: center; margin-bottom: 2px; }
    .sig-img-wrap img { max-height: 40px; max-width: 150px; }
    
    .sig-name { 
        border-top: 1px solid #000; 
        margin-top: 5px; 
        padding-top: 3px; 
        font-size: 11px; 
        font-weight: bold; 
        text-transform: uppercase;
    }
    .sig-role { font-size: 10px; color: #000; margin-top: 2px; }

    /* ─── Data Privacy Notice ─────────────────────── */
    .privacy-notice {
        margin-top: 15px;
        font-size: 8px;
        line-height: 1.3;
        text-align: justify;
        border-top: 1px dashed #999;
        padding-top: 8px;
        color: #333;
        page-break-inside: avoid;
    }
</style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('images/pnc_logo.png') }}" alt="PNC Logo">
            </td>
            <td class="title-cell">
                <div class="republic">Republic of the Philippines</div>
                <div class="uni-name">PAMANTASAN NG LUNGSOD NG CALOOCAN</div>
                <div class="uni-alt">(Pamantasan ng Caloocan)</div>
                <div class="office">Office of Academic Affairs</div>
                <div class="uni-address">Biglang-awa St., cor. Catleya St., Deparo, Caloocan City</div>
            </td>
            <td class="doc-code-cell">
                <div>PNC:AA-FO-30</div>
                <div>rev.1</div>
                <div>09022025</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="form-title">STUDENT INTERNSHIP DAILY TIME RECORD</div>
    <div class="form-code">(PNC:AA-FO-30)</div>

    {{-- Student Info Grid --}}
    <table class="info-table">
        <tr>
            <td class="info-label" style="width:140px;">Name of Student-Trainee:</td>
            <td class="info-value" colspan="3" style="font-weight: bold;">{{ $studentProfile?->full_name }}</td>
            <td class="info-label" style="width:45px; padding-left: 15px;">Month:</td>
            <td class="info-value" style="width:120px;">{{ $month }}</td>
        </tr>
        <tr>
            <td class="info-label">Program:</td>
            <td class="info-value">{{ $studentProfile?->program }}</td>
            <td class="info-label" style="width:40px; padding-left: 15px;">Major:</td>
            <td class="info-value">{{ $studentProfile?->course_name }}</td>
            <td class="info-label" style="width:50px; padding-left: 15px;">Section:</td>
            <td class="info-value" style="width:80px;">{{ $studentProfile?->section }}</td>
        </tr>
        <tr>
            <td class="info-label">Company/School:</td>
            <td class="info-value" colspan="5">{{ $company?->name }}</td>
        </tr>
    </table>

    {{-- DTR Log Table --}}
    <table class="dtr-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-date">Date</th>
                <th colspan="2">AM</th>
                <th colspan="2">PM</th>
                <th rowspan="2" class="col-hours">Total<br>Hours</th>
                <th rowspan="2" class="col-hte">HTE<br>Signature</th>
            </tr>
            <tr>
                <th class="col-time">Time In</th>
                <th class="col-time">Time Out</th>
                <th class="col-time">Time In</th>
                <th class="col-time">Time Out</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalHours = 0;
                $logMap = $logs->keyBy(fn($l) => $l->date->format('Y-m-d'));
                $startOfMonth = \Carbon\Carbon::parse($month . '-01');
                $daysInMonth = $startOfMonth->daysInMonth;
            @endphp

            @for ($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date    = $startOfMonth->copy()->day($day);
                    $dateKey = $date->format('Y-m-d');
                    $log     = $logMap[$dateKey] ?? null;
                    $isWeekend = $date->isWeekend();
                    $totalHours += $log?->hours_rendered ?? 0;
                @endphp
                <tr class="{{ $isWeekend ? 'weekend' : '' }}">
                    <td>{{ $date->format('M d, D') }}</td>
                    <td>{{ $log?->am_time_in ? \Carbon\Carbon::parse($log->am_time_in)->format('h:i A') : ($isWeekend ? '—' : '') }}</td>
                    <td>{{ $log?->am_time_out ? \Carbon\Carbon::parse($log->am_time_out)->format('h:i A') : ($isWeekend ? '—' : '') }}</td>
                    <td>{{ $log?->pm_time_in ? \Carbon\Carbon::parse($log->pm_time_in)->format('h:i A') : ($isWeekend ? '—' : '') }}</td>
                    <td>{{ $log?->pm_time_out ? \Carbon\Carbon::parse($log->pm_time_out)->format('h:i A') : ($isWeekend ? '—' : '') }}</td>
                    <td>{{ $log?->hours_rendered ? number_format($log->hours_rendered, 2) : '' }}</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor

            <tr class="totals-row">
                <td colspan="5" style="text-align:right; padding-right:15px;">TOTAL HOURS THIS MONTH:</td>
                <td>{{ number_format($totalHours, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Signatures --}}
    <table class="signature-section">
        <tr>
            <td>
                <div class="sig-label">Prepared by:</div>
                <div class="sig-img-wrap">
                    @if($studentSignature)
                        <img src="{{ $studentSignature }}" alt="Student Signature">
                    @endif
                </div>
                <div class="sig-name">{{ $studentProfile?->full_name }}</div>
                <div class="sig-role">Student-Trainee</div>
            </td>
            <td>
                <div class="sig-label">Verified by:</div>
                <div class="sig-img-wrap">
                    @if($supervisorSignature)
                        <img src="{{ $supervisorSignature }}" alt="Supervisor Signature">
                    @endif
                </div>
                <div class="sig-name">{{ $supervisorProfile?->full_name }}</div>
                <div class="sig-role">Immediate Supervisor / HTE Representative</div>
            </td>
        </tr>
    </table>

    {{-- Data Privacy Notice --}}
    <div class="privacy-notice">
        <strong>IMPORTANT NOTICE:</strong> This form contains personal data collected and processed in accordance with the Data Privacy Act of 2012 (R.A. 10173).
        The information provided will be used solely for the purpose of monitoring and evaluating the student's internship performance.
        By signing this form, the signatories consent to the collection, processing, and storage of the data herein.
    </div>

</div>
</body>
</html>
