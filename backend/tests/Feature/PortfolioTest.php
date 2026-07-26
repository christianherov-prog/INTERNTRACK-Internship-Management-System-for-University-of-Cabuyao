<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_active_student_can_get_portfolio(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/student/portfolio')
            ->assertOk()
            ->assertJsonStructure(['internship', 'user']);
    }

    public function test_active_student_can_save_portfolio(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/portfolio', [
            'things_learned' => 'Learned API design and documentation.',
        ])->assertOk();
    }

    public function test_portfolio_photo_rejects_disallowed_mime(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/portfolio/photos', [
            'type' => 'ojt_photo',
            'file' => UploadedFile::fake()->create('notes.exe', 20, 'application/x-msdownload'),
            'week_number' => 1,
        ])->assertStatus(422);
    }
}
