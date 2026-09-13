<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EvaluationPeriodGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function fo24Payload(): array
    {
        return [
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => [
                'c1' => 90, 'c2' => 90, 'c3' => 90, 'c4' => 90, 'c5' => 90,
                'c6' => 90, 'c7' => 90, 'c8' => 90, 'c9' => 90, 'c10' => 90,
            ],
        ];
    }

    private function fo22Payload(): array
    {
        return [
            'evaluation_period' => 'final',
            'form_type' => 'FO-22',
            'responses' => [
                'q1' => 5, 'q2' => 5, 'q3' => 4, 'q4' => 4, 'q5' => 5, 'q6' => 4, 'q7' => 5,
            ],
        ];
    }

    public function test_student_and_supervisor_forms_stay_locked_until_assigned_faculty_approves(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $otherFaculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $this->assertSame('pending', $internship->fresh()->evaluation_period_status ?: 'pending');

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/evaluations')
            ->assertOk()
            ->assertJsonPath('evaluation_period_status', 'pending')
            ->assertJsonPath('evaluation_period_approved', false);

        $this->postJson('/api/v1/student/evaluations', $this->fo22Payload())
            ->assertForbidden();

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/evaluations/'.$internship->id, $this->fo24Payload())
            ->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id, [
            'evaluation_period' => 'final',
            'overall_score' => 85,
        ])->assertCreated();

        Sanctum::actingAs($otherFaculty);
        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id.'/approve-period')
            ->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id.'/approve-period')
            ->assertOk()
            ->assertJsonPath('evaluation_period_status', 'approved')
            ->assertJsonPath('evaluation_period_approved', true);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/evaluations')
            ->assertOk()
            ->assertJsonPath('evaluation_period_approved', true);

        $this->postJson('/api/v1/student/evaluations', $this->fo22Payload())
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/evaluations/'.$internship->id, $this->fo24Payload())
            ->assertCreated();

        $this->assertSame(1, Evaluation::query()->where('form_type', 'FO-22')->count());
        $this->assertSame(1, Evaluation::query()->where('form_type', 'FO-24')->count());
        $this->assertSame(1, Evaluation::query()->where('form_type', 'faculty_eval')->count());
    }
}
