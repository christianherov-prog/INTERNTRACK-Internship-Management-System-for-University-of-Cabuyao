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
    private function ensureDepartment(string $name, ?string $code = null): int
    {
        $name = trim($name);
        $code = $code ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'DEPT');

        $department = Department::firstOrCreate(
            ['name' => $name],
            ['code' => $code, 'is_active' => true]
        );

        return $department->id;
    }

    private function ensureProgram(string $name, int $departmentId, ?string $code = null): int
    {
        $name = trim($name);
        $code = $code ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'PROG');

        $program = Program::firstOrCreate(
            ['name' => $name],
            ['department_id' => $departmentId, 'code' => $code, 'is_active' => true]
        );

        return $program->id;
    }

    public function run(): void
    {
        $pw = Hash::make('interntrack123');
        $misdDepartmentId = $this->ensureDepartment('Management Information Systems Department', 'MISD');
        $paldDepartmentId = $this->ensureDepartment('Placement, Alumni, & Linkages Department', 'PALD');
        $ccsDepartmentId = $this->ensureDepartment('College of Computing Studies', 'CCS');
        $this->ensureProgram('Bachelor of Science in Information Technology', $ccsDepartmentId, 'BSIT');
        $this->ensureProgram('Bachelor of Science in Computer Science', $ccsDepartmentId, 'BSCS');

        $coedDepartmentId = $this->ensureDepartment('College of Education', 'COED');
        $this->ensureProgram('Bachelor of Secondary Education', $coedDepartmentId, 'BSED');
        $this->ensureProgram('Bachelor of Elementary Education', $coedDepartmentId, 'BEED');

        $coeDepartmentId = $this->ensureDepartment('College of Engineering', 'COE');
        $this->ensureProgram('Bachelor of Science in Civil Engineering', $coeDepartmentId, 'BSCE');
        $this->ensureProgram('Bachelor of Science in Computer Engineering', $coeDepartmentId, 'BSCPE');

        $this->call(AcademicCollegesSeeder::class);
        $this->call(ProgramHteRequirementsSeeder::class);

        // ─── 1. Staff users (faculty_number = employee/faculty number) ──────────────
        $admin    = User::updateOrCreate(['faculty_number' => 'ADMIN-MISD-001'], ['email' => 'misd.admin@uc.edu.ph',     'password' => $pw, 'role' => 'admin',       'is_active' => true]);
        $director = User::updateOrCreate(['faculty_number' => 'DIR-1001'],       ['email' => 'g.oloresisimo@uc.edu.ph', 'password' => $pw, 'role' => 'director',    'is_active' => true]);
        
        $coordCcs   = User::updateOrCreate(['faculty_number' => 'COR-CCS-001'],  ['email' => 'a.quiatchon@uc.edu.ph',   'password' => $pw, 'role' => 'coordinator', 'is_active' => true]);
        $facultyCcs = User::updateOrCreate(['faculty_number' => 'FAC-CCS-001'],  ['email' => 'm.bicuna@uc.edu.ph',      'password' => $pw, 'role' => 'faculty',     'is_active' => true]);

        $coordCoed   = User::updateOrCreate(['faculty_number' => 'COR-COED-001'], ['email' => 'coord.coed@uc.edu.ph',   'password' => $pw, 'role' => 'coordinator', 'is_active' => true]);
        $facultyCoed = User::updateOrCreate(['faculty_number' => 'FAC-COED-001'], ['email' => 'faculty.coed@uc.edu.ph', 'password' => $pw, 'role' => 'faculty',     'is_active' => true]);

        $coordCoe    = User::updateOrCreate(['faculty_number' => 'COR-COE-001'],  ['email' => 'coord.coe@uc.edu.ph',    'password' => $pw, 'role' => 'coordinator', 'is_active' => true]);
        $facultyCoe  = User::updateOrCreate(['faculty_number' => 'FAC-COE-001'],  ['email' => 'faculty.coe@uc.edu.ph',  'password' => $pw, 'role' => 'faculty',     'is_active' => true]);

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

        FacultyProfile::updateOrCreate(['user_id' => $coordCcs->id], [
            'faculty_number'    => 'COR-CCS-001',
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

        FacultyProfile::updateOrCreate(['user_id' => $facultyCcs->id], [
            'faculty_number'    => 'FAC-CCS-001',
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

        FacultyProfile::updateOrCreate(['user_id' => $coordCoed->id], [
            'faculty_number'    => 'COR-COED-001',
            'first_name'        => 'COED',
            'middle_name'       => null,
            'last_name'         => 'Coordinator',
            'email'             => 'coord.coed@uc.edu.ph',
            'contact_number'    => '09175550001',
            'department_id'     => $coedDepartmentId,
            'position'          => 'COED Coordinator',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $facultyCoed->id], [
            'faculty_number'    => 'FAC-COED-001',
            'first_name'        => 'COED',
            'middle_name'       => null,
            'last_name'         => 'Faculty',
            'email'             => 'faculty.coed@uc.edu.ph',
            'contact_number'    => '09175550002',
            'department_id'     => $coedDepartmentId,
            'position'          => 'COED Faculty',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $coordCoe->id], [
            'faculty_number'    => 'COR-COE-001',
            'first_name'        => 'COE',
            'middle_name'       => null,
            'last_name'         => 'Coordinator',
            'email'             => 'coord.coe@uc.edu.ph',
            'contact_number'    => '09175550003',
            'department_id'     => $coeDepartmentId,
            'position'          => 'COE Coordinator',
            'employment_status' => 'Regular',
            'synced_at'         => now(),
        ]);

        FacultyProfile::updateOrCreate(['user_id' => $facultyCoe->id], [
            'faculty_number'    => 'FAC-COE-001',
            'first_name'        => 'COE',
            'middle_name'       => null,
            'last_name'         => 'Faculty',
            'email'             => 'faculty.coe@uc.edu.ph',
            'contact_number'    => '09175550004',
            'department_id'     => $coeDepartmentId,
            'position'          => 'COE Faculty',
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

        // ─── 3b. Supervisor demo account (Patrick Bateman at TechCorp PH) ────
        $supervisor = User::updateOrCreate(['email' => 'patrick.bateman@techcorp.ph'], [
            'faculty_number' => 'SUP-0001',
            'email' => 'patrick.bateman@techcorp.ph',
            'password' => $pw,
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        \App\Models\SupervisorProfile::updateOrCreate(['user_id' => $supervisor->id], [
            'first_name' => 'Patrick',
            'last_name' => 'Bateman',
            'email' => 'patrick.bateman@techcorp.ph',
            'contact_number' => '09170000001',
            'sex' => 'Male',
            'position' => 'Senior Vice President / OJT Supervisor',
        ]);

        // ─── 4. Faculty accounts + section assignments ────────────────────────
        $this->call(FacultySectionAssignmentSeeder::class);

        // ─── 5. Student accounts (2300600, 2300590, 2300592) ──────────────────
        $this->call(StudentAccountsSeeder::class);
        $this->call(AcademicCollegeAccountsSeeder::class);

        // ─── 6. Announcements ─────────────────────────────────────────────────
        Announcement::firstOrCreate(
            ['title' => 'Welcome to InternTrack!'],
            ['created_by' => $coordCcs->id, 'content' => 'Get started by browsing available MOA companies and selecting a supervisor.', 'target_role' => 'student', 'is_pinned' => true]
        );
        Announcement::firstOrCreate(
            ['title' => 'Orientation Schedule'],
            ['created_by' => $coordCcs->id, 'content' => 'Internship orientation will be held soon. Please wait for further announcements.', 'target_role' => 'all', 'is_pinned' => false]
        );

        $this->command->info('');
        $this->command->info('✅ INTERNTRACK database seeded successfully!');
        $this->command->info('─────────────────────────────────────────────────────────────');
        $this->command->info('  ROLE          USERNAME/STUDENT NO.   PASSWORD');
        $this->command->info('─────────────────────────────────────────────────────────────');
        $this->command->info('  Admin         ADMIN-MISD-001         interntrack123
  Director      DIR-1001               interntrack123
  Coord (CCS)   COR-CCS-001            interntrack123
  Facul (CCS)   FAC-CCS-001            interntrack123
  Coord (COED)  COR-COED-001           interntrack123
  Facul (COED)  FAC-COED-001           interntrack123
  Coord (COE)   COR-COE-001            interntrack123
  Facul (COE)   FAC-COE-001            interntrack123
  Supervisor    SUP-0001               interntrack123 (Patrick Bateman)
  Stud (CCS)    2300600                interntrack123 (Fresh/Pending)
  Stud (CCS)    2300590                interntrack123 (Fresh/Pending)
  Stud (CCS)    2300592                interntrack123 (Populated: TechCorp PH)
  Stud (COED)   2300601                interntrack123 (Fresh/Pending)
  Stud (COE)    2300602                interntrack123 (Fresh/Pending)
  Stud (COE)    2300608                interntrack123 (Fresh/Pending)
  Coord (CHAS)  COR-CHAS-001           interntrack123
  Facul (CHAS)  FAC-CHAS-001           interntrack123
  Stud (BSN)    2300603                interntrack123 (Fresh/Pending)
  Coord (CAS)   COR-CAS-001            interntrack123
  Facul (CAS)   FAC-CAS-001            interntrack123
  Stud (BSPSY)  2300604                interntrack123 (Fresh/Pending)
  Coord (CBAA)  COR-CBAA-001           interntrack123
  Facul (CBAA)  FAC-CBAA-001           interntrack123
  Stud (BSBAMM) 2300605                interntrack123 (Fresh/Pending)
  Stud (BSBAFM) 2300606                interntrack123 (Fresh/Pending)
  Stud (BSA)    2300607                interntrack123 (Fresh/Pending)');
        $this->command->info('─────────────────────────────────────────────────────────────');
    }
}
