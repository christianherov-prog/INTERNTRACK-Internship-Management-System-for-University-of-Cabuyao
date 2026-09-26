<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class RPTUnitTest extends IsolatedTestCase
{
    public function test_UT_RPT_01_500_80(): void
    {
        $i=new Internship(['total_hours_rendered'=>80,'target_hours'=>500]); $this->assertEquals(16,$i->progress_percent);
    }

    public function test_UT_RPT_01_500_550(): void
    {
        $i=new Internship(['total_hours_rendered'=>550,'target_hours'=>500]); $this->assertEquals(100,$i->progress_percent);
    }

    public function test_UT_RPT_01_0_80(): void
    {
        $i=new Internship(['total_hours_rendered'=>80,'target_hours'=>0]); $this->assertEquals(0,$i->progress_percent);
    }

    public function test_UT_RPT_05_U(): void
    {
        $this->assertSame('For Evaluation',InternshipStatuses::label('for_evaluation'));
    }

    public function test_UT_RPT_06_U(): void
    {
        $this->assertSame('1:03 PM',\App\Support\OfficialFormAsset::clockLabel('13:03'));
    }
}

