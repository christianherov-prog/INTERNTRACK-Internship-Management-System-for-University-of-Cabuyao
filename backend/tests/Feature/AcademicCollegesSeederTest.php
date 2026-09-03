<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use Database\Seeders\AcademicCollegesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCollegesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_chas_cas_cbaa_with_listed_programs(): void
    {
        $this->seed(AcademicCollegesSeeder::class);

        $chas = Department::where('code', 'CHAS')->first();
        $cas = Department::where('code', 'CAS')->first();
        $cbaa = Department::where('code', 'CBAA')->first();

        $this->assertNotNull($chas);
        $this->assertSame('College of Health and Allied Sciences', $chas->name);
        $this->assertNotNull($cas);
        $this->assertSame('College of Arts and Sciences', $cas->name);
        $this->assertNotNull($cbaa);
        $this->assertSame('College of Business, Accountancy and Administration', $cbaa->name);

        $this->assertTrue(Program::where('code', 'BSN')->where('department_id', $chas->id)->exists());
        $this->assertTrue(Program::where('code', 'BSPSY')->where('department_id', $cas->id)->exists());
        $this->assertTrue(Program::where('code', 'BSBAMM')->where('department_id', $cbaa->id)->exists());
        $this->assertTrue(Program::where('code', 'BSBAFM')->where('department_id', $cbaa->id)->exists());
        $this->assertTrue(Program::where('code', 'BSA')->where('department_id', $cbaa->id)->exists());

        $this->assertSame(
            'Bachelor of Science in Business Administration major in Marketing Management',
            Program::where('code', 'BSBAMM')->value('name')
        );
        $this->assertSame(
            'Bachelor of Science in Business Administration major in Financial Management',
            Program::where('code', 'BSBAFM')->value('name')
        );
    }

    public function test_is_idempotent_and_does_not_create_ccs(): void
    {
        $this->seed(AcademicCollegesSeeder::class);
        $this->seed(AcademicCollegesSeeder::class);

        $this->assertSame(1, Department::where('code', 'CHAS')->count());
        $this->assertSame(1, Department::where('code', 'CAS')->count());
        $this->assertSame(1, Department::where('code', 'CBAA')->count());
        $this->assertFalse(Department::where('code', 'CCS')->exists());
        $this->assertFalse(Program::where('code', 'BSIT')->exists());
        $this->assertFalse(Program::where('code', 'BSCS')->exists());
    }
}
