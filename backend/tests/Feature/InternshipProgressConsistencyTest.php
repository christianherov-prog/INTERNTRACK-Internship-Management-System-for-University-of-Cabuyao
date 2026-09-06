<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\ProgramHteRequirement;
use App\Services\InternshipProgressService;
use App\Services\ProgramRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class InternshipProgressConsistencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_program_requirements_are_looked_up_from_hte_configuration(): void
    {
        $nursing = $this->makeStudentInCollege('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $psych = $this->makeStudentInCollege('CAS', 'Bachelor of Science in Psychology', 'BSPSY', '4PSY-A');
        $marketing = $this->makeStudentInCollege('CBAA', 'Bachelor of Science in Business Administration major in Marketing Management', 'BSBAMM', '4MM-A');
        $bsit = $this->makeStudentWithSection('4ITD');

        $this->seedProgramHours($nursing->studentProfile->program_id, 5, 540.60);
        $this->seedProgramHours($psych->studentProfile->program_id, 3, 150);
        $this->seedProgramHours($marketing->studentProfile->program_id, 1, 600);
        $this->seedProgramHours($bsit->studentProfile->program_id, 1, 500);

        $this->assertSame(2703.0, ProgramRequirementService::targetHoursForProfile($nursing->studentProfile));
        $this->assertSame(5, ProgramRequirementService::hteCountFor($nursing->studentProfile->program));
        $this->assertSame(450.0, ProgramRequirementService::targetHoursForProfile($psych->studentProfile));
        $this->assertSame(3, ProgramRequirementService::hteCountFor($psych->studentProfile->program));
        $this->assertSame(600.0, ProgramRequirementService::targetHoursForProfile($marketing->studentProfile));
        $this->assertSame(1, ProgramRequirementService::hteCountFor($marketing->studentProfile->program));
        $this->assertSame(500.0, ProgramRequirementService::targetHoursForProfile($bsit->studentProfile));
    }

    public function test_dashboard_records_and_staff_progress_share_the_same_hours(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $this->seedProgramHours($student->studentProfile->program_id, 1, 500);
        $company = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['target_hours' => 180, 'total_hours_rendered' => 999]);

        AttendanceLog::create([
            'internship_id' => $internship->id,
            'date' => now()->subDays(2)->toDateString(),
            'clock_in' => '08:00:00',
            'clock_out' => '16:00:00',
            'hours_rendered' => 80,
            'status' => 'validated',
            'validated_at' => now(),
        ]);

        Sanctum::actingAs($student);
        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $records = $this->getJson('/api/v1/student/records')->assertOk();

        $this->assertEquals(80.0, (float) $dashboard->json('stats.hours_rendered'));
        $this->assertEquals(500.0, (float) $dashboard->json('stats.target_hours'));
        $this->assertEquals(16.0, (float) $dashboard->json('stats.progress_percent'));
        $this->assertSame('TechCorp PH', $dashboard->json('internship.company_name'));

        $record = collect($records->json('data'))->firstWhere('id', $internship->id);
        $this->assertNotNull($record);
        $this->assertEquals(80.0, (float) $record['total_hours_rendered']);
        $this->assertEquals(500.0, (float) $record['target_hours']);
        $this->assertEquals(16.0, (float) $record['progress_pct']);
        $this->assertSame('TechCorp PH', $record['company_name']);

        Sanctum::actingAs($coordinator);
        $coord = $this->getJson('/api/v1/coordinator/students/'.$student->id.'/progress')->assertOk();
        $this->assertEquals(80.0, (float) $coord->json('progress.hours_rendered'));
        $this->assertEquals(500.0, (float) $coord->json('progress.target_hours'));
        $this->assertSame('TechCorp PH', $coord->json('internship.company'));

        Sanctum::actingAs($faculty);
        $facultyView = $this->getJson('/api/v1/faculty/students/'.$student->id.'/progress')->assertOk();
        $this->assertEquals(80.0, (float) $facultyView->json('progress.hours_rendered'));
        $this->assertEquals(500.0, (float) $facultyView->json('progress.target_hours'));
        $this->assertSame('TechCorp PH', $facultyView->json('internship.company'));

        $snapshot = InternshipProgressService::snapshot($internship->fresh());
        $this->assertEquals(80.0, $snapshot['hours_rendered']);
        $this->assertEquals(500.0, $snapshot['target_hours']);
        $this->assertEquals(420.0, $snapshot['remaining_hours']);
        $this->assertSame(1, $snapshot['hte_count']);
    }

    public function test_nursing_internship_uses_2703_hours_and_five_htes(): void
    {
        $student = $this->makeStudentInCollege('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $this->seedProgramHours($student->studentProfile->program_id, 5, 540.60);

        Sanctum::actingAs($student);
        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();

        $this->assertEquals(2703.0, (float) $dashboard->json('stats.target_hours'));
        $this->assertCount(5, $dashboard->json('internship.placements'));
        $this->assertEquals(0.0, (float) $dashboard->json('stats.hours_rendered'));
    }

    private function seedProgramHours(int $programId, int $count, float $hoursEach): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProgramHteRequirement::updateOrCreate(
                ['program_id' => $programId, 'sequence_order' => $i],
                ['label' => "HTE {$i}", 'required_hours' => $hoursEach]
            );
        }
    }
}
