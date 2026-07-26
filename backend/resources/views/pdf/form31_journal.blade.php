<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Weekly Internship Journal – PNC:AA-FO-31</title>
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
        page-break-after: always;
    }
    .page:last-child { page-break-after: avoid; }

    /* ─── Header Section ─────────────────────────────── */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
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
    .divider { border-top: 2px solid #005a2b; margin: 5px 0 0 0; }

    /* ─── Gray Title Bar ─────────────────────── */
    .title-bar {
        background-color: #e6e6e6;
        text-align: center;
        font-size: 13px;
        font-weight: bold;
        padding: 8px 0;
        margin: 0 0 10px 0;
        border: 1px solid #000;
        border-top: none;
        letter-spacing: 0.5px;
    }

    /* ─── Student Info Section ───────────────────────── */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11px; }
    .info-table td { padding: 3px 2px; vertical-align: bottom; }
    
    .info-label { font-weight: bold; white-space: nowrap; }
    .info-value { border-bottom: 1px solid #000; padding-left: 5px; }

    /* ─── Journal Table ──────────────────────── */
    .journal-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        font-size: 10px;
        table-layout: fixed;
    }
    .journal-table th, .journal-table td {
        border: 1px solid #000;
        padding: 8px;
        vertical-align: top;
    }
    .journal-table th {
        background-color: #e6e6e6;
        font-weight: bold;
        text-align: center;
        font-size: 10px;
        padding: 6px;
    }
    
    .col-accomplishment { width: 34%; }
    .col-difficulties   { width: 33%; }
    .col-insights       { width: 33%; }

    .entry-content { 
        white-space: pre-wrap; 
        word-wrap: break-word; 
        min-height: 350px; 
        font-size: 10px; 
        line-height: 1.5; 
    }

    /* ─── Signature Section ──────────────────────────── */
    .signature-section { 
        width: 100%; 
        margin-top: 15px; 
        border-collapse: collapse; 
        page-break-inside: avoid;
    }
    .signature-section td { width: 50%; padding: 5px 15px; vertical-align: bottom; }
    
    .sig-label { font-weight: bold; font-size: 11px; margin-bottom: 25px; }
    
    .sig-img-wrap { height: 45px; text-align: center; margin-bottom: 2px; }
    .sig-img-wrap img { max-height: 40px; max-width: 150px; }
    
    .sig-name { 
        border-top: 1px solid #000; 
        margin-top: 5px; 
        padding-top: 3px; 
        font-size: 11px; 
        font-weight: bold; 
        text-align: center; 
        text-transform: uppercase;
    }
    .sig-role { font-size: 10px; color: #000; text-align: center; margin-top: 2px; }

    /* ─── Privacy Notice ─────────────────────── */
    .privacy-notice {
        margin-top: 15px;
        font-size: 9px;
        line-height: 1.4;
        text-align: justify;
        border-top: 1px dashed #999;
        padding-top: 8px;
        color: #333;
        page-break-inside: avoid;
    }
    .privacy-checkbox {
        margin-top: 6px;
        font-size: 10px;
        font-weight: bold;
        display: flex;
        align-items: center;
    }
    
    /* ─── Footer ─────────────────────────────── */
    .footer {
        text-align: center;
        font-size: 10px;
        font-style: italic;
        color: #005a2b;
        margin-top: 15px;
        padding-top: 5px;
        border-top: 2px solid #005a2b;
        font-weight: bold;
    }
</style>
</head>
<body>

@foreach($journals as $journal)
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
                <div>PNC:AA-FO-31</div>
                <div>rev.0</div>
                <div>02012023</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- Gray Title Bar --}}
    <div class="title-bar">WEEKLY STUDENT INTERNSHIP JOURNAL</div>

    {{-- Student Info --}}
    <table class="info-table">
        <tr>
            <td class="info-label" style="width:90px;">Student Intern:</td>
            <td class="info-value" style="width:250px; font-weight: bold;">{{ $studentProfile?->full_name }}</td>
            <td class="info-label" style="width:60px; padding-left: 15px;">Program:</td>
            <td class="info-value">{{ $studentProfile?->program }}</td>
            <td class="info-label" style="width:60px; padding-left: 15px;">Week No.:</td>
            <td class="info-value" style="width:40px; text-align:center;">{{ $journal->week_number }}</td>
        </tr>
        <tr>
            <td class="info-label">Date:</td>
            <td class="info-value">{{ $journal->date ? \Carbon\Carbon::parse($journal->date)->format('F d, Y') : '' }}</td>
            <td class="info-label" style="padding-left: 15px;">Company:</td>
            <td class="info-value" colspan="3">{{ $company?->name }}</td>
        </tr>
    </table>

    {{-- Journal Content Table --}}
    <table class="journal-table">
        <thead>
            <tr>
                <th class="col-accomplishment">ACCOMPLISHMENT</th>
                <th class="col-difficulties">DIFFICULTIES ENCOUNTERED</th>
                <th class="col-insights">NEW LEARNING / INSIGHTS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="entry-content">{{ $journal->activities_summary }}</div>
                </td>
                <td>
                    <div class="entry-content">{{ $journal->challenges }}</div>
                </td>
                <td>
                    <div class="entry-content">{{ $journal->learnings }}</div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Signature --}}
    <table class="signature-section">
        <tr>
            <td>
                <div class="sig-label">Submitted by:</div>
                <div class="sig-img-wrap">
                    @if($studentSignature)
                        <img src="{{ $studentSignature }}" alt="Student Signature">
                    @endif
                </div>
                <div class="sig-name">{{ $studentProfile?->full_name }}</div>
                <div class="sig-role">Student-Trainee</div>
            </td>
            <td>
                <div class="sig-label">Noted By:</div>
                <div class="sig-img-wrap">&nbsp;</div>
                <div class="sig-name">{{ $internship->supervisor?->supervisorProfile?->full_name ?? '' }}</div>
                <div class="sig-role">Immediate Supervisor / HTE Representative</div>
            </td>
        </tr>
    </table>

    {{-- Data Privacy Notice --}}
    <div class="privacy-notice">
        I understand and agree that any information collected through the PLC-Dagupan Internship program shall be handled in accordance with the PLCDN privacy policy.
        I also acknowledge that the data shall be used strictly for academic evaluation and internship management purposes.
    </div>
    <div class="privacy-checkbox">
        <span style="display:inline-block; width:12px; height:12px; border:1px solid #000; margin-right:5px; vertical-align:middle;"></span> I AGREE
    </div>

    {{-- Footer --}}
    <div class="footer">
        "Dangal ng Bayan"
    </div>

</div>
@endforeach

</body>
</html>
