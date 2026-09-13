<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\FacultyProfile;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Hash;

$pw = Hash::make('interntrack123');

// 1. COE Coordinator
$coeCoord = User::firstOrCreate(
    ['email' => 'coe.coord@uc.edu.ph'],
    ['username' => 'COR-COE', 'password' => $pw, 'role' => 'coordinator']
);
FacultyProfile::updateOrCreate(
    ['user_id' => $coeCoord->id],
    [
        'employee_number' => 'COR-COE',
        'first_name' => 'John',
        'last_name' => 'Coordinator (COE)',
        'email' => 'coe.coord@uc.edu.ph',
        'department' => 'BS CpE',
        'college' => 'College of Engineering',
        'position' => 'Coordinator',
        'employment_status' => 'Regular',
        'synced_at' => now(),
    ]
);

// 2. COE Faculty
$coeFaculty = User::firstOrCreate(
    ['email' => 'coe.faculty@uc.edu.ph'],
    ['username' => 'FAC-COE', 'password' => $pw, 'role' => 'faculty']
);
FacultyProfile::updateOrCreate(
    ['user_id' => $coeFaculty->id],
    [
        'employee_number' => 'FAC-COE',
        'first_name' => 'Jane',
        'last_name' => 'Faculty (COE)',
        'email' => 'coe.faculty@uc.edu.ph',
        'department' => 'BS CpE',
        'college' => 'College of Engineering',
        'position' => 'OJT Teacher',
        'employment_status' => 'Regular',
        'synced_at' => now(),
    ]
);

// 3. COE Student
$coeStudent = User::firstOrCreate(
    ['email' => 'coe.student@uc.edu.ph'],
    ['username' => 'STU-COE', 'password' => $pw, 'role' => 'student']
);
StudentProfile::updateOrCreate(
    ['user_id' => $coeStudent->id],
    [
        'student_number' => 'STU-COE',
        'first_name' => 'COE',
        'last_name' => 'Student',
        'email' => 'coe.student@uc.edu.ph',
        'program' => 'Bachelor of Science in Computer Engineering',
        'department' => 'BS CpE',
        'year_level' => 4,
        'target_hours' => 240,
        'is_enrolled' => true,
        'synced_at' => now(),
    ]
);

echo "COE Accounts created!\n";
