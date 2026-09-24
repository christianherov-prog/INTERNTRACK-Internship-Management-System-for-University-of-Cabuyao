<?php

namespace Tests\Feature\ChapterIVIntegration;

use App\Models\{AttendanceLog,AuditLog,Document,Evaluation,Internship,InternshipPlacement,JournalEntry,Meeting,Notification,OjtRequirementTemplate,ProgramHteRequirement,SupervisorInviteToken,SupervisorProfile,User,WorkSchedule};
use App\Services\{DocumentComplianceService,OfficialFormDataService};
use App\Support\RequiredDocuments;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Mail,Storage};
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class WorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase, CreatesInternshipFixtures;

    private array $observations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('interntrack_testing', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Mail::fake();
        RequiredDocuments::clearCache();
        $this->at('2026-09-18 08:00');
    }

    protected function tearDown(): void
    {
        // Written before rollback, including when the final business assertion fails.
        $dir = base_path(getenv('INTEGRATION_OBSERVATIONS_DIR') ?: 'tests/integration-evidence/observations');
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$this->name().'.json', json_encode($this->observations, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        Carbon::setTestNow();
        RequiredDocuments::clearCache();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Manila')->utc());
    }

    private function party(): array
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $coordinator = $this->makeUser('coordinator');
        $student = $this->makeStudentWithSection();
        $supervisor = $this->makeUser('supervisor');
        $company = $this->makeEligibleCompany(['company_name'=>'Integration HTE']);
        SupervisorProfile::create(['user_id'=>$supervisor->id,'first_name'=>'Industry','last_name'=>'Reviewer','email'=>$supervisor->email,'company_id'=>$company->id,'position'=>'Mentor']);
        $internship = $this->makeActiveInternship($student,$company,$supervisor,$faculty,$coordinator);
        $internship->update(['start_date'=>'2026-09-01','total_hours_rendered'=>0]);
        return compact('faculty','coordinator','student','supervisor','company','internship');
    }

    private function as(User $user): void { Sanctum::actingAs($user); }

    private function submitJournal(array $p, string $start='2026-09-07', string $end='2026-09-11'): JournalEntry
    {
        $this->as($p['student']);
        $this->postJson('/api/v1/student/logbook', ['date'=>$start,'end_date'=>$end,'week_number'=>2,'activities_summary'=>'Integration journal '.$start,'challenges'=>'Mapping','learnings'=>'Authoritative IDs'])->assertCreated();
        return JournalEntry::where('internship_id',$p['internship']->id)->whereDate('date',$start)->sole();
    }

    private function approveJournal(array $p): JournalEntry
    {
        $j=$this->submitJournal($p);
        $this->as($p['faculty']);
        $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertOk();
        return $j->fresh();
    }

    private function submitEvaluation(array $p): Evaluation
    {
        $this->as($p['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertOk();
        $this->as($p['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id,[
            'evaluation_period'=>'final','form_type'=>'FO-24',
            'responses'=>array_fill_keys(array_map(fn($n)=>'c'.$n,range(1,10)),80),
            'general_comments'=>'Integration evaluation',
        ])->assertCreated();
        return Evaluation::where('internship_id',$p['internship']->id)->where('form_type','FO-24')->sole();
    }

    private function templates(array $p, array $names): array
    {
        OjtRequirementTemplate::query()->update(['is_active'=>false]);
        $result=[];
        foreach($names as $name) $result[]=OjtRequirementTemplate::create(['name'=>$name,'is_system'=>true,'is_active'=>true,'created_by'=>$p['faculty']->id,'category'=>'general']);
        RequiredDocuments::clearCache();
        return $result;
    }

    private function complianceViews(array $p): array
    {
        $this->as($p['student']);
        $dash=$this->getJson('/api/v1/student/dashboard')->assertOk()->json('stats');
        $docs=$this->getJson('/api/v1/student/documents')->assertOk()->json('meta');
        $r=['student'=>['approved'=>$dash['docs_approved'],'total'=>$dash['docs_total'],'pct'=>$dash['doc_compliance']],
            'student_documents'=>['approved'=>$docs['docs_approved'],'total'=>$docs['docs_total'],'pct'=>$docs['compliance_pct']]];
        foreach(['faculty','coordinator'] as $role) {
            $this->as($p[$role]);
            $d=$this->getJson('/api/v1/'.$role.'/students/'.$p['student']->id.'/progress')->assertOk()->json('documents');
            $report=collect($this->getJson('/api/v1/'.$role.'/reports/compliance')->assertOk()->json('rows'))->firstWhere('student_number',$p['student']->student_number);
            $this->assertNotNull($report);
            $r[$role]=['approved'=>$d['approved'],'total'=>$d['total'],'pct'=>$d['compliance_pct']];
            $r[$role.'_report']=['approved'=>$report['approved_docs'],'total'=>$report['required_docs'],'pct'=>$report['compliance_pct']];
            $this->observations[$role.'_requirements']=$report['requirements'];
        }
        $this->observations['compliance']=$r;
        return $r;
    }

    public function test_INT_01_login_role_workspace_and_api_scope(): void
    {
        $p=$this->party(); $p['admin']=$this->makeUser('admin');
        foreach(['student','faculty','coordinator','supervisor','admin'] as $role) {
            $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'']);
            $login=$this->postJson('/api/v1/auth/login',['username'=>$p[$role]->username,'password'=>'password'])->assertOk();
            $this->assertSame($role,$login->json('user.role'));
            $this->assertSame($p[$role]->id,$login->json('user.id'));
            $this->app['auth']->forgetGuards();
            $this->withToken($login->json('token'));
            $summary=$this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
            $this->assertSame($role,$summary['role']);
            $this->getJson('/api/v1/'.$role.'/dashboard')->assertOk();
            $denied=$role==='admin'?'/api/v1/student/dashboard':'/api/v1/admin/audit-log';
            $this->getJson($denied)->assertForbidden();
            $this->observations[$role]=['role'=>$summary['role'],'label'=>$summary['label'],'denied'=>$denied];
        }
    }

    public function test_INT_02_profile_internship_and_placement_ids(): void
    {
        $p=$this->party();
        $r=ProgramHteRequirement::create(['program_id'=>$p['student']->studentProfile->program_id,'sequence_order'=>1,'label'=>'Primary','required_hours'=>360]);
        $placement=InternshipPlacement::create(['internship_id'=>$p['internship']->id,'program_hte_requirement_id'=>$r->id,'company_id'=>$p['company']->id,'supervisor_id'=>$p['supervisor']->id,'sequence_order'=>1,'label'=>'Primary','required_hours'=>360,'status'=>'active']);
        $p['internship']->update(['current_placement_id'=>$placement->id]);
        $this->as($p['student']);
        $dash=$this->getJson('/api/v1/student/dashboard')->assertOk()->json('internship');
        $records=$this->getJson('/api/v1/student/records')->assertOk()->json('data');
        $port=$this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame($p['internship']->id,$dash['id']);
        $this->assertSame($p['company']->company_name,$dash['company_name']);
        $row=collect($dash['placements'])->firstWhere('id',$placement->id); $this->assertNotNull($row);
        $this->assertNotNull(collect($records)->firstWhere('id',$p['internship']->id));
        $this->assertSame($p['student']->student_number,$port['identity']['student_number']);
        $this->assertSame($p['student']->studentProfile->program->name,$port['identity']['program']);
        $this->assertSame($p['company']->company_name,$port['identity']['company_name']);
        $this->assertSame($p['faculty']->id,$p['internship']->fresh()->faculty_id);
        $this->assertSame($p['student']->studentProfile->program->department_id,$p['student']->studentProfile->department_id);
        $this->as($p['faculty']);
        $this->getJson('/api/v1/faculty/students/'.$p['student']->id.'/progress')->assertOk()->assertJsonPath('internship.id',$p['internship']->id);
        $this->observations=['internship'=>$dash,'placement'=>$row,'student_number'=>$port['identity']['student_number']];
    }

    public function test_INT_04_clock_break_validation_and_progress(): void
    {
        $p=$this->party(); $this->as($p['student']);
        $id=$this->postJson('/api/v1/student/attendance/clock-in')->assertCreated()->json('record.id');
        $this->postJson('/api/v1/student/attendance/clock-in')->assertUnprocessable();
        $this->at('2026-09-18 12:00');
        $this->postJson('/api/v1/student/attendance/break-start')->assertOk();
        $this->at('2026-09-18 13:00');
        $this->postJson('/api/v1/student/attendance/break-end')->assertOk();
        $this->at('2026-09-18 17:00');
        $out=$this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $this->assertSame($id,$out->json('record.id'));
        $this->assertEquals(8,$out->json('record.hours_rendered'));
        $this->postJson('/api/v1/student/attendance/clock-out')->assertUnprocessable();
        $this->getJson('/api/v1/student/dashboard')->assertOk()->assertJsonPath('stats.hours_rendered',0);
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/supervisor/attendance/'.$id.'/validate',['action'=>'validated'])->assertOk();
        $this->as($p['student']);
        $this->assertEquals(8,$this->getJson('/api/v1/student/dashboard')->assertOk()->json('stats.hours_rendered'));
        foreach(['faculty','coordinator'] as $role) {
            $this->as($p[$role]);
            $this->assertEquals(8,$this->getJson('/api/v1/'.$role.'/students/'.$p['student']->id.'/progress')->assertOk()->json('progress.hours_rendered'));
        }
        $this->observations=['attendance_id'=>$id,'hours'=>AttendanceLog::findOrFail($id)->hours_rendered,'status'=>AttendanceLog::findOrFail($id)->status];
    }

    public function test_INT_06_schedule_absence_afternoon_and_history(): void
    {
        $p=$this->party(); $p['internship']->update(['start_date'=>'2026-09-18']);
        $this->as($p['student']);
        $schedule=$this->postJson('/api/v1/student/attendance/schedules',['start_time'=>'08:00','end_time'=>'17:00'])->assertCreated()->json('schedule.id');
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/schedules/'.$schedule,['action'=>'approved'])->assertOk();
        $this->at('2026-09-18 16:59');
        $this->as($p['student']);
        $before=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('fo30.logs');
        $this->assertFalse(collect($before)->contains(fn($r)=>($r['day_absent']??false)));
        $this->at('2026-09-18 17:01');
        $after=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('fo30.logs');
        $absent=collect($after)->firstWhere('date','2026-09-18');
        $this->assertSame(0,AttendanceLog::where('internship_id',$p['internship']->id)->count());
        // A separate next working day demonstrates afternoon presence, then historical state.
        $this->at('2026-09-21 13:00');
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->getJson('/api/v1/student/attendance')->assertOk()->assertJsonPath('today_status','clocked_in');
        $this->at('2026-09-21 17:00'); $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $rows=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('fo30.logs');
        $afternoon=collect($rows)->firstWhere('date','2026-09-21'); $this->assertNotNull($afternoon);
        $this->assertFalse($afternoon['day_absent']??false);
        $this->at('2026-09-22 08:00');
        $this->getJson('/api/v1/student/attendance')->assertOk()->assertJsonPath('today_status','not_clocked_in')->assertJsonPath('today_record',null);
        $this->getJson('/api/v1/student/dashboard')->assertOk()->assertJsonPath('stats.days_present',0);
        $this->as($p['faculty']);
        $this->getJson('/api/v1/faculty/students/'.$p['student']->id.'/progress')->assertOk()->assertJsonPath('progress.hours_rendered',0);
        $this->observations=['after_shift'=>$absent,'afternoon'=>$afternoon,'next_day'=>'not_clocked_in'];
        $this->assertNotNull($absent,'Completed first workday absent from FO-30 when no historical attendance exists.');
        $this->assertTrue($absent['day_absent']);
    }

    public function test_INT_07_journal_review_history_and_denials(): void
    {
        $p=$this->party(); $j=$this->submitJournal($p);
        $this->assertSame('submitted',$j->status);
        $this->as($this->makeUser('faculty'));
        $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertNotFound();
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertForbidden();
        $this->assertSame('submitted',$j->fresh()->status);
        $this->as($p['faculty']);
        $this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved'])->assertOk();
        $this->assertSame($p['faculty']->id,(int)$j->fresh()->faculty_reviewed_by);
        $this->as($p['student']);
        $history=$this->getJson('/api/v1/student/logbook')->assertOk()->json('data');
        $this->assertSame('approved',collect($history)->firstWhere('id',$j->id)['status']);
        $this->observations=['journal_id'=>$j->id,'status'=>$j->fresh()->status,'reviewer'=>$j->fresh()->faculty_reviewed_by];
    }

    public function test_INT_07_COORD_coordinator_review_propagation(): void
    {
        $p=$this->party(); $p['internship']->update(['faculty_id'=>$p['coordinator']->id]);
        $j=$this->submitJournal($p); $this->as($p['coordinator']);
        $response=$this->patchJson('/api/v1/faculty/journals/'.$j->id.'/review',['action'=>'approved']);
        $this->as($p['student']);
        $history=$this->getJson('/api/v1/student/logbook')->assertOk()->json('data');
        $form=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('journals');
        $portfolio=$this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.journals');
        $this->observations=['http'=>$response->status(),'database'=>$j->fresh()->only('status','faculty_reviewed_by'),
            'student'=>collect($history)->firstWhere('id',$j->id),'fo31'=>collect($form)->firstWhere('id',$j->id),'portfolio'=>collect($portfolio)->firstWhere('id',$j->id)];
        // Coordinators may act as the assigned faculty supervisor.
        $this->assertSame(200,$response->status(),json_encode($this->observations));
        $this->assertSame('approved',$j->fresh()->status);
        $this->assertSame($p['coordinator']->id, (int) $j->fresh()->faculty_reviewed_by);
        foreach (['student', 'fo31', 'portfolio'] as $view) {
            $this->assertSame('approved', $this->observations[$view]['status']);
        }
    }

    public function test_INT_08_approved_journal_to_fo31(): void
    {
        $p=$this->party(); $j=$this->approveJournal($p); $this->as($p['student']);
        $this->postJson('/api/v1/student/logbook',['date'=>'2026-09-09','end_date'=>'2026-09-12','activities_summary'=>'Overlap'])->assertUnprocessable();
        $form=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json();
        $this->assertSame($p['student']->student_number,$form['identity']['student_number']);
        $this->assertCount(1,$form['journals']);
        $row=$form['journals'][0];
        foreach(['id','week_number','activities_summary','status'] as $key) $this->assertEquals($j->$key,$row[$key]);
        $this->assertSame('2026-09-07',$row['date']); $this->assertSame('2026-09-11',$row['end_date']);
        $this->observations=['fo31'=>$row,'reviewer'=>$j->faculty_reviewed_by];
    }

    public function test_INT_09_portfolio_excludes_unapproved_journals(): void
    {
        $p=$this->party(); $approved=$this->approveJournal($p); $pending=$this->submitJournal($p,'2026-09-14','2026-09-18');
        $payload=$this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.journals');
        $this->observations=['journal_rows'=>$payload,'approved_id'=>$approved->id,'pending_id'=>$pending->id];
        $this->assertSame(1,collect($payload)->where('id',$approved->id)->count());
        $this->assertFalse(collect($payload)->contains('id',$pending->id),'Unapproved academic journal leaked into portfolio: '.json_encode($payload));
    }

    public function test_INT_10_upload_approval_all_compliance_views(): void
    {
        $p=$this->party(); [$t]=$this->templates($p,['Integration Letter']); $this->as($p['student']);
        $this->post('/api/v1/student/documents/upload',['document_type'=>$t->name,'files'=>[UploadedFile::fake()->create('letter.pdf',20,'application/pdf')]],['Accept'=>'application/json'])->assertCreated();
        $d=Document::where('internship_id',$p['internship']->id)->where('document_type',$t->name)->sole();
        $attachment=$d->attachments()->sole(); Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame('letter.pdf',$attachment->file_name);
        $before=$this->complianceViews($p);
        foreach($before as $v) $this->assertSame(0,(int)$v['approved']);
        $this->as($p['faculty']); $this->postJson('/api/v1/faculty/documents/'.$d->id.'/review',['action'=>'approve'])->assertOk();
        foreach($this->complianceViews($p) as $v) $this->assertEquals(['approved'=>1,'total'=>1,'pct'=>100],$v);
        $this->assertSame('approved',$d->fresh()->status);
    }

    public function test_INT_11_mixed_status_report_denominator(): void
    {
        $p=$this->party(); $templates=$this->templates($p,['Approved proof','Pending proof','Missing proof','Rejected proof']);
        foreach([0=>'approved',1=>'pending_review',3=>'rejected'] as $n=>$status) Document::create(['internship_id'=>$p['internship']->id,'requirement_template_id'=>$templates[$n]->id,'document_type'=>$templates[$n]->name,'status'=>$status,'submitted_at'=>now()]);
        foreach($this->complianceViews($p) as $v) $this->assertEquals(['approved'=>1,'total'=>4,'pct'=>25],$v);
        $sum=app(DocumentComplianceService::class)->summaryForStudent($p['student'],$p['internship']);
        $this->assertSame(1,$sum['pending']); $this->assertSame(1,$sum['rejected']); $this->assertFalse($sum['complete']);
        $this->observations['summary']=$sum;
    }

    public function test_INT_11_SCOPE_fo24_does_not_satisfy_host_evaluation(): void
    {
        $p=$this->party(); [$t]=$this->templates($p,['Host Evaluation']); $t->update(['system_code'=>'host_evaluation']);
        RequiredDocuments::clearCache(); $this->submitEvaluation($p);
        $views=$this->complianceViews($p);
        foreach($views as $role=>$v) $this->assertSame(0,(int)$v['approved'],$role.' incorrectly satisfied Host Evaluation; '.json_encode($views));
    }

    public function test_INT_12_COORD_coordinator_approval_access_propagation(): void
    {
        $p=$this->party(); $p['internship']->update(['supervisor_id'=>null]);
        $invite=SupervisorInviteToken::create(['internship_id'=>$p['internship']->id,'student_id'=>$p['student']->id,'supervisor_user_id'=>$p['supervisor']->id,'company_id'=>$p['company']->id,'token'=>'integration-private-token','expires_at'=>now()->addDay(),'status'=>'registered','first_name'=>'Industry','last_name'=>'Reviewer','email'=>$p['supervisor']->email]);
        $this->as($p['coordinator']);
        $response=$this->patchJson('/api/v1/faculty/supervisor-approvals/'.$invite->id.'/approve');
        $this->as($p['supervisor']);
        $list=$this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json();
        $this->as($p['student']); $dashboard=$this->getJson('/api/v1/student/dashboard')->assertOk()->json('internship');
        $this->observations=['http'=>$response->status(),'invite_status'=>$invite->fresh()->status,'assigned_supervisor'=>$p['internship']->fresh()->supervisor_id,'supervisor_list'=>$list,'student'=>$dashboard];
        // Department-scoped supervisor approvals are available to coordinators.
        $this->assertSame(200,$response->status(),json_encode($this->observations));
        $this->assertSame('approved', $invite->fresh()->status);
        $this->assertSame($p['supervisor']->id, (int) $p['internship']->fresh()->supervisor_id);
        $this->assertTrue(collect($list['data'])->contains('id', $p['internship']->id));
    }

    public function test_INT_13_validation_authorization_fo30_and_monitoring(): void
    {
        $p=$this->party(); $this->as($p['student']);
        $id=$this->postJson('/api/v1/student/attendance/clock-in')->assertCreated()->json('record.id');
        $this->at('2026-09-18 12:00'); $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $this->as($this->makeUser('supervisor'));
        $this->patchJson('/api/v1/supervisor/attendance/'.$id.'/validate',['action'=>'validated'])->assertForbidden();
        $this->assertSame('pending',AttendanceLog::findOrFail($id)->status);
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/supervisor/attendance/'.$id.'/validate',['action'=>'validated'])->assertOk();
        $this->getJson('/api/v1/supervisor/dtr/history')->assertOk();
        $this->as($p['student']);
        $form=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('fo30.logs');
        $row=collect($form)->firstWhere('id',$id); $this->assertNotNull($row);
        $this->assertTrue($row['validated']); $this->assertEquals(4,$row['hours_rendered']);
        $this->as($p['faculty']);
        $this->assertEquals(4,$this->getJson('/api/v1/faculty/students/'.$p['student']->id.'/progress')->assertOk()->json('progress.hours_rendered'));
        $this->observations=['fo30'=>$row];
    }

    public function test_INT_14_evaluation_submission_student_record(): void
    {
        $p=$this->party(); $e=$this->submitEvaluation($p);
        $this->assertSame($p['supervisor']->id,(int)$e->evaluated_by);
        $this->assertNotNull($e->submitted_at); $this->assertEquals(80,$e->average_score);
        $this->as($p['student']);
        $row=collect($this->getJson('/api/v1/student/evaluations')->assertOk()->json('data'))->firstWhere('id',$e->id);
        $this->assertNotNull($row); $this->assertSame('FO-24',$row['form_type']);
        $this->assertSame($p['internship']->id,$row['internship_id']);
        $this->assertEquals(80,$row['average_score']); $this->assertNotNull($row['submitted_at']);
        $this->observations=['evaluation'=>$row];
    }

    public function test_INT_15_pending_evaluation_not_completed(): void
    {
        $p=$this->party();
        $e=Evaluation::create(['internship_id'=>$p['internship']->id,'evaluated_by'=>$p['supervisor']->id,'evaluator_type'=>'supervisor','evaluation_period'=>'final','form_type'=>'FO-24','responses'=>['c1'=>80],'submitted_at'=>null]);
        $this->as($p['student']);
        $row=collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.evaluations'))->firstWhere('id',$e->id);
        $this->assertNotNull($row); $this->assertSame('pending',$row['status']); $this->assertNull($row['submitted_at']);
        $this->observations=['pending_portfolio_evaluation'=>$row];
    }

