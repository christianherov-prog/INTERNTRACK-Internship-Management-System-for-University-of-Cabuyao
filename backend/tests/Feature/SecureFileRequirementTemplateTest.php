<?php

namespace Tests\Feature;

use App\Models\OjtRequirementTemplate;
use App\Models\RequirementTarget;
use App\Models\RequirementTemplateAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecureFileRequirementTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_student_can_download_requirement_template_file(): void
    {
        Storage::fake('local');
        $path = 'requirement_templates/sample.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        $student = User::factory()->role('student')->create();
        $other = User::factory()->role('student')->create();
        $faculty = User::factory()->role('faculty')->create();

        $template = OjtRequirementTemplate::create([
            'name' => 'Application Letter',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 1,
        ]);
        RequirementTemplateAttachment::create([
            'requirement_template_id' => $template->id,
            'file_path' => $path,
            'file_name' => 'sample.pdf',
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $template->id,
            'target_type' => 'student',
            'target_id' => (string) $student->id,
        ]);

        Sanctum::actingAs($student);
        $this->get('/api/v1/files/download?path='.urlencode($path))
            ->assertOk();

        Sanctum::actingAs($other);
        $this->get('/api/v1/files/download?path='.urlencode($path))
            ->assertForbidden();
    }
}
