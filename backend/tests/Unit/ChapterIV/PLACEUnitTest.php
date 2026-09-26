<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class PLACEUnitTest extends IsolatedTestCase
{
    public function test_UT_PLACE_02_U(): void
    {
        $this->assertSame('active',InternshipStatuses::normalize(' ONGOING ')); $this->assertNotContains('completed',InternshipStatuses::openCurrent());
    }

    public function test_UT_PLACE_04_U(): void
    {
        $this->assertFalse(InternshipAccess::canManageAsCoordinator(new User(['role'=>'student']),new Internship));
    }

    public function test_UT_PLACE_06_U(): void
    {
        $i=new Internship(['student_id'=>11,'faculty_id'=>22,'coordinator_id'=>22,'supervisor_id'=>null]); $this->assertSame([11,22],$i->participantUserIds());
    }
}

