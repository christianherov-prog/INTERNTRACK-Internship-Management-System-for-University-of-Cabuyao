<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Company;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Main database seeder.
 *
 * Login credentials (all accounts use password: interntrack123):
 *   Admin       → username: ADMIN-MISD-001
 *   Director    → username: DIR-1001
 *   Coordinator → username: COR-1001
 *   Faculty     → username: FAC-1001
 *   Student     → username: 2300600 (Valinado), 2300590 (Taac-Taac), 2300592 (Montealegre)
 */
class DatabaseSeeder extends Seeder
{
    private function ensureDepartment(string $name): int
    {
        $name = trim($name);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'DEPT');

        $department = Department::firstOrCreate(
            ['name' => $name],
            ['code' => $code, 'is_active' => true]
        );

        return $department->id;
    }

    private function ensureProgram(string $name, int $departmentId): int
    {
        $name = trim($name);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'PROG');

        $program = Program::firstOrCreate(
            ['name' => $name],
            ['department_id' => $departmentId, 'code' => $code, 'is_active' => true]
        );

        return $program->id;
    }

    public function run(): void
    {
        $pw = Hash::make('interntrack123');
        $misdDepartmentId = $this->ensureDepartment('Management Information Systems Department');
        $paldDepartmentId = $this->ensureDepartment('Placement, Alumni, & Linkages Department');
        $ccsDepartmentId = $this->ensureDepartment('College of Computing Studies');

        // ─── 1. Staff users (faculty_number = employee/faculty number) ──────────────
        $admin    = User::updateOrCreate(['faculty_number' => 'ADMIN-MISD-001'], ['email' => 'misd.admin@uc.edu.ph',     'password' => $pw, 'role' => 'admin',       'is_active' => true]);
        $director = User::updateOrCreate(['faculty_number' => 'DIR-1001'],       ['email' => 'g.oloresisimo@uc.edu.ph', 'password' => $pw, 'role' => 'director',    'is_active' => true]);
        $coord    = User::updateOrCreate(['faculty_number' => 'COR-1001'],       ['email' => 'a.quiatchon@uc.edu.ph',   'password' => $pw, 'role' => 'coordinator', 'is_active' => true]);
        $faculty  = User::updateOrCreate(['faculty_number' => 'FAC-1001'],       ['email' => 'm.bicuna@uc.edu.ph',       'password' => $pw, 'role' => 'faculty',     'is_active' => true]);

        // ─── 2. Staff faculty profiles ────────────────────────────────────────
        FacultyProfile::updateOrCreate(['user_id' => $admin->id], [
            'faculty_number'    => 'ADMIN-MISD-001',
            'first_name'        => 'MISD',
            'middle_name'       => null,
            'last_name'         => 'Administrator',
            'email'             => 'misd.admin@uc.edu.ph',
            'contact_number'    => '09175557800',
            'department_id'     => $misdDepartmentId,
            'position'          => 'MISD Administrator',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $director->id], [
            'faculty_number'    => 'DIR-1001',
            'first_name'        => 'Gina',
            'middle_name'       => 'M.',
            'last_name'         => 'Oloresisimo',
            'email'             => 'g.oloresisimo@uc.edu.ph',
            'contact_number'    => '09175557892',
            'department_id'     => $paldDepartmentId,
            'position'          => 'PALD Director',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $coord->id], [
            'faculty_number'    => 'COR-1001',
            'first_name'        => 'Arcelito',
            'middle_name'       => 'C.',
            'last_name'         => 'Quiatchon',
            'email'             => 'a.quiatchon@uc.edu.ph',
            'contact_number'    => '09175557891',
            'department_id'     => $ccsDepartmentId,
            'position'          => 'CCS Coordinator',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $faculty->id], [
            'faculty_number'    => 'FAC-1001',
            'first_name'        => 'Marvin',
            'middle_name'       => 'M.',
            'last_name'         => 'Bicuña',
            'email'             => 'm.bicuna@uc.edu.ph',
            'contact_number'    => '09175557891',
            'department_id'     => $ccsDepartmentId,
            'position'          => 'CCS Faculty',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        // ─── 3. Companies (created before student accounts so demo internships can link) ─
        Company::firstOrCreate(['company_name' => 'TechCorp PH'], [
            'address'        => 'Alabang, Muntinlupa',
            'industry'       => 'Information Technology',
            'contact_person' => 'Ms. Rivera',
            'contact_email'  => 'hr@techcorp.ph',
            'contact_number' => '028001234',
            'moa_status'     => 'active',
            'moa_start_date' => '2024-01-01',
            'moa_expiry_date'=> '2026-12-31',
            'slots_available'=> 10,
        ]);
        Company::firstOrCreate(['company_name' => 'Accenture PH'], [
            'address'        => 'BGC, Taguig',
            'industry'       => 'IT Consulting',
            'contact_person' => 'Mr. Lim',
            'contact_email'  => 'hr@accenture.ph',
            'contact_number' => '028009876',
            'moa_status'     => 'active',
            'moa_start_date' => '2024-03-01',
            'moa_expiry_date'=> '2026-02-28',
            'slots_available'=> 15,
        ]);

        // ─── 4. Faculty accounts + section assignments ────────────────────────
        $this->call(FacultySectionAssignmentSeeder::class);

        // ─── 5. Student accounts (2300600, 2300590, 2300592) ──────────────────
        $this->call(StudentAccountsSeeder::class);

        // ─── 6. Announcements ─────────────────────────────────────────────────
        Announcement::firstOrCreate(
            ['title' => 'Welcome to InternTrack!'],
            ['created_by' => $coord->id, 'content' => 'Get started by browsing available MOA companies and selecting a supervisor.', 'target_role' => 'student', 'is_pinned' => true]
        );
        Announcement::firstOrCreate(
            ['title' => 'Orientation Schedule'],
            ['created_by' => $coord->id, 'content' => 'Internship orientation will be held soon. Please wait for further announcements.', 'target_role' => 'all', 'is_pinned' => false]
        );

        $this->command->info('');
        $this->command->info('✅ INTERNTRACK database seeded successfully!');
        $this->command->info('─────────────────────────────────────────────────────────────');
        $this->command->info('  ROLE          USERNAME/STUDENT NO.   PASSWORD');
        $this->command->info('─────────────────────────────────────────────────────────────');
        $this->command->info('  Admin         ADMIN-MISD-001         interntrack123');
        $this->command->info('  Director      DIR-1001               interntrack123');
        $this->command->info('  Coordinator   COR-1001               interntrack123');
        $this->command->info('  Faculty       FAC-1001               interntrack123');
        $this->command->info('  Student (Valinado)    2300600        interntrack123 (Fresh/Pending)');
        $this->command->info('  Student (Taac-Taac)   2300590        interntrack123 (Fresh/Pending)');
        $this->command->info('  Student (Montealegre) 2300592        interntrack123 (Populated)');
        $this->command->info('─────────────────────────────────────────────────────────────');
    }
}
