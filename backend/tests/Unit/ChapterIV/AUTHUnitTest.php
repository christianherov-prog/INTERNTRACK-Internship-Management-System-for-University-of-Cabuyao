<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class AUTHUnitTest extends IsolatedTestCase
{
    public function test_UT_AUTH_03_U(): void
    {
        $u = new User(['login_username'=>'supervisor.login','faculty_number'=>'SUP-0123','email'=>'s@example.test']); $this->assertSame('supervisor.login',$u->username); $this->assertSame('SUP-0123',$u->account_id);
    }

    public function test_UT_AUTH_05_U(): void
    {
        $r = Request::create('/'); $r->setUserResolver(fn()=>new User(['role'=>'student'])); $response=(new EnsureUserHasRole)->handle($r, function(){ $this->fail('Unauthorized action executed'); }, 'faculty'); $this->assertSame(403,$response->getStatusCode());
    }

    public function test_UT_AUTH_04_U(): void
    {
        $this->assertFalse((new User(['role'=>'coordinator']))->hasExactRole('faculty'));
    }
}

