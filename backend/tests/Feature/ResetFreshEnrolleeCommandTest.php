<?php

namespace Tests\Feature;

use App\Models\InternshipApplication;
use App\Models\SupervisorInviteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class ResetFreshEnrolleeCommandTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_command_resets_student_to_pending_placement_without_company(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty, '4IT-A');
        $student = $this->makeStudentWithSection('4IT-A');
        $other = $this->makeStudentWithSection('4IT-B');
        $this->mapFacultyForSection($faculty, '4IT-B');

        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);
        $otherInternship = $this->makeActiveInternship($other, $company, $supervisor, $faculty, $faculty);

        InternshipApplication::create([
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'approved',
        ]);

        SupervisorInviteToken::create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'token' => 'test-token-fresh-reset',
            'email' => 'sup@example.com',
            'first_name' => 'Sup',
            'last_name' => 'Ervisor',
            'expires_at' => now()->addDays(7),
            'status' => 'approved',
            'supervisor_user_id' => $supervisor->id,
        ]);

        $exit = Artisan::call('interntrack:reset-fresh-enrollee', [
            'student_number' => $student->student_number,
        ]);
        $this->assertSame(0, $exit);

        $student->refresh()->load('activeInternship');
        $active = $student->activeInternship;

        $this->assertNotNull($active);
        $this->assertSame('pending_placement', $active->status);
        $this->assertNull($active->company_id);
        $this->assertNull($active->supervisor_id);
        $this->assertSame(0.0, (float) $active->total_hours_rendered);
        $this->assertSame((int) $faculty->id, (int) $active->faculty_id);
        $this->assertSame(0, InternshipApplication::where('student_id', $student->id)->count());
        $this->assertSame(
            0,
            SupervisorInviteToken::where('student_id', $student->id)
                ->whereIn('status', ['pending', 'pending_faculty', 'approved', 'accepted'])
                ->count()
        );

        $otherInternship->refresh();
        $this->assertContains($otherInternship->status, ['ongoing', 'active', 'placed']);
        $this->assertSame((int) $company->id, (int) $otherInternship->company_id);
        $this->assertSame((int) $supervisor->id, (int) $otherInternship->supervisor_id);
    }
}
