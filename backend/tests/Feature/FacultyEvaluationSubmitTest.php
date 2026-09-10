<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class FacultyEvaluationSubmitTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_assigned_faculty_can_submit_and_update_faculty_eval_without_replacing_fo24(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $otherFaculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $this->approveEvaluationPeriod($internship, $faculty);

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/evaluations/'.$internship->id, [
            'evaluation_period' => 'midterm',
            'form_type' => 'FO-24',
            'responses' => [
                'c1' => 90, 'c2' => 90, 'c3' => 90, 'c4' => 90, 'c5' => 90,
                'c6' => 90, 'c7' => 90, 'c8' => 90, 'c9' => 90, 'c10' => 90,
            ],
        ])->assertCreated();

        Sanctum::actingAs($otherFaculty);
        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id, [
            'evaluation_period' => 'midterm',
            'overall_score' => 80,
        ])->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id, [
            'evaluation_period' => 'midterm',
            'overall_score' => 86,
            'general_comments' => 'Meets expectations',
        ])->assertCreated()
            ->assertJsonPath('evaluation.form_type', 'faculty_eval')
            ->assertJsonPath('evaluation.evaluator_type', 'faculty')
            ->assertJsonPath('evaluation.average_score', 86);

        $this->postJson('/api/v1/faculty/evaluations/'.$internship->id, [
            'evaluation_period' => 'midterm',
            'overall_score' => 88,
        ])->assertCreated();

        $this->assertSame(1, Evaluation::query()->where('form_type', 'faculty_eval')->count());
        $this->assertSame(1, Evaluation::query()->where('form_type', 'FO-24')->count());
        $this->assertEquals(88.0, (float) Evaluation::query()->where('form_type', 'faculty_eval')->value('average_score'));

        $list = $this->getJson('/api/v1/faculty/evaluations')->assertOk();
        $types = collect($list->json('internships.0.evaluations'))->pluck('form_type')->all();
        $this->assertContains('FO-24', $types);
        $this->assertContains('faculty_eval', $types);
    }
}
