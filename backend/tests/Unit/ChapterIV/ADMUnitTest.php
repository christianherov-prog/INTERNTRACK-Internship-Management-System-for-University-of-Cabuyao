<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class ADMUnitTest extends IsolatedTestCase
{
    public function test_UT_ADM_01_U(): void
    {
        $r=Request::create('/'); $r->setUserResolver(fn()=>new User(['role'=>'faculty'])); $response=(new EnsureUserHasRole)->handle($r,function(){$this->fail('Admin operation executed');},'admin'); $this->assertSame(403,$response->getStatusCode());
    }

    public function test_UT_ADM_04_U(): void
    {
        $c=app(\App\Http\Controllers\Api\MisdAdminController::class); $r=$this->invoke($c,'sanitizeAuditPayload',['role'=>'student','password'=>'secret','nested'=>['token'=>'secret','status'=>'active']]); $this->assertSame(['role'=>'student','nested'=>['status'=>'active']],$r);
    }
}

