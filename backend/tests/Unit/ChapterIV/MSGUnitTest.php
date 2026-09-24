<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class MSGUnitTest extends IsolatedTestCase
{
    public function test_UT_MSG_02_U(): void
    {
        $i=new Internship(['student_id'=>11,'faculty_id'=>22]); $this->assertTrue($i->isParticipant(22)); $this->assertFalse($i->isParticipant(99));
    }
}

