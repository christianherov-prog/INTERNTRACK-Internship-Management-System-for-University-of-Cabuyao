<?php
namespace Tests\Feature\ChapterIV;
use Tests\TestCase;
use Tests\Support\CreatesInternshipFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Mail};
use Laravel\Sanctum\Sanctum;
use App\Models\{User,Internship,JournalEntry,Evaluation,Notification,Document,OjtRequirementTemplate};
use Carbon\Carbon;
class FocusedComponentsTest extends TestCase
{
    use RefreshDatabase, CreatesInternshipFixtures;
    protected function setUp(): void {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('interntrack_testing', \Illuminate\Support\Facades\DB::connection()->getDatabaseName());
        Storage::fake('local'); Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00','Asia/Manila'));
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function party(): array {
        $faculty=$this->makeUser('faculty'); $this->mapFacultyForSection($faculty);
        $coordinator=$this->makeUser('coordinator'); $supervisor=$this->makeUser('supervisor');
        $student=$this->makeStudentWithSection(); $company=$this->makeEligibleCompany();
        $internship=$this->makeActiveInternship($student,$company,$supervisor,$faculty,$coordinator);
        $internship->update(['start_date'=>'2026-09-01']);
        return compact('faculty','coordinator','supervisor','student','company','internship');
    }
    private function journal(array $p): JournalEntry {
        return JournalEntry::create(['internship_id'=>$p['internship']->id,'week_number'=>1,'entry_number'=>1,'date'=>'2026-09-01','end_date'=>'2026-09-05','status'=>'submitted','activities_summary'=>'Unit journal evidence']);
    }
    private function evaluationData(): array {
        return ['evaluation_period'=>'midterm','form_type'=>'FO-24','responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),90)];
    }
    public function test_UT_APP_01(): void
    {
        $u=$this->makeUser('faculty'); Sanctum::actingAs($u); $this->postJson('/api/v1/meetings',['title'=>'Unit appointment','type'=>'check_in','starts_at'=>'2026-09-21T09:00:00+08:00'])->assertCreated(); $this->assertDatabaseHas('meetings',['title'=>'Unit appointment','created_by'=>$u->id,'status'=>'scheduled']);
    }

    public function test_UT_APP_02(): void
    {
        Sanctum::actingAs($this->makeUser('faculty')); $this->postJson('/api/v1/meetings',[])->assertUnprocessable()->assertJsonValidationErrors(['title','type','starts_at']);
    }

    public function test_UT_APP_03(): void
    {
        Sanctum::actingAs($this->makeUser('faculty')); $this->postJson('/api/v1/meetings',['title'=>'Reversed','type'=>'check_in','starts_at'=>'2026-09-21 10:00','ends_at'=>'2026-09-21 09:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at'); $this->assertDatabaseMissing('meetings',['title'=>'Reversed']);
    }

    public function test_UT_APP_04_ATOMIC(): void
    {
        $p=$this->party(); Sanctum::actingAs($this->makeUser('coordinator')); $this->postJson('/api/v1/meetings',['title'=>'Unauthorized atomic probe','type'=>'check_in','starts_at'=>'2026-09-21 09:00','internship_id'=>$p['internship']->id])->assertForbidden(); $this->assertDatabaseMissing('meetings',['title'=>'Unauthorized atomic probe']);
    }

    public function test_UT_NOTIF_01(): void
    {
        $u=$this->makeUser('student'); $v=$this->makeUser('student'); $a=Notification::create(['user_id'=>$u->id,'type'=>'unit','title'=>'Own','message'=>'Private own']); $b=Notification::create(['user_id'=>$v->id,'type'=>'unit','title'=>'Other','message'=>'Private other']); Sanctum::actingAs($u); $r=$this->getJson('/api/v1/notifications')->assertOk(); $r->assertJsonFragment(['message'=>'Private own'])->assertJsonMissing(['message'=>'Private other']);
    }

    public function test_UT_NOTIF_03(): void
    {
        $u=$this->makeUser('student'); $n=Notification::create(['user_id'=>$u->id,'type'=>'unit','title'=>'Own','message'=>'Read probe']); $this->assertNull($n->read_at); Sanctum::actingAs($u); $this->postJson('/api/v1/notifications/'.$n->id.'/read')->assertOk(); $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_UT_NOTIF_04(): void
    {
        $u=$this->makeUser('student'); $n=Notification::create(['user_id'=>$u->id,'type'=>'unit','title'=>'Own','message'=>'Read probe']); Sanctum::actingAs($this->makeUser('student')); $this->postJson('/api/v1/notifications/'.$n->id.'/read')->assertNotFound(); $this->assertNull($n->fresh()->read_at);
    }

    public function test_UT_FDBK_03(): void
    {
        $p=$this->party(); Sanctum::actingAs($p['supervisor']); $this->postJson('/api/v1/supervisor/feedback/'.$p['internship']->id,['feedback'=>''])->assertUnprocessable()->assertJsonValidationErrors('feedback');
    }

    public function test_UT_FDBK_01(): void
    {
        $p=$this->party(); $n=app(\App\Services\SupervisorFeedbackService::class)->upsert($p['internship'],$p['supervisor'],'Sustained improvement in assigned tasks.'); $this->assertDatabaseHas('journal_entries',['id'=>$n->id,'status'=>'supervisor_note','supervisor_reviewed_by'=>$p['supervisor']->id,'supervisor_feedback'=>'Sustained improvement in assigned tasks.']);
    }

    public function test_UT_EVAL_03(): void
    {
        $p=$this->party(); Sanctum::actingAs($p['supervisor']); $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id,[])->assertUnprocessable()->assertJsonValidationErrors(['evaluation_period','form_type','responses']);
    }

    public function test_UT_EVAL_02(): void
    {
        $p=$this->party(); Sanctum::actingAs($p['supervisor']); $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id,$this->evaluationData())->assertForbidden()->assertJsonPath('message','Waiting for Faculty approval of the evaluation period.'); $this->assertDatabaseMissing('evaluations',['internship_id'=>$p['internship']->id]);
    }

    public function test_UT_EVAL_05(): void
    {
        $p=$this->party(); $this->approveEvaluationPeriod($p['internship'],$p['faculty']); Sanctum::actingAs($p['supervisor']); $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id,$this->evaluationData())->assertCreated(); $e=Evaluation::where('internship_id',$p['internship']->id)->sole(); $this->assertSame($p['supervisor']->id,(int)$e->evaluated_by); $this->assertNotNull($e->submitted_at); $this->assertEquals(90,$e->average_score);
    }

    public function test_UT_EVAL_07(): void
    {
        $p=$this->party(); Sanctum::actingAs($this->makeUser('supervisor')); $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id,$this->evaluationData())->assertNotFound(); $this->assertDatabaseMissing('evaluations',['internship_id'=>$p['internship']->id]);
    }

