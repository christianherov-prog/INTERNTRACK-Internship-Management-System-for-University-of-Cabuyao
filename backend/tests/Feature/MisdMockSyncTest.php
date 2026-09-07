<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use App\Services\MisdIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class MisdMockSyncTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_mock_student_sync_is_idempotent_and_preserves_local_account_fields(): void
    {
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        $student = $this->makeUser('student', '2300600');
        $student->update([
            'password' => Hash::make('local-secret'),
            'role' => 'student',
        ]);
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2300600',
            'first_name' => 'Local',
            'last_name' => 'Name',
            'section' => 'OLDSEC',
            'school_year' => '2023-2024',
            'semester' => '1st Semester',
        ]);

        Sanctum::actingAs($admin);
        $first = $this->postJson('/api/v1/admin/misd/sync/student/'.$student->id)->assertOk();
        $this->assertSame('4ITD', $first->json('student.section'));
        $second = $this->postJson('/api/v1/admin/misd/sync/student/'.$student->id)->assertOk();
        $this->assertFalse($second->json('changed'));

        $this->assertSame(1, StudentProfile::query()->where('user_id', $student->id)->count());
        $this->assertSame(1, User::query()->where('student_number', '2300600')->count());
        $this->assertSame('student', $student->fresh()->role);
        $this->assertTrue(Hash::check('local-secret', $student->fresh()->password));
        $this->assertSame('Valinado', $student->fresh()->studentProfile->last_name);
    }

    public function test_mock_directory_lists_programs_and_sections_without_http(): void
    {
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        Sanctum::actingAs($admin);

        $list = $this->postJson('/api/v1/admin/misd/directory', ['type' => 'students'])->assertOk();
        $this->assertGreaterThan(0, $list->json('count'));
        $this->assertNotEmpty(collect($list->json('data'))->pluck('section')->filter());
        $this->assertNotEmpty(collect($list->json('data'))->pluck('program')->filter());
    }

    public function test_integration_service_does_not_create_duplicate_on_repeated_sync(): void
    {
        $student = $this->makeUser('student', '2300600');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2300600',
            'first_name' => 'Local',
            'last_name' => 'Name',
        ]);

        $service = app(MisdIntegrationService::class);
        $service->forgetStudentCache('2300600');
        $service->syncStudent($student->fresh('studentProfile'));
        $service->forgetStudentCache('2300600');
        $service->syncStudent($student->fresh('studentProfile'));

        $this->assertSame(1, StudentProfile::query()->where('student_number', '2300600')->count());
    }
}