public function test_INT_03_existing_supervisor_approval_placement_and_access(): void
    {
        $p=$this->party(); $p['internship']->update(['supervisor_id'=>null]);
        $requirement=ProgramHteRequirement::create(['program_id'=>$p['student']->studentProfile->program_id,'sequence_order'=>1,'label'=>'Primary','required_hours'=>360]);
        $placement=InternshipPlacement::create(['internship_id'=>$p['internship']->id,'program_hte_requirement_id'=>$requirement->id,'company_id'=>$p['company']->id,'sequence_order'=>1,'label'=>'Primary','required_hours'=>360,'status'=>'active']);
        $p['internship']->update(['current_placement_id'=>$placement->id]);
        $count=User::where('role','supervisor')->count();
        $this->as($p['student']); $token=$this->postJson('/api/v1/student/supervisor-invite')->assertOk()->json('token');
        $this->as($p['supervisor']);
        $id=$this->postJson('/api/v1/supervisor/invites/bind',['token'=>$token])->assertOk()->json('invite.id');
        $this->post('/api/v1/supervisor/invites/'.$id.'/accept',['acceptance_forms'=>[UploadedFile::fake()->create('acceptance.pdf',20,'application/pdf')]],['Accept'=>'application/json'])->assertOk();
        $this->assertNull($p['internship']->fresh()->supervisor_id);
        $this->assertNull($placement->fresh()->supervisor_id);
        $before=$this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json('data');
        $this->assertFalse(collect($before)->contains('id',$p['internship']->id));
        $this->as($p['faculty']); $this->patchJson('/api/v1/faculty/supervisor-approvals/'.$id.'/approve')->assertOk();
        $this->assertSame($count,User::where('role','supervisor')->count());
        $this->assertSame($p['supervisor']->id,(int)$placement->fresh()->supervisor_id);
        $this->assertSame($p['supervisor']->id,(int)$p['internship']->fresh()->supervisor_id);
        $this->as($p['supervisor']);
        $assigned=$this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json('data');
        $this->assertNotNull(collect($assigned)->firstWhere('id',$p['internship']->id));
        $this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk();
        $this->as($this->makeUser('supervisor'));
        $this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertForbidden();
        $this->as($p['student']);
        $dash=$this->getJson('/api/v1/student/dashboard')->assertOk()->json('internship');
        $this->assertSame($p['supervisor']->faculty_number,$dash['supervisor_faculty_number']);
        $this->assertSame($p['company']->company_name,$dash['company_name']);
        $this->observations=['invite_status'=>SupervisorInviteToken::findOrFail($id)->status,'placement_supervisor'=>$placement->fresh()->supervisor_id,'internship_supervisor'=>$p['internship']->fresh()->supervisor_id,'student'=>$dash,'supervisor_account_count_before'=>$count,'supervisor_account_count_after_approval'=>$count];
    }

    public function test_INT_11_COMPLETE_approval_updates_complete_label(): void
    {
        $p=$this->party(); $templates=$this->templates($p,['First completion proof','Second completion proof']);
        foreach($templates as $t) {
            $this->as($p['student']);
            $this->post('/api/v1/student/documents/upload',['document_type'=>$t->name,'files'=>[UploadedFile::fake()->create('proof.pdf',20,'application/pdf')]],['Accept'=>'application/json'])->assertCreated();
            $d=Document::where('internship_id',$p['internship']->id)->where('document_type',$t->name)->sole();
            $this->as($p['faculty']);
            $this->postJson('/api/v1/faculty/documents/'.$d->id.'/review',['action'=>'approve'])->assertOk();
        }
        foreach($this->complianceViews($p) as $v) $this->assertEquals(['approved'=>2,'total'=>2,'pct'=>100],$v);
        foreach(['faculty','coordinator'] as $role) {
            $this->as($p[$role]);
            $this->getJson('/api/v1/'.$role.'/students/'.$p['student']->id.'/progress')->assertOk()->assertJsonPath('documents.complete',true)->assertJsonPath('documents.label','Complete');
        }
    }

