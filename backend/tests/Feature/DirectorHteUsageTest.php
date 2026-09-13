<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DirectorHteUsageTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_director_analytics_include_most_and_least_used_hte(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $busy = $this->makeEligibleCompany(['company_name' => 'Busy HTE']);
        $quiet = $this->makeEligibleCompany(['company_name' => 'Quiet HTE']);

        $this->makeActiveInternship($this->makeStudentWithSection('4ITA'), $busy, $supervisor, $faculty, $coordinator);
        $this->makeActiveInternship($this->makeStudentWithSection('4ITB'), $busy, $supervisor, $faculty, $coordinator);
        $this->makeActiveInternship($this->makeStudentWithSection('4ITC'), $quiet, $supervisor, $faculty, $coordinator);

        $director = $this->makeUser('director');
        Sanctum::actingAs($director);

        $dash = $this->getJson('/api/v1/director/dashboard')->assertOk();
        $most = collect($dash->json('most_used_hte'));
        $least = collect($dash->json('least_used_hte'));
        $this->assertSame('Busy HTE', $most->first()['company_name']);
        $this->assertGreaterThanOrEqual(2, (int) $most->first()['internships_count']);
        $this->assertTrue($least->contains(fn ($row) => $row['company_name'] === 'Quiet HTE'));
    }
}
