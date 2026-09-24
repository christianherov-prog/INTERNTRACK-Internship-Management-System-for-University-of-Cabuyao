<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class FDBKUnitTest extends IsolatedTestCase
{
    public function test_UT_FDBK_02_U(): void
    {
        $u=new User(['role'=>'supervisor']); $u->id=8; try{(new \App\Services\SupervisorFeedbackService)->assertAssignedSupervisor($u,new Internship(['supervisor_id'=>7])); $this->fail('Unrelated supervisor accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }

    public function test_UT_FDBK_07_U(): void
    {
        $n=new \App\Models\JournalEntry(['status'=>'supervisor_note','supervisor_feedback'=>'Observed feedback','supervisor_reviewed_at'=>'2026-09-18 05:00:00']); $n->setRelation('internship',null); $r=(new \App\Services\SupervisorFeedbackService)->serialize($n); $this->assertSame('Sep 18, 2026 1:00 PM',$r['supervisor_reviewed_at_manila']); $this->assertSame('Observed feedback',$r['feedback']);
    }
}

