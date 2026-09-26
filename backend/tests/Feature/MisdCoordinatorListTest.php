<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class MisdCoordinatorListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_coordinators_list_eager_loads_departments_and_matches_dashboard_count(): void
    {
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        $ccs = $this->departmentByCode('CCS');
        $chas = Department::firstOrCreate(
            ['code' => 'CHAS'],
            ['name' => 'College of Health and Allied Sciences', 'is_active' => true]
        );

        $withDept = $this->makeUser('coordinator', 'COR-TEST-CCS-001');
        FacultyProfile::where('user_id', $withDept->id)->update(['department_id' => $ccs->id]);

        $other = $this->makeUser('coordinator', 'COR-TEST-CHAS-001');
        FacultyProfile::where('user_id', $other->id)->update(['department_id' => $chas->id]);

        $orphan = $this->makeUser('coordinator', 'COR-TEST-ORPHAN-001');
        FacultyProfile::where('user_id', $orphan->id)->update(['department_id' => null]);

        Sanctum::actingAs($admin);

        $list = $this->getJson('/api/v1/admin/coordinators')->assertOk();
        $rows = collect($list->json('data'));
        $this->assertGreaterThanOrEqual(3, $rows->count());
        $this->assertSame('College of Computer Studies', $rows->firstWhere('faculty_number', 'COR-TEST-CCS-001')['department']);
        $this->assertNull($rows->firstWhere('faculty_number', 'COR-TEST-ORPHAN-001')['department']);

        $dashboard = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertSame($rows->count(), (int) $dashboard->json('users_by_role.coordinator.total'));
        $this->assertSame($rows->where('is_active', true)->count(), (int) $dashboard->json('users_by_role.coordinator.active'));
    }
}
