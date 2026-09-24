<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class APPUnitTest extends IsolatedTestCase
{
    public function test_UT_APP_04_U(): void
    {
        $r=Request::create('/','POST'); $r->setUserResolver(fn()=>new User(['role'=>'student'])); try{(new \App\Http\Controllers\Api\MeetingController)->store($r); $this->fail('Student accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
}

