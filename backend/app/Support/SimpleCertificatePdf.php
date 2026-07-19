<?php

namespace App\Support;

/**
 * Minimal landscape PDF writer for completion certificates.
 * Used when barryvdh/laravel-dompdf is not installed (e.g. PHP < 8.2 locally).
 */
final class SimpleCertificatePdf
{
    public static function download(array $data, string $filename)
    {
        $pdf = self::build($data);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    public static function build(array $d): string
    {
        $w = 842; // A4 landscape points
        $h = 595;

        $lines = [
            [28, 'CERTIFICATE OF COMPLETION'],
            [16, 'INTERNTRACK · Internship Management System'],
            [12, 'This certifies that'],
            [22, (string) ($d['studentName'] ?? 'Student')],
            [11, 'Student No. '.($d['studentNo'] ?? '—').' · '.($d['program'] ?? '—')],
            [12, 'has successfully completed the internship program'],
            [14, 'Host Training Establishment: '.($d['company'] ?? '—')],
            [12, 'Term: '.($d['term'] ?? '—').' · Hours rendered: '.number_format((float) ($d['hours'] ?? 0), 1)],
            [11, 'Issued: '.($d['issued'] ?? now()->format('F j, Y'))],
            [11, 'Certified by: '.($d['coordName'] ?? 'Internship Coordinator')],
        ];

        $content = "BT\n";
        $y = 480;
        foreach ($lines as [$size, $text]) {
            $escaped = self::escape($text);
            $content .= "/F1 {$size} Tf\n";
            $content .= "50 {$y} Td\n";
            $content .= "({$escaped}) Tj\n";
            $content .= "0 -".($size + 14)." Td\n";
            $y -= ($size + 14);
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
        $objects[] = "<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
