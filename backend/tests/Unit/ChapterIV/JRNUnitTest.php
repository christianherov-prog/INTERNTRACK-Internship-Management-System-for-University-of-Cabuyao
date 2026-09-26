<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class JRNUnitTest extends IsolatedTestCase
{
    public function test_UT_JRN_02_U(): void
    {
        $i=new Internship(['status'=>'active','company_id'=>1,'start_date'=>'2026-09-01']); try {(new \App\Services\JournalPeriodValidator)->validateAndResolveWeek($i,'2026-08-31','2026-09-04'); $this->fail('Early journal accepted');} catch(\Illuminate\Validation\ValidationException $e){$this->assertSame([\App\Services\JournalPeriodValidator::MSG_BEFORE_START],$e->errors()['date']);}
    }

    public function test_UT_JRN_05_U(): void
    {
        $i=new Internship(['status'=>'active','company_id'=>1,'start_date'=>'2026-09-01']); try {(new \App\Services\JournalPeriodValidator)->validateAndResolveWeek($i,'2026-09-04','2026-09-02'); $this->fail('Reversed period accepted');} catch(\Illuminate\Validation\ValidationException $e){$this->assertSame([\App\Services\JournalPeriodValidator::MSG_RANGE_ORDER],$e->errors()['end_date']);}
    }

    public function test_UT_JRN_WEEK(): void
    {
        $v=new \App\Services\JournalPeriodValidator; $this->assertSame(1,$v->weekNumberFor('2026-09-01','2026-09-01')); $this->assertSame(2,$v->weekNumberFor('2026-09-01','2026-09-08'));
    }

    public function test_UT_JRN_13_U(): void
    {
        $this->assertSame('September 7–11, 2026',\App\Support\Fo31JournalPresenter::dateRange('2026-09-07','2026-09-11')); $this->assertSame('Week 2',\App\Support\Fo31JournalPresenter::weekLabel(2));
    }
}

