<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AcademicCollegesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class AcademicStructureApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_director_can_list_new_colleges_and_programs(): void
    {
        $this->seed(AcademicCollegesSeeder::class);
        $director = $this->makeUser('director', 'DIR-TEST-1');
        Sanctum::actingAs($director);

        $departments = $this->getJson('/api/v1/academic/departments')->assertOk()->json();
        $codes = collect($departments)->pluck('code')->all();
        $this->assertContains('CHAS', $codes);
        $this->assertContains('CAS', $codes);
        $this->assertContains('CBAA', $codes);

        $programs = $this->getJson('/api/v1/academic/programs')->assertOk()->json();
        $programCodes = collect($programs)->pluck('code')->all();
        foreach (['BSN', 'BSPSY', 'BSBAMM', 'BSBAFM', 'BSA'] as $code) {
            $this->assertContains($code, $programCodes);
        }

        $chas = collect($departments)->firstWhere('code', 'CHAS');
        $chasPrograms = $this->getJson('/api/v1/academic/programs?department_id='.$chas['id'])->assertOk()->json();
        $this->assertSame(['BSN'], collect($chasPrograms)->pluck('code')->all());
    }
}
