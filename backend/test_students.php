<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$internships = \App\Models\Internship::with('student.studentProfile')->get();
echo "Internships: " . $internships->count() . "\n";

$students = [];
foreach($internships as $i) {
    if ($i->student) {
        $name = $i->student->studentProfile ? trim($i->student->studentProfile->first_name . ' ' . $i->student->studentProfile->last_name) : $i->student->username;
        $students[] = [
            'id' => $i->student->id,
            'name' => $name,
            'section' => $i->student->studentProfile->section ?? null
        ];
    }
}
echo "Students: " . count($students) . "\n";
