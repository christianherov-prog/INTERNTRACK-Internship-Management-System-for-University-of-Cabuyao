<?php

use Illuminate\Support\Facades\Hash;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Models\StudentProfile;

$dept = Department::firstOrCreate(['code' => 'COE'], ['name' => 'College of Engineering', 'is_active' => true]);
$coeProg = Program::firstOrCreate(['code' => 'COE'], ['name' => 'Computer Engineering', 'department_id' => $dept->id, 'is_active' => true]);
$bsitProg = Program::where('code', 'BSIT')->first();

$u1 = User::create([
    'student_number' => '2300601',
    'email' => '2300601@student.edu.ph',
    'password' => Hash::make('password'),
    'role' => 'student',
    'is_active' => true,
    'must_change_password' => false
]);
StudentProfile::create([
    'user_id' => $u1->id,
    'student_number' => '2300601',
    'first_name' => 'Alice',
    'last_name' => 'Smith',
    'program_id' => $bsitProg->id,
    'department_id' => $bsitProg->department_id,
    'year_level' => 4,
    'section' => '4A',
    'sex' => 'Female'
]);

$u2 = User::create([
    'student_number' => '2300602',
    'email' => '2300602@student.edu.ph',
    'password' => Hash::make('password'),
    'role' => 'student',
    'is_active' => true,
    'must_change_password' => false
]);
StudentProfile::create([
    'user_id' => $u2->id,
    'student_number' => '2300602',
    'first_name' => 'Bob',
    'last_name' => 'Johnson',
    'program_id' => $coeProg->id,
    'department_id' => $coeProg->department_id,
    'year_level' => 4,
    'section' => '4B',
    'sex' => 'Male'
]);
echo "Accounts Created!\n";
