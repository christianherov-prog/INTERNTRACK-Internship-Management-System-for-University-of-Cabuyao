<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\MisdIntegrationService;
use App\Services\MockMisdRepository;
use App\Services\OfficialFormDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class Student2300590IdentityTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_mock_misd_catalogs_angel_luis_taac_for_2300590(): void
    {
        $row = app(MockMisdRepository::class)->findStudent('2300590');

        $this->assertSame('Angel Luis', $row['first_name']);
        $this->assertSame('Taac - Taac', $row['last_name']);
        $this->assertSame('2300590', $row['student_number']);
        $this->assertSame('Bachelor of Science in Information Technology', $row['program']);
        $this->assertSame('College of Computing Studies', $row['department']);
        $this->assertNotSame('UC', $row['first_name']);
        $this->assertNotSame('Student', $row['last_name']);
    }

    public function test_unknown_student_id_still_uses_uc_student_stub(): void
    {
        $row = app(MockMisdRepository::class)->findStudent('2399999');

        $this->assertSame('UC', $row['first_name']);
        $this->assertSame('Student', $row['last_name']);
    }

    public function test_clarence_2300592_identity_is_unchanged(): void
    {
        $row = app(MockMisdRepository::class)->findStudent('2300592');

        $this->assertSame('Clarence', $row['first_name']);
        $this->assertSame('Montealegre', $row['last_name']);
    }

    public function test_login_sync_replaces_uc_student_stub_with_catalog_name(): void
    {
        $student = $this->makeAngelAccount(first: 'UC', last: 'Student');
        $clarence = $this->makeClarenceAccount();

        $this->assertSame(1, User::where('student_number', '2300590')->count());
        $this->assertSame(1, StudentProfile::where('student_number', '2300590')->count());

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => '2300590',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('Angel Luis Taac - Taac', $login->json('user.name'));
        $this->assertSame('Angel Luis', $login->json('user.first_name'));
        $this->assertSame('Taac - Taac', $login->json('user.last_name'));
        $this->assertSame('2300590', $login->json('user.student_number'));
        $this->assertSame('AT', $login->json('user.avatar'));
        $this->assertStringContainsString('Information Technology', (string) $login->json('user.program'));

        $student->refresh()->load('studentProfile');
        $this->assertSame('Angel Luis', $student->studentProfile->first_name);
        $this->assertSame('Taac - Taac', $student->studentProfile->last_name);

        $clarence->refresh()->load('studentProfile');
        $this->assertSame('Clarence', $clarence->studentProfile->first_name);
        $this->assertSame('Montealegre', $clarence->studentProfile->last_name);
    }

    public function test_generic_stub_does_not_overwrite_a_real_uncatalogued_name(): void
    {
        $student = $this->makeStudentWithSection();
        $student->update(['student_number' => '2300123']);
        $student->studentProfile->update([
            'student_number' => '2300123',
            'first_name' => 'Kept',
            'last_name' => 'Identity',
        ]);

        app(MisdIntegrationService::class)->syncStudent($student->fresh('studentProfile'));

        $student->refresh()->load('studentProfile');
        $this->assertSame('Kept', $student->studentProfile->first_name);
        $this->assertSame('Identity', $student->studentProfile->last_name);
    }

    public function test_user_resource_and_official_forms_use_canonical_name(): void
    {
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);
        $coordinator = $this->makeUser('coordinator');
        $this->ensureStaffDepartment($coordinator, 'CCS');
        $supervisor = $this->makeUser('supervisor', 'SUP-ANGEL');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Pat',
            'last_name' => 'Supervisor',
        ]);
        $student = $this->makeAngelAccount();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'ongoing']);

        $resource = (new UserResource($student->fresh(\App\Services\AuthService::USER_RELATIONS)))->toArray(Request::create('/'));
        $this->assertSame('Angel Luis Taac - Taac', $resource['name']);
        $this->assertSame('AT', $resource['avatar']);

        Sanctum::actingAs($faculty);
        $roster = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->first(fn ($row) => ($row['student']['student_number'] ?? null) === '2300590');
        $this->assertNotNull($roster);
        $profile = $roster['student']['student_profile'] ?? $roster['student']['studentProfile'] ?? [];
        $this->assertSame('Angel Luis', $profile['first_name'] ?? null);
        $this->assertSame('Taac - Taac', $profile['last_name'] ?? null);

        Sanctum::actingAs($coordinator);
        $progress = $this->getJson('/api/v1/coordinator/students/'.$student->id.'/progress')->assertOk()->json();
        $this->assertSame('Angel Luis Taac - Taac', data_get($progress, 'student.name'));

        $fo30 = app(OfficialFormDataService::class)->fo30($internship->fresh());
        $this->assertSame('TAAC - TAAC, ANGEL LUIS', $fo30['student_name']);
        $this->assertSame('Bachelor of Science in Information Technology', $fo30['program']);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id' => $faculty->id,
            'body' => 'Hello faculty',
        ])->assertCreated();

        $peer = collect($this->actingAs($faculty, 'sanctum')
            ->getJson('/api/v1/messages/conversations')
            ->assertOk()
            ->json('data'))
            ->firstWhere('peer.id', $student->id);
        $this->assertNotNull($peer);
        $this->assertSame('Angel Luis Taac - Taac', $peer['peer']['name']);
        $this->assertSame('AT', $peer['peer']['avatar']);
    }

    private function makeAngelAccount(string $first = 'Angel Luis', string $last = 'Taac - Taac'): User
    {
        $student = $this->makeStudentWithSection();
        $student->forceFill([
            'student_number' => '2300590',
            'email' => 'angel.taactaac@uc.edu.ph',
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ])->save();
        $student->studentProfile->update([
            'student_number' => '2300590',
            'first_name' => $first,
            'last_name' => $last,
            'email' => 'angel.taactaac@uc.edu.ph',
        ]);

        return $student->fresh('studentProfile.program.department');
    }

    private function makeClarenceAccount(): User
    {
        $student = $this->makeStudentWithSection('4ITA');
        $student->forceFill([
            'student_number' => '2300592',
            'email' => 'clarence.montealegre@uc.edu.ph',
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ])->save();
        $student->studentProfile->update([
            'student_number' => '2300592',
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
        ]);

        return $student->fresh('studentProfile');
    }
}
