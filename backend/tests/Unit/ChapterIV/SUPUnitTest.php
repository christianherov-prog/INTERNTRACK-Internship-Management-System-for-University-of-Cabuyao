<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class SUPUnitTest extends IsolatedTestCase
{
    public function test_UT_SUP_12_U(): void
    {
        $u=new User(['role'=>'supervisor']); $u->id=8; $this->assertSame(false,InternshipAccess::canView($u,new Internship(['supervisor_id'=>7])));
    }

    public function test_UT_SUP_13_U(): void
    {
        $u=new User(['role'=>'supervisor']); $u->id=7; $this->assertSame(true,InternshipAccess::canView($u,new Internship(['supervisor_id'=>7])));
    }
}

