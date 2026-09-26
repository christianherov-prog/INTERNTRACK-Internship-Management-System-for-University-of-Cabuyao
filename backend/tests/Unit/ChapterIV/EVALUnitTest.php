<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class EVALUnitTest extends IsolatedTestCase
{
    public function test_UT_EVAL_04_96(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),96)]); $e->computeScores(); $this->assertEqualsWithDelta(96,$e->average_score,0.001); $this->assertSame('Excellent',$e->rating);
    }

    public function test_UT_EVAL_04_90(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),90)]); $e->computeScores(); $this->assertEqualsWithDelta(90,$e->average_score,0.001); $this->assertSame('Very Good',$e->rating);
    }

    public function test_UT_EVAL_04_85(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),85)]); $e->computeScores(); $this->assertEqualsWithDelta(85,$e->average_score,0.001); $this->assertSame('Good',$e->rating);
    }

    public function test_UT_EVAL_04_80(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),80)]); $e->computeScores(); $this->assertEqualsWithDelta(80,$e->average_score,0.001); $this->assertSame('Fair',$e->rating);
    }

    public function test_UT_EVAL_04_75(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),75)]); $e->computeScores(); $this->assertEqualsWithDelta(75,$e->average_score,0.001); $this->assertSame('Passed',$e->rating);
    }

    public function test_UT_EVAL_04_74(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),74)]); $e->computeScores(); $this->assertEqualsWithDelta(74,$e->average_score,0.001); $this->assertSame('Failed',$e->rating);
    }

    public function test_UT_EVAL_04_MIXED(): void
    {
        $e=new \App\Models\Evaluation(['form_type'=>'FO-22','responses'=>['q1'=>5,'q2'=>4,'comment'=>'text','other'=>100]]); $e->computeScores(); $this->assertSame(4.5,$e->average_score); $this->assertSame(9.0,$e->total_score); $this->assertSame('Outstanding',$e->rating);
    }

    public function test_UT_EVAL_06_U(): void
    {
        $e=new \App\Models\Evaluation; $e->computeScores(); $this->assertNull($e->average_score); $this->assertNull($e->submitted_at);
    }
}

