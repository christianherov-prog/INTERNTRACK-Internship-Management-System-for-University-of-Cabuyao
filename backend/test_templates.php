<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\OjtRequirementTemplate;
use App\Models\Document;

echo "--- TEMPLATES IN DB ---\n";
$templates = OjtRequirementTemplate::with('targets')->get();
foreach ($templates as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | Active: " . ($t->is_active ? 'YES' : 'NO') . "\n";
    foreach ($t->targets as $tg) {
        echo "   -> Target: {$tg->target_type} = {$tg->target_id}\n";
    }
}
echo "Total Active Templates: " . OjtRequirementTemplate::where('is_active', true)->count() . "\n\n";

echo "--- STUDENTS ---\n";
$students = User::where('role', 'student')->with(['studentProfile.program', 'activeInternship'])->get();
foreach ($students as $s) {
    $p = $s->studentProfile;
    $internship = $s->activeInternship;
    echo "Student: {$s->username} ({$s->name}) | Program: " . ($p?->program?->code ?? $p?->program?->name ?? 'N/A') . " | Section: {$p?->section}\n";
    if ($internship) {
        echo "   Internship ID: {$internship->id} | Status: {$internship->status}\n";
        $docs = Document::where('internship_id', $internship->id)->get();
        echo "   Total Doc Rows in DB: " . $docs->count() . "\n";
        foreach ($docs as $d) {
            echo "      - Doc ID {$d->id}: Type='{$d->document_type}', Status='{$d->status}', Name='{$d->file_name}'\n";
        }
    }
}
