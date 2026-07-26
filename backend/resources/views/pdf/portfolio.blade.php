<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OJT Portfolio – {{ $studentProfile?->full_name }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; background: #fff; }

    /* ─── Page management ─── */
    .page {
        width: 100%;
        padding: 18mm 18mm 14mm 18mm;
        background: #ffffff;
        page-break-after: always;
        min-height: 270mm;
    }
    .page:last-child { page-break-after: avoid; }

    /* ─── Cover page ─── */
    .cover {
        text-align: center;
        padding-top: 30mm;
    }
    .cover .logo { width: 80px; height: 80px; margin-bottom: 10px; }
    .cover .uni-name { font-size: 14pt; font-weight: bold; line-height: 1.4; }
    .cover .college { font-size: 11pt; margin: 4px 0 2px; }
    .cover .address  { font-size: 9pt; color: #555; margin-bottom: 30px; }
    .cover .title { font-size: 18pt; font-weight: bold; text-decoration: underline; margin-bottom: 6px; }
    .cover .subtitle { font-size: 11pt; margin-bottom: 30px; }
    .cover .divider { border-top: 2px solid #000; width: 80%; margin: 0 auto 30px; }
    .cover .info-block { font-size: 11pt; line-height: 2; }
    .cover .info-label { font-weight: bold; display: inline-block; width: 130px; text-align: right; padding-right: 10px; }
    .cover .signature-area { margin-top: 40px; display: inline-block; text-align: center; }
    .cover .signature-area img { max-height: 45px; max-width: 140px; object-fit: contain; display: block; margin: 0 auto 4px; }
    .cover .signature-line { border-top: 1px solid #000; width: 200px; margin: 0 auto 2px; }

    /* ─── Section headers ─── */
    .section-header {
        border-bottom: 2px solid #000;
        padding-bottom: 4px;
        margin-bottom: 10px;
        font-size: 12pt;
        font-weight: bold;
    }

    /* ─── Tables ─── */
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9pt; }
    .data-table th, .data-table td { border: 1px solid #bbb; padding: 4px 6px; vertical-align: top; }
    .data-table th { background: #e8e8e8; font-weight: bold; text-align: center; }
    .data-table .col-date { width: 90px; }
    .data-table .col-time { width: 70px; text-align: center; }
    .data-table .col-hours { width: 60px; text-align: center; }

    /* ─── Journal cards ─── */
    .journal-entry { border: 1px solid #ccc; padding: 8px; margin-bottom: 8px; page-break-inside: avoid; }
    .journal-week { font-weight: bold; font-size: 9.5pt; margin-bottom: 4px; }
    .journal-cols { display: table; width: 100%; }
    .journal-col { display: table-cell; width: 33%; padding-right: 6px; font-size: 8.5pt; vertical-align: top; }
    .journal-col-label { font-weight: bold; font-size: 8pt; color: #333; margin-bottom: 2px; }
    .journal-col-body { white-space: pre-wrap; word-break: break-word; }

    /* ─── Documents list ─── */
    .doc-item { padding: 3px 6px; border-bottom: 1px solid #eee; font-size: 9pt; display: flex; justify-content: space-between; }
    .badge-ok   { background: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 4px; font-size: 8pt; }
    .badge-pend { background: #fef9c3; color: #854d0e; padding: 1px 6px; border-radius: 4px; font-size: 8pt; }

    /* ─── Page numbers ─── */
    @page { margin: 0; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════
     COVER PAGE
═══════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="cover">
        <img class="logo" src="{{ public_path('images/pnc_logo.png') }}" alt="PNC Logo">
        <div class="uni-name">PAMANTASAN NG LUNGSOD NG CALOOCAN</div>
        <div class="college">College of Computing Studies</div>
        <div class="address">Biglang-awa St., cor. Catleya St., Deparo, Caloocan City</div>

        <div class="divider"></div>

        <div class="title">OJT PORTFOLIO</div>
        <div class="subtitle">Student Internship Documentation</div>
        <div class="subtitle">Academic Year {{ $internship->academic_year ?? '—' }}, Semester {{ $internship->semester ?? '—' }}</div>

        <div class="divider"></div>

        <div class="info-block">
            <div><span class="info-label">Student:</span> {{ $studentProfile?->full_name ?? $user->username }}</div>
            <div><span class="info-label">Student No.:</span> {{ $studentProfile?->student_number ?? '—' }}</div>
            <div><span class="info-label">Program:</span> {{ $studentProfile?->program ?? '—' }}</div>
            <div><span class="info-label">Section:</span> {{ $studentProfile?->section ?? '—' }}</div>
            <div><span class="info-label">Company:</span> {{ $company?->name ?? '—' }}</div>
            <div><span class="info-label">OJT Period:</span>
                {{ $internship->start_date ? \Carbon\Carbon::parse($internship->start_date)->format('M d, Y') : '—' }}
                to
                {{ $internship->end_date ? \Carbon\Carbon::parse($internship->end_date)->format('M d, Y') : 'Present' }}
            </div>
            <div><span class="info-label">Hours Rendered:</span>
                {{ number_format($internship->total_hours_rendered ?? 0, 0) }} / {{ $internship->target_hours ?? 486 }} hrs
            </div>
        </div>

        @if($studentSignature)
        <div class="signature-area" style="margin-top: 40px;">
            <img src="{{ $studentSignature }}" alt="Student Signature">
            <div class="signature-line"></div>
            <div style="font-size:9pt; font-weight:bold;">{{ $studentProfile?->full_name }}</div>
            <div style="font-size:8pt; color:#555;">Student-Trainee</div>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     CHAPTER I — Attendance Summary (DTR)
═══════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="section-header">CHAPTER I &nbsp;|&nbsp; Daily Time Record Summary</div>
    <p style="font-size:8.5pt; color:#555; margin-bottom:10px;">Validated attendance records for the OJT period.</p>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-date">Date</th>
                <th class="col-time">AM In</th>
                <th class="col-time">AM Out</th>
                <th class="col-time">PM In</th>
                <th class="col-time">PM Out</th>
                <th class="col-hours">Hours</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php $totalHours = 0; @endphp
            @forelse($attendance as $log)
                @php $totalHours += $log->hours_rendered ?? 0; @endphp
                <tr>
                    <td>{{ $log->date ? \Carbon\Carbon::parse($log->date)->format('M d, Y') : '—' }}</td>
                    <td>{{ $log->am_time_in ? \Carbon\Carbon::parse($log->am_time_in)->format('h:i A') : '—' }}</td>
                    <td>{{ $log->am_time_out ? \Carbon\Carbon::parse($log->am_time_out)->format('h:i A') : '—' }}</td>
                    <td>{{ $log->pm_time_in ? \Carbon\Carbon::parse($log->pm_time_in)->format('h:i A') : '—' }}</td>
                    <td>{{ $log->pm_time_out ? \Carbon\Carbon::parse($log->pm_time_out)->format('h:i A') : '—' }}</td>
                    <td>{{ $log->hours_rendered ? number_format($log->hours_rendered, 2) : '—' }}</td>
                    <td>{{ $log->remarks ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:#888; padding:8px;">No validated attendance records.</td></tr>
            @endforelse
            <tr style="background:#f0f0f0; font-weight:bold;">
                <td colspan="5" style="text-align:right; padding-right:8px;">TOTAL:</td>
                <td>{{ number_format($totalHours, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════
     CHAPTER II — Weekly Journals
═══════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="section-header">CHAPTER II &nbsp;|&nbsp; Weekly Journal Entries (Form 31)</div>
    <p style="font-size:8.5pt; color:#555; margin-bottom:10px;">Approved weekly journal entries submitted during the OJT period.</p>

    @forelse($journals as $journal)
        <div class="journal-entry">
            <div class="journal-week">
                Week {{ $journal->week_number }}
                @if($journal->date)
                    &nbsp;—&nbsp; {{ \Carbon\Carbon::parse($journal->date)->format('F d, Y') }}
                @endif
            </div>
            <div class="journal-cols">
                <div class="journal-col">
                    <div class="journal-col-label">✓ ACCOMPLISHMENT</div>
                    <div class="journal-col-body">{{ $journal->activities_summary ?: '—' }}</div>
                </div>
                <div class="journal-col">
                    <div class="journal-col-label">⚠ DIFFICULTIES ENCOUNTERED</div>
                    <div class="journal-col-body">{{ $journal->challenges ?: '—' }}</div>
                </div>
                <div class="journal-col" style="padding-right:0;">
                    <div class="journal-col-label">💡 NEW LEARNING / INSIGHTS</div>
                    <div class="journal-col-body">{{ $journal->learnings ?: '—' }}</div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted" style="color:#888;">No approved journal entries found.</p>
    @endforelse
</div>

{{-- ═══════════════════════════════════════════════════════
     CHAPTER III — Portfolio Narrative (if saved)
═══════════════════════════════════════════════════════ --}}
@if($portfolio)
<div class="page">
    <div class="section-header">CHAPTER III &nbsp;|&nbsp; Narrative & Reflections</div>

    @if($portfolio->company_background)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Company Background</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->company_background }}</div>
        </div>
    @endif

    @if($portfolio->things_learned)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Things Learned</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->things_learned }}</div>
        </div>
    @endif

    @if($portfolio->experience_with_people)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Experience with People</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->experience_with_people }}</div>
        </div>
    @endif

    @if($portfolio->industry_best_practices)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Industry Best Practices</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->industry_best_practices }}</div>
        </div>
    @endif

    @if($portfolio->recommendations)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Recommendations</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->recommendations }}</div>
        </div>
    @endif

    @if($portfolio->advice)
        <div style="margin-bottom:14px;">
            <div style="font-weight:bold; margin-bottom:4px;">Advice for Future OJT Students</div>
            <div style="white-space:pre-wrap; font-size:9.5pt; color:#222;">{{ $portfolio->advice }}</div>
        </div>
    @endif
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     CHAPTER IV — Submitted Documents Checklist
═══════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="section-header">CHAPTER IV &nbsp;|&nbsp; Document Checklist</div>
    <p style="font-size:8.5pt; color:#555; margin-bottom:10px;">Approved documents submitted by the student during OJT.</p>

    @forelse($documents as $doc)
        <div class="doc-item">
            <span>{{ $doc->document_type }}</span>
            <span class="badge-ok">Approved</span>
        </div>
    @empty
        <p style="color:#888; padding:8px;">No approved documents on record.</p>
    @endforelse

    {{-- Certification signature --}}
    <div style="margin-top: 40px; text-align: center;">
        @if($studentSignature)
            <img src="{{ $studentSignature }}" alt="Signature" style="max-height:45px; max-width:140px; object-fit:contain; display:block; margin:0 auto 4px;">
        @endif
        <div style="border-top:1px solid #000; width:200px; margin:0 auto 3px;"></div>
        <div style="font-weight:bold; font-size:9.5pt;">{{ $studentProfile?->full_name }}</div>
        <div style="font-size:8.5pt; color:#555;">Student-Trainee</div>
        <div style="font-size:8pt; color:#888; margin-top:2px;">
            Generated on {{ now()->format('F d, Y') }}
        </div>
    </div>
</div>

</body>
</html>
