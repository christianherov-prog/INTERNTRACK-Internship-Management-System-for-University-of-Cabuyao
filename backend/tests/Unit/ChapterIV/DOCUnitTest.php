<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class DOCUnitTest extends IsolatedTestCase
{
    public function test_UT_DOC_05_U(): void
    {
        Schema::shouldReceive('hasColumn')->with('documents','requirement_template_id')->andReturn(true); $t=new \App\Models\OjtRequirementTemplate(['name'=>'Custom evidence']); $t->id=3; $d=new \App\Models\Document(['document_type'=>'Custom evidence','status'=>'approved','requirement_template_id'=>3]); $d->id=4; $r=$this->invoke(new \App\Services\DocumentComplianceService,'resolveTemplateState',$t,null,collect([$d])); $this->assertSame('approved',$r['status']); $this->assertSame(4,$r['document_id']);
    }

    public function test_UT_DOC_07_U(): void
    {
        Schema::shouldReceive('hasColumn')->with('documents','requirement_template_id')->andReturn(true); $t=new \App\Models\OjtRequirementTemplate(['name'=>'Custom evidence']); $t->id=3; $d=new \App\Models\Document(['document_type'=>'Custom evidence','status'=>'pending_faculty','requirement_template_id'=>3]); $d->id=4; $r=$this->invoke(new \App\Services\DocumentComplianceService,'resolveTemplateState',$t,null,collect([$d])); $this->assertSame('pending',$r['status']); $this->assertSame(4,$r['document_id']);
    }

    public function test_UT_DOC_08_U(): void
    {
        Schema::shouldReceive('hasColumn')->with('documents','requirement_template_id')->andReturn(true); $t=new \App\Models\OjtRequirementTemplate(['name'=>'Custom evidence']); $t->id=3; $d=new \App\Models\Document(['document_type'=>'Custom evidence','status'=>'rejected','requirement_template_id'=>3]); $d->id=4; $r=$this->invoke(new \App\Services\DocumentComplianceService,'resolveTemplateState',$t,null,collect([$d])); $this->assertSame('rejected',$r['status']); $this->assertSame(4,$r['document_id']);
    }

    public function test_UT_DOC_06_U(): void
    {
        $r=$this->invoke(new \App\Services\DocumentComplianceService,'resolveTemplateState',new \App\Models\OjtRequirementTemplate(['name'=>'Custom']),null,collect()); $this->assertSame('missing',$r['status']); $this->assertNull($r['document_id']);
    }

    public function test_UT_DOC_APPROVAL_PRIORITY(): void
    {
        Schema::shouldReceive('hasColumn')->andReturn(true); $t=new \App\Models\OjtRequirementTemplate(['name'=>'Custom']); $t->id=3; $a=new \App\Models\Document(['document_type'=>'Custom','status'=>'approved']); $a->id=4; $r=new \App\Models\Document(['document_type'=>'Custom','status'=>'rejected']); $r->id=5; $s=$this->invoke(new \App\Services\DocumentComplianceService,'resolveTemplateState',$t,null,collect([$r,$a])); $this->assertSame('approved',$s['status']); $this->assertSame(4,$s['document_id']);
    }
}

