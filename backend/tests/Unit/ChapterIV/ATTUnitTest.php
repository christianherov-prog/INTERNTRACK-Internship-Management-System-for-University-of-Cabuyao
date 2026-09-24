<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class ATTUnitTest extends IsolatedTestCase
{
    public function test_UT_ATT_05_U(): void
    {
        $this->assertSame(7.0,ManilaAttendanceClock::creditedHours('09:00','12:00','13:00','17:00'));
    }

    public function test_UT_ATT_08_U(): void
    {
        $dtr=\Mockery::mock(\App\Services\DtrWorkflowService::class); $dtr->shouldReceive('activeScheduleFor')->andReturn(new \App\Models\WorkSchedule(['end_time'=>'17:00'])); $resolver=new \App\Services\AttendanceDayResolver($dtr); $i=new Internship(['start_date'=>'2026-09-01']); $this->assertFalse($resolver->isFullDayAbsent($i,'2026-09-18',null,Carbon::parse('2026-09-18 16:59','Asia/Manila'))); $this->assertTrue($resolver->isFullDayAbsent($i,'2026-09-18',null,Carbon::parse('2026-09-18 17:00','Asia/Manila')));
    }

    public function test_UT_ATT_07_U(): void
    {
        $dtr=\Mockery::mock(\App\Services\DtrWorkflowService::class); $dtr->shouldReceive('activeScheduleFor')->andReturn(new \App\Models\WorkSchedule(['end_time'=>'17:00'])); $resolver=new \App\Services\AttendanceDayResolver($dtr); $log=new AttendanceLog(['date'=>'2026-09-18','clock_in'=>ManilaAttendanceClock::storedTime('13:00')]); $this->assertFalse($resolver->isFullDayAbsent(new Internship(['start_date'=>'2026-09-01']),'2026-09-18',$log));
    }

    public function test_UT_ATT_08_WEEKEND(): void
    {
        $dtr=\Mockery::mock(\App\Services\DtrWorkflowService::class); $dtr->shouldNotReceive('activeScheduleFor'); $this->assertFalse((new \App\Services\AttendanceDayResolver($dtr))->isFullDayAbsent(new Internship,'2026-09-12'));
    }

    public function test_UT_ATT_11_U(): void
    {
        $this->assertSame('2026-09-19',ManilaTime::todayDateString(Carbon::parse('2026-09-18 16:01','UTC')));
    }

    public function test_UT_ATT_15_U(): void
    {
        $l=new AttendanceLog(['date'=>'2026-09-18','clock_in'=>ManilaAttendanceClock::storedTime('13:00'),'hours_rendered'=>3.25,'status'=>'pending']); $r=(new \App\Services\PortfolioDataService)->serializeAttendance($l,['supervisor_signature_path'=>'signature.png']); $this->assertSame(3.25,$r['hours_rendered']); $this->assertNull($r['hte_signature_path']); $this->assertSame('13:00',$r['pm_time_in']);
    }
}

