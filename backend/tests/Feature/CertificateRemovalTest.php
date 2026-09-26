<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DocumentComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CERT-REMOVE-01..06. The system-generated OJT completion certificate is out
 * of scope and fully removed (UI, endpoints, generation, schema). The HTE's
 * "Certificate of Completion" — an uploaded compliance document — is a
 * different thing and must remain.
 */
class CertificateRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function frontendSources(): array
    {
        $root = dirname(__DIR__, 3).'/frontend/src';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['jsx', 'js'], true)) {
                $files[$file->getPathname()] = file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    /** CERT-REMOVE-01 / 04: no Certificate card, action, status or warning anywhere in the UI. */
    public function test_frontend_has_no_system_certificate_feature(): void
    {
        $patterns = [
            '/certificate\s+not\s+(yet\s+)?available/i',
            '/download\s+(completion\s+)?certificate/i',
            '/(preview|generate)\s+certificate/i',
            '/certificates?\/completion|certificate\/eligibility|internships\/\$\{[^}]+\}\/certificate/i',
            '/completion-certificate/i',
            '/downloadCertificate|certData|certLoading/',
        ];
        $offenders = [];
        foreach ($this->frontendSources() as $path => $src) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $src)) {
                    $offenders[] = basename($path).' ~ '.$pattern;
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    /** CERT-REMOVE-02: no certificate endpoint is registered, and the old URLs are not found. */
    public function test_no_certificate_endpoint_exists(): void
    {
        $certificateRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains(strtolower($r->uri()), 'certificate'))
            ->map(fn ($r) => $r->uri())->values()->all();
        $this->assertSame([], $certificateRoutes);

        $this->assertFalse(class_exists(\App\Http\Controllers\Api\CertificateController::class));
        $this->assertFalse(class_exists(\App\Services\CertificateEligibilityService::class));

        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
        Sanctum::actingAs(User::findOrFail(StudentProfile::where('student_number', '2300592')->value('user_id')));
        $this->getJson('/api/v1/student/certificate/eligibility')->assertNotFound();
        $this->getJson('/api/v1/student/certificates/completion')->assertNotFound();
    }

    /** CERT-REMOVE-03 / 05: completing an OJT generates nothing certificate-related; completion still works. */
    public function test_completion_generates_no_certificate_and_completion_features_remain(): void
    {
        $this->assertFalse(Schema::hasColumn('internships', 'certificate_eligible'));
        $this->assertFalse(Schema::hasColumn('internships', 'certificate_issued_at'));

        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');

        $internship = Internship::where('student_id', StudentProfile::where('student_number', '2300592')->value('user_id'))->firstOrFail();
        $this->assertSame('completed', $internship->status);
        $this->assertArrayNotHasKey('certificate_eligible', $internship->getAttributes());
        $this->assertSame(0, Document::where('internship_id', $internship->id)
            ->where(fn ($q) => $q->where('document_type', 'like', '%OJT Completion Certificate%')
                ->orWhere('document_type', 'like', '%completion-certificate%'))
            ->count(), 'No system-generated certificate document is created');

        // Other completion functionality still works.
        Sanctum::actingAs(User::findOrFail($internship->student_id));
        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertSame('completed', $dashboard->json('internship.status'));
        $this->assertSame('absorbed', $internship->absorption_status);
        $this->assertStringNotContainsString('certificate', strtolower(json_encode($dashboard->json('internship'))));
    }

    /** CERT-REMOVE-06: the HTE-issued "Certificate of Completion" upload requirement is preserved. */
    public function test_official_certificate_of_completion_requirement_is_preserved(): void
    {
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');

        $codes = collect(DocumentComplianceService::systemCodeCatalog())->pluck('code')->all();
        $this->assertContains('certificate_of_completion', $codes);
        $this->assertContains('Certificate of Completion', \App\Support\RequiredDocuments::canonicalTypeNames());

        $templates = app(DocumentComplianceService::class)->applicableTemplatesForStudent(
            User::findOrFail(StudentProfile::where('student_number', '2300592')->value('user_id'))
        );
        $this->assertTrue($templates->contains(fn ($t) => stripos($t->name, 'Certificate of Completion') !== false
            || ($t->system_code ?? null) === 'certificate_of_completion'));
    }
}