    public function test_UT_DOC_09_10(): void
    {
        $p=$this->party(); OjtRequirementTemplate::query()->update(['is_active'=>false]); foreach(['Alpha','Beta','Gamma'] as $name){$t=OjtRequirementTemplate::create(['name'=>$name,'is_active'=>true,'is_system'=>true,'category'=>'general','created_by'=>$p['faculty']->id]); if($name!=='Gamma'){Document::create(['internship_id'=>$p['internship']->id,'document_type'=>$name,'requirement_template_id'=>$t->id,'status'=>$name==='Alpha'?'approved':'pending_review','submitted_at'=>now()]);}} $r=app(\App\Services\DocumentComplianceService::class)->evaluateStudent($p['student'],$p['internship']); $this->assertSame(3,$r['required_count']); $this->assertSame(1,$r['satisfied_count']); $this->assertSame(1,$r['pending_count']); $this->assertSame(1,$r['missing_count']); $this->assertSame(33,$r['compliance_pct']);
    }

    public function test_UT_DOC_EVAL_SCOPE(): void
    {
        $p=$this->party(); Evaluation::create(['internship_id'=>$p['internship']->id,'evaluator_type'=>'supervisor','evaluated_by'=>$p['supervisor']->id,'evaluation_period'=>'final','form_type'=>'FO-24','responses'=>['c1'=>90],'submitted_at'=>now()]); $t=new OjtRequirementTemplate(['name'=>'Host Evaluation','system_code'=>'host_evaluation']); $m=new \ReflectionMethod(\App\Services\DocumentComplianceService::class,'resolveTemplateState'); $r=$m->invoke(app(\App\Services\DocumentComplianceService::class),$t,$p['internship'],collect()); $this->assertSame('missing',$r['status'],'FO-24 must not complete a different official evaluation requirement.');
    }