public function test_INT_04_TZ_scheduled_afternoon_hours_propagate(): void
    {
        $p=$this->party(); $this->as($p['student']);
        $id=$this->postJson('/api/v1/student/attendance/schedules',['start_time'=>'08:00','end_time'=>'17:00'])->assertCreated()->json('schedule.id');
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/schedules/'.$id,['action'=>'approved'])->assertOk();
        $this->at('2026-09-18 13:00'); $this->as($p['student']);
        $logId=$this->postJson('/api/v1/student/attendance/clock-in')->assertCreated()->json('record.id');
        $this->at('2026-09-18 17:00');
        $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $this->as($p['supervisor']);
        $this->patchJson('/api/v1/supervisor/attendance/'.$logId.'/validate',['action'=>'validated'])->assertOk();
        $this->as($p['student']);
        $dashboard=$this->getJson('/api/v1/student/dashboard')->assertOk()->json('stats.hours_rendered');
        $form=$this->getJson('/api/v1/official-forms/'.$p['internship']->id)->assertOk()->json('fo30.logs');
        $row=collect($form)->firstWhere('id',$logId); $this->assertNotNull($row);
        $this->as($p['faculty']);
        $faculty=$this->getJson('/api/v1/faculty/students/'.$p['student']->id.'/progress')->assertOk()->json('progress.hours_rendered');
        $this->observations=['app_timezone'=>config('app.timezone'),'schedule'=>WorkSchedule::findOrFail($id)->only('start_time','end_time','effective_from','status'),
            'log'=>AttendanceLog::findOrFail($logId)->only('clock_in','clock_out','hours_rendered','status'),'dashboard_hours'=>$dashboard,'faculty_hours'=>$faculty,'fo30'=>$row];
        $this->assertSame('13:00',$row['pm_time_in']); $this->assertSame('17:00',$row['pm_time_out']);
        $this->assertEquals(4,$dashboard,json_encode($this->observations));
        $this->assertEquals(4,$row['hours_rendered']); $this->assertEquals(4,$faculty);
    }

    private function meetingData(array $p, string $title): array
    {
        return ['title'=>$title,'type'=>'check_in','starts_at'=>'2026-09-21 10:00','ends_at'=>'2026-09-21 11:00','internship_id'=>$p['internship']->id,'attendee_ids'=>[$p['student']->id]];
    }

    public function test_INT_17_authorized_meeting_student_view(): void
    {
        $p=$this->party(); $this->as($p['faculty']);
        $this->postJson('/api/v1/meetings',$this->meetingData($p,'Integration consultation'))->assertCreated();
        $m=Meeting::where('title','Integration consultation')->sole();
        $this->assertTrue($m->attendees()->where('user_id',$p['student']->id)->exists());
        $this->as($p['student']); $list=$this->getJson('/api/v1/meetings')->assertOk()->json('meetings');
        $this->assertNotNull(collect($list)->firstWhere('id',$m->id));
        $this->postJson('/api/v1/meetings',$this->meetingData($p,'Student forbidden'))->assertForbidden();
        $this->assertDatabaseMissing('meetings',['title'=>'Student forbidden']);
        $this->as($p['faculty']);
        $bad=$this->meetingData($p,'Reverse dates'); $bad['ends_at']='2026-09-21 09:00';
        $this->postJson('/api/v1/meetings',$bad)->assertUnprocessable();
        $this->assertDatabaseMissing('meetings',['title'=>'Reverse dates']);
        $this->observations=['meeting_id'=>$m->id,'student_visible'=>true];
    }

    public function test_INT_17_ATOMIC_denied_meeting_downstream_visibility(): void
    {
        $p=$this->party(); $outsider=$this->makeUser('coordinator'); $this->as($outsider);
        $before=Notification::count();
        $response=$this->postJson('/api/v1/meetings',$this->meetingData($p,'Denied persisted meeting'));
        $stored=Meeting::where('title','Denied persisted meeting')->get();
        $creator=$this->getJson('/api/v1/meetings')->assertOk()->json('meetings');
        $this->as($p['student']); $student=$this->getJson('/api/v1/meetings')->assertOk()->json('meetings');
        $this->observations=['http'=>$response->status(),'persisted_count'=>$stored->count(),'attendees'=>$stored->sum(fn($m)=>$m->attendees()->count()),'creator_visible'=>collect($creator)->contains('title','Denied persisted meeting'),'student_visible'=>collect($student)->contains('title','Denied persisted meeting'),'new_notifications'=>Notification::count()-$before];
        $this->assertSame(403,$response->status());
        $this->assertSame(0,$stored->count(),json_encode($this->observations));
    }

    public function test_INT_18_action_notification_preferences_and_read_ownership(): void
    {
        $p=$this->party(); $outsider=$this->makeUser('student');
        $p['student']->update(['notification_preferences'=>['emailReminders'=>false]]);
        $this->as($p['faculty']); $this->postJson('/api/v1/meetings',$this->meetingData($p,'Opted out meeting'))->assertCreated();
        $this->assertSame(0,Notification::where('user_id',$p['student']->id)->where('type','meeting_invite')->count());
        $p['student']->update(['notification_preferences'=>['emailReminders'=>true]]);
        $this->postJson('/api/v1/meetings',$this->meetingData($p,'Opted in meeting'))->assertCreated();
        $n=Notification::where('user_id',$p['student']->id)->where('type','meeting_invite')->sole();
        $this->assertStringContainsString('Opted in meeting',$n->message);
        $this->assertNull($n->read_at); $this->assertSame(0,Notification::where('user_id',$outsider->id)->count());
        $this->as($outsider); $this->postJson('/api/v1/notifications/'.$n->id.'/read')->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
        $this->as($p['student']); $this->getJson('/api/v1/notifications')->assertOk();
        $this->postJson('/api/v1/notifications/'.$n->id.'/read')->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
        $this->observations=['notification_id'=>$n->id,'recipient'=>$n->user_id,'type'=>$n->type,'read'=>true,'outsider_count'=>0,'opt_out_count'=>0];
    }

    public function test_INT_19_broad_portfolio_authoritative_sources(): void
    {
        $p=$this->party(); $j=$this->approveJournal($p); $e=$this->submitEvaluation($p);
        $a=AttendanceLog::create(['internship_id'=>$p['internship']->id,'date'=>'2026-09-17','clock_in'=>'00:00:00','clock_out'=>'04:00:00','hours_rendered'=>4,'status'=>'validated','validated_by'=>$p['supervisor']->id,'validated_at'=>now()]);
        $other=$this->makeStudentWithSection('4ITA');
        $otherI=$this->makeActiveInternship($other,$p['company'],$p['supervisor'],$p['faculty'],$p['coordinator']);
        JournalEntry::create(['internship_id'=>$otherI->id,'week_number'=>1,'entry_number'=>1,'date'=>'2026-09-01','end_date'=>'2026-09-05','status'=>'approved','activities_summary'=>'PRIVATE OTHER STUDENT']);
        $this->as($p['student']);
        $this->postJson('/api/v1/student/portfolio/builder',['company_background'=>'Integration company essay'])->assertOk();
        $this->post('/api/v1/student/portfolio/photos',['type'=>'company_logo','file'=>UploadedFile::fake()->image('integration-logo.png')],['Accept'=>'application/json'])->assertCreated();
        $payload=$this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame($p['student']->student_number,$payload['identity']['student_number']);
        $this->assertSame($p['company']->company_name,$payload['identity']['company_name']);
        $this->assertSame('Integration company essay',$payload['internship']['portfolio']['company_background']);
        $this->assertSame(1,collect($payload['internship']['journals'])->where('id',$j->id)->count());
        $this->assertFalse(collect($payload['internship']['journals'])->contains('activities_summary','PRIVATE OTHER STUDENT'));
        $this->assertEquals(4,collect($payload['internship']['attendance'])->firstWhere('id',$a->id)['hours_rendered']);
        $this->assertSame('completed',collect($payload['internship']['evaluations'])->firstWhere('id',$e->id)['status']);
        $this->assertFalse(collect($payload['internship']['evaluations'])->contains('form_type','faculty_eval'));
        $this->assertSame(1,collect($payload['internship']['portfolio']['photos'])->where('type','company_logo')->count());
        $this->observations=['identity'=>$payload['identity'],'journal_ids'=>collect($payload['internship']['journals'])->pluck('id'),'evaluation_ids'=>collect($payload['internship']['evaluations'])->pluck('id'),'attendance_id'=>$a->id,'logo_count'=>1];
    }

    public function test_INT_20_reports_match_authoritative_sources(): void
    {
        $p=$this->party(); $this->approveJournal($p); $e=$this->submitEvaluation($p);
        [$t]=$this->templates($p,['Report proof']);
        Document::create(['internship_id'=>$p['internship']->id,'document_type'=>$t->name,'requirement_template_id'=>$t->id,'status'=>'approved']);
        AttendanceLog::create(['internship_id'=>$p['internship']->id,'date'=>'2026-09-17','clock_in'=>'00:00:00','clock_out'=>'08:00:00','hours_rendered'=>8,'status'=>'validated','validated_by'=>$p['supervisor']->id]);
        foreach(['faculty','coordinator'] as $role) {
            $this->as($p[$role]);
            $row=collect($this->getJson('/api/v1/'.$role.'/reports/student-summary')->assertOk()->json('students'))->firstWhere('student_number',$p['student']->student_number);
            $this->assertNotNull($row);
            $this->assertSame($p['company']->company_name,$row['company']);
            $this->assertSame(1,(int)$row['approved_journals']); $this->assertSame(1,(int)$row['approved_docs']);
            $performance=$this->getJson('/api/v1/'.$role.'/reports/performance')->assertOk()->json();
            $this->observations[$role]=['summary'=>$row,'performance'=>$performance];
        }
        $faculty=$this->observations['faculty']['performance'];
        $eval=collect($faculty['eval_averages'])->firstWhere('evaluator_type','supervisor'); $this->assertNotNull($eval);
        $this->assertEqualsWithDelta((float)$e->average_score,(float)$eval['avg_overall'],0.00001);
        $violations=[];
        foreach(['faculty','coordinator'] as $role) {
            if ((float)$this->observations[$role]['summary']['hours_rendered']!==8.0) $violations[]=$role.' summary hours';
            if ((float)$this->observations[$role]['performance']['by_program'][0]['avg_hours']!==8.0) $violations[]=$role.' performance hours';
        }
        $this->assertSame([],$violations,'Reports disagree with validated attendance: '.json_encode($this->observations));
    }

    public function test_INT_21_admin_account_change_login_and_audit(): void
    {
        $admin=$this->makeUser('admin'); $staff=$this->makeUser('faculty'); $student=$this->makeStudentWithSection();
        $this->as($student); $this->putJson('/api/v1/admin/staff/'.$staff->id,['is_active'=>false])->assertForbidden();
        $this->assertTrue($staff->fresh()->is_active);
        $this->as($admin); $this->putJson('/api/v1/admin/staff/'.$staff->id,['is_active'=>false])->assertOk();
        $this->assertFalse($staff->fresh()->is_active);
        $audit=AuditLog::where('action','staff.deactivated')->where('user_id',$admin->id)->sole();
        $metadata=json_encode($audit->toArray()); $this->assertStringNotContainsString($staff->password,$metadata);
        $this->assertStringNotContainsString('"password"',$metadata); $this->assertStringNotContainsString('"token"',$metadata);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login',['username'=>$staff->username,'password'=>'password'])->assertUnprocessable();
        $this->as($admin); $this->putJson('/api/v1/admin/staff/'.$staff->id,['is_active'=>true])->assertOk();
        $this->app['auth']->forgetGuards();
        $login=$this->postJson('/api/v1/auth/login',['username'=>$staff->username,'password'=>'password'])->assertOk();
        $this->assertSame('faculty',$login->json('user.role'));
        $this->app['auth']->forgetGuards(); $this->withToken($login->json('token'));
        $this->getJson('/api/v1/faculty/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/audit-log')->assertForbidden();
        $this->observations=['staff_id'=>$staff->id,'deactivation_audit_id'=>$audit->id,'reactivated'=>$staff->fresh()->is_active,'login_role'=>$login->json('user.role')];
    }
}
