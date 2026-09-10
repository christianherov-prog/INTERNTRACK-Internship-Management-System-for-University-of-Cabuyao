<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\InternshipApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class InternshipAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_director_sees_all_departments_and_coordinator_stays_in_own(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $ccsCoordinator = $this->makeUser('coordinator', 'COR-CCS-009');
        $supervisor = $this->makeUser('supervisor');
        $company = $this->makeEligibleCompany(['company_name' => 'Analytics HTE']);

        $ccsStudent = $this->makeStudentInCollege('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $coeStudent = $this->makeStudentInCollege('COE', 'Bachelor of Science in Civil Engineering', 'BSCE', '4CE-A');
        $ccsIntern = $this->makeActiveInternship($ccsStudent, $company, $supervisor, $faculty, $ccsCoordinator);
        $ccsIntern->update(['total_hours_rendered' => 80, 'school_year' => '2024-2025', 'semester' => 2]);
        $coeIntern = $this->makeActiveInternship($coeStudent, $company, $supervisor, $faculty, $ccsCoordinator);
        $coeIntern->update(['total_hours_rendered' => 220, 'school_year' => '2023-2024', 'semester' => 1]);

        Evaluation::query()->create([
            'internship_id' => $ccsIntern->id,
            'evaluator_type' => 'student',
            'evaluated_by' => $ccsStudent->id,
            'evaluation_period' => 'final',
            'form_type' => 'FO-23',
            'average_score' => 4.5,
            'submitted_at' => now(),
        ]);

        InternshipApplication::create([
            'student_id' => $ccsStudent->id,
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
        InternshipApplication::create([
            'student_id' => $coeStudent->id,
            'company_id' => $company->id,
            'status' => 'pending',
        ]);

        $director = $this->makeUser('director', 'DIR-ANLY');
        Sanctum::actingAs($director);

        $all = $this->getJson('/api/v1/director/analytics')->assertOk();
        $this->assertTrue($all->json('scope.cross_department'));
        $codes = collect($all->json('department_leaderboard'))->pluck('department_code')->all();
        $this->assertContains('CCS', $codes);
        $this->assertContains('COE', $codes);
        $this->assertGreaterThanOrEqual(2, (int) $all->json('kpis.internships'));
        $this->assertGreaterThanOrEqual(2, (int) $all->json('kpis.applications'));
        $active = collect($all->json('student_activity.most_active_per_company'))->firstWhere('company', 'Analytics HTE');
        $this->assertStringContainsString('COE Student', $active['student_name'] ?? '');
        $this->assertGreaterThanOrEqual(2, count($all->json('period_comparison')));

        $ccsDeptId = $this->departmentByCode('CCS')->id;
        $ccsOnly = $this->getJson('/api/v1/director/analytics?department_id='.$ccsDeptId.'&school_year=2024-2025')->assertOk();
        $this->assertSame(1, (int) $ccsOnly->json('kpis.internships'));
        $this->assertFalse($ccsOnly->json('scope.cross_department'));

        $this->getJson('/api/v1/director/analytics?school_year=2099-2100')
            ->assertOk()
            ->assertJsonPath('kpis.internships', 0);

        $this->getJson('/api/v1/director/dashboard')->assertOk()->assertJsonStructure(['stats', 'most_used_hte']);

        Sanctum::actingAs($ccsCoordinator);
        $coord = $this->getJson('/api/v1/coordinator/analytics?school_year=2024-2025')->assertOk();
        $this->assertFalse($coord->json('scope.cross_department'));
        $this->assertNull($coord->json('department_leaderboard'));
        $this->assertSame(1, (int) $coord->json('kpis.internships'));
        $names = collect($coord->json('student_activity.top_students'))->pluck('student_name')->implode(' ');
        $this->assertStringContainsString('CCS', $names);
        $this->assertStringNotContainsString('COE Student', $names);

        $coeDeptId = $this->departmentByCode('COE')->id;
        $widened = $this->getJson('/api/v1/coordinator/analytics?department_id='.$coeDeptId.'&school_year=2024-2025')->assertOk();
        $this->assertSame(1, (int) $widened->json('kpis.internships'));
        $this->assertSame($this->departmentByCode('CCS')->id, (int) $widened->json('scope.department_id'));
    }
}