    public function test_UT_JRN_08(): void
    {
        $p=$this->party(); $j=$this->journal($p); Sanctum::actingAs($p['faculty']); $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertOk(); $this->assertSame('approved',$j->fresh()->status); $this->assertSame($p['faculty']->id,(int)$j->fresh()->faculty_reviewed_by);
    }

    public function test_UT_JRN_09(): void
    {
        $p=$this->party(); $j=$this->journal($p); Sanctum::actingAs($this->makeUser('faculty')); $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertNotFound(); $this->assertSame('submitted',$j->fresh()->status);
    }

    public function test_UT_JRN_10(): void
    {
        $p=$this->party(); $j=$this->journal($p); Sanctum::actingAs($p['supervisor']); $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertForbidden(); $this->assertSame('submitted',$j->fresh()->status);
    }

    public function test_UT_JRN_11(): void
    {
        $p=$this->party(); $p['internship']->update(['faculty_id'=>$p['coordinator']->id]); $j=$this->journal($p); Sanctum::actingAs($p['coordinator']); $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertOk();
        $this->assertSame('approved', $j->fresh()->status);
        $this->assertSame($p['coordinator']->id, (int) $j->fresh()->faculty_reviewed_by);
    }

    public function test_UT_ADM_02(): void
    {
        $a=$this->makeUser('admin'); $u=$this->makeUser('faculty'); Sanctum::actingAs($a); $this->putJson('/api/v1/admin/staff/'.$u->id,['is_active'=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors('is_active'); $this->assertTrue($u->fresh()->is_active);
    }

    public function test_UT_ADM_05(): void
    {
        Sanctum::actingAs($this->makeUser('student')); $this->getJson('/api/v1/admin/audit-log')->assertForbidden();
    }

    public function test_UT_ADM_06(): void
    {
        Sanctum::actingAs($this->makeUser('admin')); $this->postJson('/api/v1/admin/section-assignments',[])->assertUnprocessable();
    }

    public function test_UT_DOC_02_04(): void { $p=$this->party(); OjtRequirementTemplate::create(['name'=>'Unit PDF','is_active'=>true,'is_system'=>true,'created_by'=>$p['faculty']->id]); Sanctum::actingAs($p['student']); $f=\Illuminate\Http\UploadedFile::fake()->create('proof.pdf',20,'application/pdf'); $this->post('/api/v1/student/documents/upload',['document_type'=>'Unit PDF','files'=>[$f]],['Accept'=>'application/json'])->assertCreated(); $d=Document::where('internship_id',$p['internship']->id)->where('document_type','Unit PDF')->sole(); $a=$d->attachments()->sole(); $this->assertSame('proof.pdf',$a->file_name); $this->assertSame('application/pdf',$a->mime_type); $this->assertSame(20480,(int)$a->file_size); Storage::disk('local')->assertExists($a->file_path); }
    public function test_UT_DOC_03(): void { $p=$this->party(); OjtRequirementTemplate::create(['name'=>'Unit invalid file','is_active'=>true,'is_system'=>true,'created_by'=>$p['faculty']->id]); Sanctum::actingAs($p['student']); $this->post('/api/v1/student/documents/upload',['document_type'=>'Unit invalid file','files'=>[\Illuminate\Http\UploadedFile::fake()->create('bad.exe',20,'application/x-msdownload')]],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('files.0'); $this->assertDatabaseMissing('documents',['document_type'=>'Unit invalid file']); }
    public function test_UT_DOC_13(): void { $p=$this->party(); Sanctum::actingAs($p['coordinator']); $this->postJson('/api/v1/coordinator/requirements',['name'=>'Unit custom','targets'=>[['type'=>'section','id'=>'4ITD']]])->assertCreated(); $t=OjtRequirementTemplate::where('name','Unit custom')->sole(); $this->assertFalse((bool)$t->is_system); $this->assertSame($p['coordinator']->id,(int)$t->created_by); $this->assertTrue($t->targets()->where('target_type','section')->where('target_id','4ITD')->exists()); }
    public function test_UT_PROFILE_06(): void { $p=$this->party(); $i=$p['student']->fresh()->activeInternship; $this->assertSame($p['internship']->id,$i->id); $this->assertSame($p['company']->id,$i->company_id); $this->assertSame($p['supervisor']->id,$i->supervisor_id); }
    public function test_UT_ADM_04(): void { $u=$this->makeUser('admin'); audit_log($u->id,'unit.audit_probe',['unit_marker'=>'chapter-iv']); $log=\App\Models\AuditLog::where('user_id',$u->id)->where('action','unit.audit_probe')->sole(); $this->assertSame($u->id,(int)$log->user_id); $this->assertStringContainsString('chapter-iv',json_encode($log->toArray())); }
    private function invite(array $p): \App\Models\SupervisorInviteToken {
        return \App\Models\SupervisorInviteToken::create(['internship_id'=>$p['internship']->id,'student_id'=>$p['student']->id,'supervisor_user_id'=>$p['supervisor']->id,'company_id'=>$p['company']->id,'token'=>'unit-'.uniqid(),'expires_at'=>now()->addDay(),'status'=>'registered','first_name'=>'Unit','last_name'=>'Supervisor','email'=>$p['supervisor']->email]);
    }
    public function test_UT_PORT_03_04(): void
    {
        $p=$this->party(); Sanctum::actingAs($p['student']); $a=$this->post('/api/v1/student/portfolio/photos',['type'=>'company_logo','file'=>\Illuminate\Http\UploadedFile::fake()->image('first.png')],['Accept'=>'application/json'])->assertCreated()->json('document'); $b=$this->post('/api/v1/student/portfolio/photos',['type'=>'company_logo','file'=>\Illuminate\Http\UploadedFile::fake()->image('second.png')],['Accept'=>'application/json'])->assertCreated()->json('document'); Storage::disk('local')->assertMissing($a['file_path']); Storage::disk('local')->assertExists($b['file_path']); $this->assertSame(1,Document::where('internship_id',$p['internship']->id)->where('document_type','company_logo')->count());
    }

    public function test_UT_PORT_05(): void
    {
        $p=$this->party(); $d=Document::create(['internship_id'=>$p['internship']->id,'document_type'=>'company_logo','status'=>'approved']); $path='internships/'.$p['internship']->id.'/portfolio/unit.png'; Storage::disk('local')->put($path,'test-only file'); $d->attachments()->create(['file_path'=>$path,'file_name'=>'unit.png','file_size'=>14,'mime_type'=>'image/png']); Sanctum::actingAs($p['student']); $this->deleteJson('/api/v1/student/portfolio/photos/'.$d->id)->assertOk(); Storage::disk('local')->assertMissing($path); $this->assertNull(Document::find($d->id));
    }

    public function test_UT_PORT_11(): void
    {
        $p=$this->party(); $d=Document::create(['internship_id'=>$p['internship']->id,'document_type'=>'company_logo','status'=>'approved']); $path='internships/'.$p['internship']->id.'/portfolio/private.png'; Storage::disk('local')->put($path,'test-only file'); $d->attachments()->create(['file_path'=>$path,'file_name'=>'private.png','file_size'=>14,'mime_type'=>'image/png']); Sanctum::actingAs($this->makeUser('student')); $this->deleteJson('/api/v1/student/portfolio/photos/'.$d->id)->assertForbidden(); Storage::disk('local')->assertExists($path); $this->assertNotNull(Document::find($d->id));
    }

    public function test_UT_ATT_13(): void
    {
        $p=$this->party(); $l=\App\Models\AttendanceLog::create(['internship_id'=>$p['internship']->id,'date'=>'2026-09-17','clock_in'=>'08:00:00','status'=>'pending']); Sanctum::actingAs($p['student']); $this->postJson('/api/v1/student/attendance/corrections',['date'=>'2026-09-17','correction_type'=>'clock_out','requested_clock_out'=>'17:00','reason'=>'Forgot to clock out.'])->assertCreated()->assertJsonPath('correction.status','pending_supervisor'); $this->assertNull($l->fresh()->clock_out); $this->assertGreaterThan(0,\App\Models\DtrRequestAudit::count());
    }

    public function test_UT_SUP_07_10(): void
    {
        $p=$this->party(); $p['internship']->update(['supervisor_id'=>null]); $p['supervisor']->update(['is_active'=>false]); $n=$this->invite($p); $count=User::count(); Sanctum::actingAs($p['faculty']); $this->patchJson('/api/v1/faculty/supervisor-approvals/'.$n->id.'/approve')->assertOk(); $this->assertSame('approved',$n->fresh()->status); $this->assertSame($p['supervisor']->id,(int)$p['internship']->fresh()->supervisor_id); $this->assertTrue($p['supervisor']->fresh()->is_active); $this->assertSame($count,User::count());
    }

    public function test_UT_SUP_11(): void
    {
        $p=$this->party(); $p['internship']->update(['supervisor_id'=>null]); $p['supervisor']->update(['is_active'=>false]); $n=$this->invite($p); Sanctum::actingAs($p['faculty']); $this->patchJson('/api/v1/faculty/supervisor-approvals/'.$n->id.'/reject',['remarks'=>'Acceptance form needs correction'])->assertOk(); $this->assertSame('rejected',$n->fresh()->status); $this->assertNull($p['internship']->fresh()->supervisor_id); $this->assertFalse($p['supervisor']->fresh()->is_active);
    }

    public function test_UT_SUP_COORDINATOR_DEPARTMENT_APPROVAL(): void
    {
        $p=$this->party(); $p['internship']->update(['supervisor_id'=>null]); $n=$this->invite($p); Sanctum::actingAs($p['coordinator']); $this->patchJson('/api/v1/faculty/supervisor-approvals/'.$n->id.'/approve')->assertOk();
        $this->assertSame('approved', $n->fresh()->status);
        $this->assertSame($p['supervisor']->id, (int) $p['internship']->fresh()->supervisor_id);
    }

    public function test_UT_DOC_15(): void
    {
        $p=$this->party(); $t=OjtRequirementTemplate::create(['name'=>'Private template','is_active'=>true,'is_system'=>true,'category'=>'general','created_by'=>$p['faculty']->id]); Sanctum::actingAs($p['student']); $this->postJson('/api/v1/faculty/requirements/'.$t->id,['name'=>'Unauthorized edit'])->assertForbidden(); $this->assertSame('Private template',$t->fresh()->name);
    }

    public function test_UT_RPT_03(): void
    {
        $p=$this->party(); foreach([['2026-09-16','validated',8],['2026-09-17','pending',4]] as [$date,$status,$hours]){\App\Models\AttendanceLog::create(['internship_id'=>$p['internship']->id,'date'=>$date,'status'=>$status,'hours_rendered'=>$hours]);} $this->assertSame(8.0,$p['internship']->computeTotalHours());
    }
}
