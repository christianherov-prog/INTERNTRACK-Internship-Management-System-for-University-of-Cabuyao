<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Announcement;
use App\Models\FacultyProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('interntrack123');

        // ─── 1. Student accounts (shared capstone logins — survives git pull via seed) ─
        $this->call(StudentAccountsSeeder::class);

        // ─── 2. Staff users ───────────────────────────────────────────────────
        $director = User::create(['username' => 'DIR-1001', 'email' => 'g.oloresisimo@uc.edu.ph', 'password' => $pw, 'role' => 'director']);
        $coord = User::create(['username' => 'COR-1001', 'email' => 'a.quiatchon@uc.edu.ph', 'password' => $pw, 'role' => 'coordinator']);
        $faculty = User::create(['username' => 'FAC-1001', 'email' => 'm.bicua@uc.edu.ph', 'password' => $pw, 'role' => 'faculty']);

        // ─── 3. Staff profiles ────────────────────────────────────────────────
        FacultyProfile::create([
            'user_id' => $faculty->id,
            'employee_number' => 'FAC-1001',
            'first_name' => 'Marvin',
            'middle_name' => 'M.',
            'last_name' => 'Bicua',
            'email' => 'm.bicua@uc.edu.ph',
            'contact_number' => '09175557890',
            'department' => 'CCS',
            'college' => 'College of Computing and Information Sciences',
            'position' => 'OJT Teacher',
            'employment_status' => 'Regular',
            'synced_at' => now(),
        ]);

        FacultyProfile::create([
            'user_id' => $coord->id,
            'employee_number' => 'COR-1001',
            'first_name' => 'Arcelito',
            'middle_name' => 'C.',
            'last_name' => 'Quiatchon',
            'email' => 'a.quiatchon@uc.edu.ph',
            'contact_number' => '09175557891',
            'department' => 'CCS',
            'college' => 'College of Computing and Information Sciences',
            'position' => 'Coordinator',
            'employment_status' => 'Regular',
            'synced_at' => now(),
        ]);

        FacultyProfile::create([
            'user_id' => $director->id,
            'employee_number' => 'DIR-1001',
            'first_name' => 'Gina',
            'middle_name' => 'M.',
            'last_name' => 'Oloresisimo',
            'email' => 'g.oloresisimo@uc.edu.ph',
            'contact_number' => '09175557892',
            'department' => 'Director',
            'college' => 'University Administration',
            'position' => 'Director',
            'employment_status' => 'Regular',
            'synced_at' => now(),
        ]);

        // ─── 4. Companies (Available for MOA) ─────────────────────────────────
        Company::create(['company_name' => 'TechCorp PH', 'address' => 'Alabang, Muntinlupa', 'industry' => 'Information Technology', 'contact_person' => 'Ms. Rivera', 'contact_email' => 'hr@techcorp.ph', 'contact_number' => '028001234', 'moa_status' => 'active', 'moa_start_date' => '2024-01-01', 'moa_expiry_date' => '2026-12-31', 'slots_available' => 10]);
        Company::create(['company_name' => 'Accenture PH', 'address' => 'BGC, Taguig', 'industry' => 'IT Consulting', 'contact_person' => 'Mr. Lim', 'contact_email' => 'hr@accenture.ph', 'contact_number' => '028009876', 'moa_status' => 'active', 'moa_start_date' => '2024-03-01', 'moa_expiry_date' => '2026-02-28', 'slots_available' => 15]);

        // ─── 5. Announcements ─────────────────────────────────────────────────
        Announcement::create(['created_by' => $coord->id, 'title' => 'Welcome to InternTrack!', 'content' => 'Get started by browsing available MOA companies and selecting a supervisor.', 'target_role' => 'student', 'is_pinned' => true]);
        Announcement::create(['created_by' => $coord->id, 'title' => 'Orientation Schedule', 'content' => 'Internship orientation will be held soon. Please wait for further announcements.', 'target_role' => 'all', 'is_pinned' => false]);

        // Survey / demo messaging: place both students with faculty + supervisor + coordinator
        $this->call(SurveyPlacementSeeder::class);

        $this->command->info('✅ INTERNTRACK database seeded successfully!');
        $this->command->info('   Students: 2300600 (Valinado), 2300592 (Montealegre) — password: interntrack123');
        $this->command->info('   Staff: DIR-1001, COR-1001, FAC-1001, SUP-1001 — password: interntrack123');
    }
}
