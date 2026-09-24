<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,AttendanceLog};
class SupplementalUnitTest extends IsolatedTestCase {
    public function test_UT_ATT_06_U(): void { $s=app(\App\Services\DtrWorkflowService::class); $this->assertSame(60,$s->breakMinutesFor(new AttendanceLog(['break_start'=>'2026-09-18 04:00:00','break_end'=>'2026-09-18 05:00:00']))); $this->assertSame(0,$s->breakMinutesFor(new AttendanceLog(['break_start'=>'2026-09-18 04:00:00']))); }
    public function test_UT_EVAL_04_WEIGHTED(): void { $r=array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),80); $r['c1']=100; $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>$r]); $e->computeScores(); $this->assertEqualsWithDelta(85,$e->average_score,0.00001); $this->assertSame('Good',$e->rating); }
    public function test_UT_PORT_08_U(): void { $e=new \App\Models\Evaluation(['form_type'=>'FO-24','responses'=>['c1'=>100]]); $e->setRelation('evaluator',null); $s=new \App\Services\PortfolioDataService; $a=$this->invoke($s,'serializeEvaluation',$e,[]); $this->assertSame('pending',$a['status']); $this->assertSame('FO-24',$a['form_type']); $this->assertSame(25.0,$a['responses']['eq1']); $e->submitted_at='2026-09-18 05:00:00'; $b=$this->invoke($s,'serializeEvaluation',$e,[]); $this->assertSame('completed',$b['status']); $this->assertSame('2026-09-18T13:00:00+08:00',$b['submitted_at']); }
    public function test_UT_PORT_06_U(): void { $j=new \App\Models\JournalEntry(['status'=>'approved','week_number'=>2,'date'=>'2026-09-07','end_date'=>'2026-09-11','activities_summary'=>'Validated activity']); $r=$this->invoke(new \App\Services\PortfolioDataService,'serializeJournal',$j); $this->assertSame('approved',$r['status']); $this->assertSame(2,$r['week_number']); $this->assertSame('Validated activity',$r['activities_summary']); $this->assertSame('2026-09-07',$r['date']); }
    public function test_UT_ADM_03_U(): void { try{app(\App\Services\StaffAssignmentService::class)->assign('FAC-UNIT','student',new User(['role'=>'admin'])); $this->fail('Invalid role accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertSame(['Role must be director or coordinator.'],$e->errors()['role']);} }
}

