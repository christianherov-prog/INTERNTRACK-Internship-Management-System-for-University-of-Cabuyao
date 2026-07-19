<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #14261a; margin: 0; }
        .frame {
            border: 10px solid #0a5c2e;
            padding: 36px 48px;
            min-height: 520px;
            position: relative;
        }
        .inner { border: 2px solid #2da058; padding: 28px 36px; min-height: 460px; text-align: center; }
        .eyebrow { letter-spacing: 4px; font-size: 12px; color: #1a7a3f; text-transform: uppercase; margin-bottom: 8px; }
        h1 { font-size: 34px; margin: 0 0 6px; color: #0a5c2e; }
        h2 { font-size: 18px; font-weight: normal; margin: 0 0 28px; color: #3d5c46; }
        .name { font-size: 28px; font-weight: bold; color: #0a5c2e; margin: 18px 0 8px; }
        .meta { font-size: 13px; color: #3d5c46; line-height: 1.7; margin-top: 18px; }
        .footer { margin-top: 42px; display: table; width: 100%; }
        .col { display: table-cell; width: 50%; text-align: center; font-size: 12px; }
        .line { border-top: 1px solid #0a5c2e; width: 70%; margin: 0 auto 6px; }
        .note { margin-top: 24px; font-size: 11px; color: #7da488; }
    </style>
</head>
<body>
<div class="frame">
    <div class="inner">
        <div class="eyebrow">University of Cabuyao · PALD</div>
        <h1>Certificate of Completion</h1>
        <h2>Internship Program — INTERNTRACK</h2>

        <p>This certifies that</p>
        <div class="name">{{ $studentName }}</div>
        <p>Student No. {{ $studentNo }}</p>

        <div class="meta">
            of the program <strong>{{ $program }}</strong><br>
            has successfully completed the internship for <strong>{{ $term }}</strong><br>
            at <strong>{{ $company }}</strong><br>
            with a total of <strong>{{ number_format($hours, 1) }}</strong> validated hours rendered.
        </div>

        <div class="footer">
            <div class="col">
                <div class="line"></div>
                {{ $coordName }}<br>
                Internship Coordinator
            </div>
            <div class="col">
                <div class="line"></div>
                Issued {{ $issued }}<br>
                INTERNTRACK System
            </div>
        </div>

        <div class="note">Generated from live internship records — not a manually uploaded file.</div>
    </div>
</div>
</body>
</html>
