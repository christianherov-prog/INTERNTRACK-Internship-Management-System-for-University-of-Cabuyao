<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class PROFILEUnitTest extends IsolatedTestCase
{
    public function test_UT_PROFILE_03_U(): void
    {
        $v=Validator::make(['email'=>'invalid','contact'=>str_repeat('x',41)],(new \App\Http\Requests\Auth\UpdateProfileRequest)->rules()); $this->assertTrue($v->fails()); $this->assertTrue($v->errors()->has('email')); $this->assertTrue($v->errors()->has('contact'));
    }

    public function test_UT_PROFILE_02_U(): void
    {
        $v=Validator::make(['email'=>'student@example.test','contact'=>'09123456789'],(new \App\Http\Requests\Auth\UpdateProfileRequest)->rules()); $this->assertFalse($v->fails());
    }

    public function test_UT_PROFILE_08_U(): void
    {
        $u=new User(['role'=>'student']); $u->id=8; $this->assertFalse(InternshipAccess::canView($u,new Internship(['student_id'=>9])));
    }
}

