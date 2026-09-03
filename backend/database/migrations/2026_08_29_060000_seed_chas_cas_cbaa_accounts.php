<?php

use Database\Seeders\AcademicCollegeAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('departments') || ! Schema::hasTable('programs')) {
            return;
        }

        (new AcademicCollegeAccountsSeeder())->run();
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $facultyNumbers = [
            'COR-CHAS-001', 'FAC-CHAS-001',
            'COR-CAS-001', 'FAC-CAS-001',
            'COR-CBAA-001', 'FAC-CBAA-001',
        ];
        $studentNumbers = ['2300603', '2300604', '2300605', '2300606', '2300607'];

        $userIds = \App\Models\User::withTrashed()
            ->where(function ($query) use ($facultyNumbers, $studentNumbers) {
                $query->whereIn('faculty_number', $facultyNumbers)
                    ->orWhereIn('student_number', $studentNumbers);
            })
            ->pluck('id');

        \App\Models\Internship::whereIn('student_id', $userIds)->delete();
        \App\Models\StudentProfile::whereIn('user_id', $userIds)->delete();
        \App\Models\FacultyProfile::whereIn('user_id', $userIds)->delete();
        \App\Models\FacultySectionAssignment::whereIn('faculty_user_id', $userIds)->delete();
        \App\Models\User::withTrashed()->whereIn('id', $userIds)->forceDelete();
    }
};
