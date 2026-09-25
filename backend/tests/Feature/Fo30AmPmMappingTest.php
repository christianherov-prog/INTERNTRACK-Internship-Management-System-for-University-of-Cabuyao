<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\SupervisorProfile;
use App\Models\WorkSchedule;
use App\Services\AttendanceDayResolver;
use App\Services\OfficialFormDataService;
use App\Services\OneWeekOjtDemoService;
use App\Services\PortfolioDataService;
use App\Support\ManilaAttendanceClock;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class Fo30AmPmMappingTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function party(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty, '4ITD');
        $supervisor = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Industry',
            'last_name' => 'Supervisor',
            'position' => 'IT Supervisor',
            'email' => $supervisor->email,
        ]);
        $student = $this->makeStudentWithSection('4ITD');
        $company = $this->makeEligibleCompany([
            'company_name' => 'TechCorp PH',
            'address' => 'Alabang, Muntinlupa City',
        ]);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'start_date' => '2026-06-01',
            'end_date' => '2026-09-30',
        ]);

        WorkSchedule::create([
            'internship_id' => $internship->id,
            'proposed_by' => $student->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'status' => 'approved',
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
        ]);

        return compact('student', 'faculty', 'coordinator', 'supervisor', 'internship');
    }

    public function test_afternoon_only_fo30_is_not_absent(): void
    {
        $party = $this->party();
        Carbon::setTestNow(Carbon::parse('2026-09-09 05:01:00', 'UTC'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-09 05:03:00', 'UTC'));
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $hours = (float) $out->json('record.hours_rendered');

        $dtr = app(PortfolioDataService::class)
            ->payload($party['internship']->fresh(), $party['student'])['internship']['attendance'];
        $row = collect($dtr)->firstWhere('date', '2026-09-09');

        $this->assertNotNull($row);
        $this->assertFalse($row['day_absent'] ?? false);
        $this->assertNotSame('Absent', $row['am_time_in']);
        $this->assertNull($row['am_time_in']);
        $this->assertNull($row['am_time_out']);
        $this->assertSame('13:01', $row['pm_time_in']);
        $this->assertSame('13:03', $row['pm_time_out']);
        $this->assertEqualsWithDelta($hours, (float) $row['hours_rendered'], 0.01);

        foreach (['student', 'faculty', 'coordinator', 'supervisor'] as $role) {
            Sanctum::actingAs($party[$role]);
            $fo30 = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)
                ->assertOk()
                ->json('fo30.logs');
            $log = collect($fo30)->firstWhere('date', '2026-09-09');
            $this->assertSame('13:01', $log['pm_time_in'], $role);
            $this->assertNotSame('Absent', $log['am_time_in'], $role);
        }

        Carbon::setTestNow();
    }

    public function test_no_attendance_before_shift_end_is_not_absent_yet(): void
    {
        $party = $this->party();
        $resolver = app(AttendanceDayResolver::class);
        Carbon::setTestNow(Carbon::parse('2026-09-09 07:00:00', ManilaTime::TZ)); // 3 PM Manila

        $this->assertFalse($resolver->isFullDayAbsent($party['internship'], '2026-09-09', null));
        Carbon::setTestNow();
    }

    public function test_no_attendance_after_shift_end_is_absent(): void
    {
        $party = $this->party();
        $resolver = app(AttendanceDayResolver::class);
        Carbon::setTestNow(Carbon::parse('2026-09-09 17:01:00', ManilaTime::TZ));

        $this->assertTrue($resolver->isFullDayAbsent($party['internship'], '2026-09-09', null));
        $row = $resolver->absentFo30Row('2026-09-09');
        $this->assertTrue($row['day_absent']);
        $this->assertSame('Absent', $row['am_time_in']);
        $this->assertSame(0.0, $row['hours_rendered']);
        $this->assertNull($row['hte_signature_path']);

        Carbon::setTestNow();
    }

    public function test_weekend_without_attendance_is_not_absent(): void
    {
        $party = $this->party();
        $resolver = app(AttendanceDayResolver::class);
        Carbon::setTestNow(Carbon::parse('2026-09-12 20:00:00', ManilaTime::TZ)); // Saturday

        $this->assertFalse($resolver->isExpectedWorkingDay($party['internship'], '2026-09-12'));
        $this->assertFalse($resolver->isFullDayAbsent($party['internship'], '2026-09-12', null));
        Carbon::setTestNow();
    }

    public function test_future_day_is_not_absent(): void
    {
        $party = $this->party();
        $resolver = app(AttendanceDayResolver::class);
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00', ManilaTime::TZ));

        $this->assertFalse($resolver->isFullDayAbsent($party['internship'], '2026-09-08', null));
        Carbon::setTestNow();
    }

    public function test_schedule_end_is_student_specific(): void
    {
        $party = $this->party();
        WorkSchedule::query()->where('internship_id', $party['internship']->id)->update([
            'end_time' => '18:00:00',
        ]);
        $resolver = app(AttendanceDayResolver::class);
        Carbon::setTestNow(Carbon::parse('2026-09-09 17:30:00', ManilaTime::TZ));
        $this->assertFalse($resolver->shiftHasEnded($party['internship']->fresh(), '2026-09-09'));
        Carbon::setTestNow(Carbon::parse('2026-09-09 18:00:00', ManilaTime::TZ));
        $this->assertTrue($resolver->shiftHasEnded($party['internship']->fresh(), '2026-09-09'));
        Carbon::setTestNow();
    }

    public function test_approved_correction_uses_canonical_corrected_clock_for_fo30(): void
    {
        $party = $this->party();
        Carbon::setTestNow(Carbon::parse('2026-09-10 01:00:00', 'UTC'));

        $party['internship']->attendance()->create([
            'date' => '2026-09-09',
            'clock_in' => ManilaAttendanceClock::storedTime('13:00'),
            'am_time_in' => ManilaAttendanceClock::storedTime('13:00'),
            'status' => 'pending',
            'placement_id' => $party['internship']->current_placement_id,
        ]);

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/corrections', [
            'date' => '2026-09-09',
            'correction_type' => 'clock_in',
            'requested_clock_in' => substr(ManilaAttendanceClock::storedTime('08:00'), 0, 5),
            'reason' => 'Forgot morning punch',
        ])->assertCreated();

        $requestId = AttendanceCorrectionRequest::first()->id;
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/corrections/'.$requestId, ['action' => 'approved'])->assertOk();
        Sanctum::actingAs($party['faculty']);
        $this->patchJson('/api/v1/faculty/dtr/corrections/'.$requestId, ['action' => 'approved'])->assertOk();

        $log = app(OfficialFormDataService::class)->fo30($party['internship']->fresh())['logs'];
        $row = collect($log)->firstWhere('date', '2026-09-09');
        $this->assertSame('08:00', $row['am_time_in']);
        $this->assertFalse($row['day_absent'] ?? false);
        Carbon::setTestNow();
    }

    public function test_clarence_controlled_window_and_sep7_absent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', ManilaTime::TZ));
        $demo = app(OneWeekOjtDemoService::class);
        // Ensure seed path exists for 2300592 in this isolated DB.
        $party = $this->party();
        $party['student']->update(['student_number' => '2300592']);
        $party['supervisor']->update(['faculty_number' => 'SUP-0002']);
        SupervisorProfile::where('user_id', $party['supervisor']->id)->update([
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
        ]);

        // The demo writes generated portfolio images; keep them off the real disk.
        \Illuminate\Support\Facades\Storage::fake('local');
        $result = $demo->reconcileStudent('2300592');
        $internship = $result['internship'];

        $dates = collect($result['attendance'])->map(fn (AttendanceLog $l) => $l->date->toDateString())->sort()->values();
        $this->assertTrue($dates->contains('2026-08-24'));
        $this->assertTrue($dates->contains('2026-08-28'));
        $this->assertTrue($dates->contains('2026-08-31'));
        $this->assertTrue($dates->contains('2026-09-04'));
        $this->assertFalse($dates->contains('2026-09-07'));

        $this->assertEquals(80.0, (float) collect($result['attendance'])->sum('hours_rendered'));

        $fo30 = app(OfficialFormDataService::class)->fo30($internship->fresh());
        $sep7 = collect($fo30['logs'])->firstWhere('date', '2026-09-07');
        $this->assertNotNull($sep7);
        $this->assertTrue($sep7['day_absent']);
        $this->assertSame('Absent', $sep7['am_time_in']);
        $this->assertSame(0.0, (float) $sep7['hours_rendered']);
        $this->assertNull($sep7['hte_signature_path']);

        $portfolio = app(PortfolioDataService::class)->payload($internship->fresh(), $party['student']);
        $pSep7 = collect($portfolio['internship']['attendance'])->firstWhere('date', '2026-09-07');
        $this->assertTrue($pSep7['day_absent']);

        Carbon::setTestNow();
    }
}
