<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\OfficialFormDataService;
use App\Support\ManilaAttendanceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CLR-ATT-01..05 and controlled-data time checks, against the seeded CCS
 * dataset in interntrack_testing (never the development database).
 *
 * The situation cleaned up on the development database is reproduced here:
 * an attendance row recorded after Clarence's internship was completed, plus
 * rows in the earlier generator's format (Manila times stored as UTC).
 */
class ControlledAttendanceCleanupTest extends TestCase
{
    use RefreshDatabase;

    private const POST_COMPLETION_DATE = '2026-09-26';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
    }

    private function internship(string $studentNumber): Internship
    {
        $userId = StudentProfile::where('student_number', $studentNumber)->value('user_id');

        return Internship::where('student_id', $userId)->orderByDesc('id')->firstOrFail();
    }

    private function validatedHours(Internship $internship): float
    {
        return round((float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered'), 2);
    }

    /** Recreate the erroneous post-completion entry and legacy-format rows, then repair. */
    private function reproduceAndRepair(Internship $clarence): AttendanceLog
    {
        $clarence->attendance()->create([
            'date' => self::POST_COMPLETION_DATE,
            'clock_in' => '02:22:24',
            'clock_out' => '02:22:36',
            'am_time_in' => '02:22:24',
            'am_time_out' => '02:22:36',
            'hours_rendered' => 0,
            'status' => 'rejected',
            'validated_by' => $clarence->supervisor_id,
            'validated_at' => now(),
        ]);

        // Earlier generator format: Manila wall-clock stored as UTC, no break.
        $legacy = $clarence->attendance()->where('status', 'validated')->where('hours_rendered', 8)->orderBy('date')->firstOrFail();
        $legacy->update([
            'clock_in' => '08:00:00', 'clock_out' => '17:00:00',
            'am_time_in' => null, 'am_time_out' => null, 'pm_time_in' => null, 'pm_time_out' => null,
            'break_start' => null, 'break_end' => null,
        ]);

        $this->assertSame(0, Artisan::call('interntrack:repair-controlled-attendance'));

        return $legacy->fresh();
    }

    public function test_seeded_attendance_is_realistic_daytime_manila_time(): void
    {
        foreach (['2300592', '2300590', '2300595', '2300613', '2300600', '2300609', '2300610', '2300611'] as $sn) {
            $internship = $this->internship($sn);
            $rows = app(OfficialFormDataService::class)->fo30($internship)['logs'];
            $worked = collect($rows)->filter(fn ($r) => ($r['hours_rendered'] ?? 0) > 0);
            $this->assertNotEmpty($worked, $sn);

            foreach ($worked as $row) {
                $this->assertMatchesRegularExpression('/^0[78]:\d{2}$/', (string) $row['am_time_in'], "{$sn} {$row['date']} clock in");
                $out = $row['pm_time_out'] ?? $row['am_time_out'];
                $this->assertMatchesRegularExpression('/^(12:00|17:0\d)$/', (string) $out, "{$sn} {$row['date']} clock out");
                if ((float) $row['hours_rendered'] === 8.0) {
                    $this->assertMatchesRegularExpression('/^12:0\d$/', (string) $row['am_time_out']);
                    $this->assertMatchesRegularExpression('/^13:0\d$/', (string) $row['pm_time_in']);
                }
            }

            // Credited total equals the recorded internship total (no fabricated totals).
            $this->assertEquals((float) $internship->fresh()->total_hours_rendered, $this->validatedHours($internship), $sn);
        }

        $this->assertEquals(500.0, $this->validatedHours($this->internship('2300592')));
        $this->assertEquals(300.0, $this->validatedHours($this->internship('2300613')));
    }

    // CLR-ATT-01 / 02 / 03
    public function test_post_completion_entry_is_removed_and_completion_is_intact(): void
    {
        $clarence = $this->internship('2300592');
        $legacy = $this->reproduceAndRepair($clarence);

        $this->assertFalse($clarence->attendance()->withTrashed()->whereDate('date', self::POST_COMPLETION_DATE)->exists());
        $this->assertEquals(500.0, $this->validatedHours($clarence));
        $this->assertEquals(500.0, (float) $clarence->fresh()->total_hours_rendered);
        $this->assertSame('completed', $clarence->fresh()->status);

        // The legacy row now holds correctly stored daytime times with a lunch break.
        $this->assertMatchesRegularExpression('/^0[78]:\d{2}$/', ManilaAttendanceClock::resolveEventAt($legacy->date, $legacy->clock_in)->format('H:i'));
        $this->assertNotNull($legacy->break_start);
        $this->assertEquals(8.0, (float) $legacy->hours_rendered);

        // Idempotent: a second run changes nothing.
        $before = $clarence->attendance()->orderBy('id')->get(['id', 'clock_in', 'clock_out', 'break_start', 'hours_rendered'])->toArray();
        Artisan::call('interntrack:repair-controlled-attendance');
        $this->assertSame($before, $clarence->attendance()->orderBy('id')->get(['id', 'clock_in', 'clock_out', 'break_start', 'hours_rendered'])->toArray());
    }

    // CLR-ATT-04 / 05
    public function test_no_surface_shows_the_removed_entry(): void
    {
        $clarence = $this->internship('2300592');
        $this->reproduceAndRepair($clarence);
        $student = User::findOrFail($clarence->student_id);

        $fo30Dates = collect(app(OfficialFormDataService::class)->fo30($clarence->fresh())['logs'])->pluck('date');
        $this->assertNotContains(self::POST_COMPLETION_DATE, $fo30Dates);
        $html = view('pdf.form30_dtr', app(OfficialFormDataService::class)->pdfDtr($clarence->fresh()))->render();
        $this->assertStringNotContainsString('1:04 AM', $html);
        $this->assertStringNotContainsString('1:00 AM', $html);

        Sanctum::actingAs($student);
        $studentDates = collect($this->getJson('/api/v1/student/attendance')->assertOk()->json('attendance.data'))->pluck('date_display');
        $this->assertNotContains(self::POST_COMPLETION_DATE, $studentDates);
        $this->assertSame('2026-08-28', $studentDates->first(), 'latest row is the last working day');

        Sanctum::actingAs(User::findOrFail($clarence->supervisor_id));
        $supervisorDates = collect($this->getJson('/api/v1/supervisor/dtr/history?internship_id='.$clarence->id)->assertOk()->json('data'))->pluck('date_display');
        $this->assertNotContains(self::POST_COMPLETION_DATE, $supervisorDates);

        Sanctum::actingAs(User::findOrFail($clarence->faculty_id));
        $facultyDates = collect($this->getJson('/api/v1/faculty/attendance?internship_id='.$clarence->id)->assertOk()->json('data'))->pluck('date_display');
        $this->assertNotContains(self::POST_COMPLETION_DATE, $facultyDates);
        $progress = $this->getJson('/api/v1/faculty/students/'.$student->id.'/progress')->assertOk();
        $this->assertNotContains(self::POST_COMPLETION_DATE, collect($progress->json('attendance_logs'))->pluck('date'));

        Sanctum::actingAs(User::findOrFail($clarence->coordinator_id));
        $coord = $this->getJson('/api/v1/coordinator/students/'.$student->id.'/progress')->assertOk();
        $this->assertNotContains(self::POST_COMPLETION_DATE, collect($coord->json('attendance_logs'))->pluck('date'));
        $this->assertEquals(500.0, (float) $coord->json('progress.hours_rendered'));
        $this->assertSame('completed', $coord->json('internship.status'));
    }

    public function test_reseeding_is_idempotent_and_keeps_daytime_times(): void
    {
        $clarence = $this->internship('2300592');
        $snapshot = fn () => $clarence->attendance()->orderBy('date')->get(['date', 'clock_in', 'clock_out', 'break_start', 'break_end', 'hours_rendered'])->toArray();
        $before = $snapshot();

        Artisan::call('interntrack:seed-ccs-demo');

        $this->assertSame($before, $snapshot());
        $this->assertSame(1, $clarence->attendance()->whereDate('date', '2026-08-28')->count());
        $this->assertFalse($clarence->attendance()->whereDate('date', '>', $clarence->fresh()->end_date->toDateString())->exists());
    }
}
