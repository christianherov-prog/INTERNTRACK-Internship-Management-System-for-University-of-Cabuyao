<?php

namespace Tests\Feature;

use App\Models\StudentPortfolio;
use App\Models\User;
use Database\Seeders\StudentAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PsychologyPortfolioFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychology_student_auth_payload_exposes_program_and_code(): void
    {
        $student = User::where('student_number', '2300604')->first();
        $this->assertNotNull($student);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.program', 'Bachelor of Science in Psychology')
            ->assertJsonPath('user.program_code', 'BSPSY')
            ->assertJsonPath('user.department.code', 'CAS');
    }

    public function test_psychology_portfolio_saves_rotation_fields_without_blanking_ccs_essays(): void
    {
        $student = User::where('student_number', '2300604')->first();
        $this->assertNotNull($student);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/student/portfolio')->assertOk();

        $this->postJson('/api/v1/student/portfolio', [
            'custom_fields' => [
                'psychology' => [
                    'rotations' => [
                        1 => [
                            'hte_name' => 'Verification Clinic',
                            'hte_address' => 'Cabuyao',
                            'hte_profile' => 'Educational rotation profile.',
                            'narrative' => '',
                            'rec_students' => '',
                            'rec_program' => '',
                            'rec_curriculum' => '',
                            'rec_hte' => '',
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $portfolio = StudentPortfolio::where('user_id', $student->id)->first();
        $this->assertNotNull($portfolio);
        $this->assertSame('Verification Clinic', $portfolio->custom_fields['psychology']['rotations'][1]['hte_name'] ?? $portfolio->custom_fields['psychology']['rotations']['1']['hte_name'] ?? null);
        $this->assertNull($portfolio->assessment_ethical);
    }

    public function test_ccs_student_still_saves_chapter_fields(): void
    {
        $this->seed(StudentAccountsSeeder::class);
        $student = User::where('student_number', '2300600')->first();
        $this->assertNotNull($student);
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/portfolio', [
            'company_name' => 'TechCorp PH',
            'company_address' => 'Alabang',
            'company_vision' => 'Vision text',
            'company_mission' => 'Mission text',
            'company_history' => 'History text',
            'assessment_ethical' => 'CCS ethical essay',
            'assessment_learnings' => 'CCS learnings',
            'assessment_experience' => 'CCS experience',
            'assessment_standards' => 'CCS standards',
            'assessment_recommendations' => 'CCS recommendations',
            'assessment_advice' => 'CCS advice',
        ])->assertOk();

        $portfolio = StudentPortfolio::where('user_id', $student->id)->first();
        $this->assertNotNull($portfolio);
        $this->assertSame('TechCorp PH', $portfolio->company_name);
        $this->assertSame('CCS ethical essay', $portfolio->assessment_ethical);
        $this->assertArrayNotHasKey('psychology', $portfolio->custom_fields ?? []);
    }

    public function test_nursing_student_auth_payload_and_portfolio_save(): void
    {
        $student = User::where('student_number', '2300603')->first();
        $this->assertNotNull($student);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.program', 'Bachelor of Science in Nursing')
            ->assertJsonPath('user.program_code', 'BSN')
            ->assertJsonPath('user.department.code', 'CHAS');

        $this->postJson('/api/v1/student/portfolio', [
            'custom_fields' => [
                'nursing' => [
                    'bio_sketch' => 'Nursing bio for verification.',
                    'narrative' => '',
                    'rotations' => [
                        1 => [
                            'hte_name' => 'Verification School Clinic',
                            'hte_address' => 'Cabuyao',
                            'hte_profile' => 'School clinic profile.',
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $portfolio = StudentPortfolio::where('user_id', $student->id)->first();
        $this->assertNotNull($portfolio);
        $this->assertSame('Nursing bio for verification.', $portfolio->custom_fields['nursing']['bio_sketch'] ?? null);
        $this->assertSame(
            'Verification School Clinic',
            $portfolio->custom_fields['nursing']['rotations'][1]['hte_name']
                ?? $portfolio->custom_fields['nursing']['rotations']['1']['hte_name']
                ?? null
        );
        $this->assertNull($portfolio->assessment_ethical);
    }
}
