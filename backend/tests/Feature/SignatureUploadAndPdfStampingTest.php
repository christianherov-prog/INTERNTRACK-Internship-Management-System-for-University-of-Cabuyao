<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SignatureUploadAndPdfStampingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private string $logoPath;
    private bool $createdLogo = false;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->ensurePdfLogoExists();
    }

    protected function tearDown(): void
    {
        if ($this->createdLogo && is_file($this->logoPath)) {
            @unlink($this->logoPath);
            @rmdir(dirname($this->logoPath));
        }
        parent::tearDown();
    }

    public function test_signature_upload_accepts_png_and_jpg_and_rejects_invalid_or_oversized_files(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => UploadedFile::fake()->create('signature.pdf', 10, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature')
            ->assertJsonFragment(['Signature must be a PNG or JPG image.']);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => UploadedFile::fake()->image('signature.png')->size(5121),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature')
            ->assertJsonFragment(['Signature image must be 5MB or smaller.']);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => $this->signatureImage('signature.png', 'png'),
        ])
            ->assertOk()
            ->assertJsonPath('has_signature', true)
            ->assertJsonPath('signature_path', "signatures/{$student->id}_processed.png");

        Storage::disk('local')->assertExists("signatures/{$student->id}_processed.png");

        $this->postJson('/api/v1/auth/signature', [
            'signature' => $this->signatureImage('signature.jpg', 'jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('has_signature', true)
            ->assertJsonPath('signature_path', "signatures/{$student->id}_processed.png");
    }

    public function test_upload_removes_white_background_and_reupload_replaces_stored_signature(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => $this->signatureImage('first.png', 'png', [0, 0, 0]),
        ])->assertOk();

        $path = "signatures/{$student->id}_processed.png";
        $first = Storage::disk('local')->get($path);
        $this->assertTransparentBackgroundWithOpaqueInk($first);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => $this->signatureImage('second.jpg', 'jpg', [180, 20, 20]),
        ])->assertOk();

        $second = Storage::disk('local')->get($path);
        $this->assertNotSame(md5($first), md5($second), 'Re-upload should overwrite the processed signature file.');
        $this->assertTransparentBackgroundWithOpaqueInk($second);
    }

    public function test_processed_signature_is_used_by_form30_and_form31_pdf_generation(): void
    {
        $party = $this->setupPartyWithEntries();
        $student = $party['student'];
        $internship = $party['internship'];
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/auth/signature', [
            'signature' => $this->signatureImage('signature.png', 'png'),
        ])->assertOk();

        $dtr = $this->get("/api/v1/student/dtr/generate?internship_id={$internship->id}&month=2026-07")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $journal = $this->get("/api/v1/student/journal/generate?internship_id={$internship->id}&week_number=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // DomPDF download() returns a regular response (not StreamedResponse).
        $this->assertStringStartsWith('%PDF', (string) $dtr->getContent());
        $this->assertStringStartsWith('%PDF', (string) $journal->getContent());
        Storage::disk('local')->assertExists("signatures/{$student->id}_processed.png");
    }

    private function setupPartyWithEntries(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        AttendanceLog::create([
            'internship_id' => $internship->id,
            'date' => '2026-07-06',
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
        ]);

        JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 1,
            'week_number' => 1,
            'date' => '2026-07-10',
            'activities_summary' => 'Completed assigned internship tasks.',
            'challenges' => 'Adjusted to the team workflow.',
            'learnings' => 'Learned standard reporting practices.',
            'status' => 'approved',
        ]);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    private function signatureImage(string $name, string $format, array $inkRgb = [0, 0, 0]): UploadedFile
    {
        $image = imagecreatetruecolor(220, 90);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        $ink = imagecolorallocate($image, $inkRgb[0], $inkRgb[1], $inkRgb[2]);
        imagesetthickness($image, 5);
        imageline($image, 25, 55, 90, 25, $ink);
        imageline($image, 90, 25, 160, 62, $ink);
        imageline($image, 35, 67, 185, 67, $ink);

        $tmp = tempnam(sys_get_temp_dir(), 'signature_');
        if ($format === 'jpg' || $format === 'jpeg') {
            imagejpeg($image, $tmp, 95);
            $mime = 'image/jpeg';
        } else {
            imagepng($image, $tmp);
            $mime = 'image/png';
        }
        imagedestroy($image);

        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    private function assertTransparentBackgroundWithOpaqueInk(string $pngData): void
    {
        $image = imagecreatefromstring($pngData);
        $this->assertNotFalse($image);

        $background = imagecolorsforindex($image, imagecolorat($image, 2, 2));
        $ink = imagecolorsforindex($image, imagecolorat($image, 90, 25));

        imagedestroy($image);

        $this->assertGreaterThanOrEqual(120, $background['alpha'], 'White paper background should be transparent.');
        $this->assertLessThanOrEqual(10, $ink['alpha'], 'Signature ink should remain opaque.');
    }

    private function ensurePdfLogoExists(): void
    {
        $this->logoPath = public_path('images/pnc_logo.png');
        if (is_file($this->logoPath)) {
            return;
        }

        if (!is_dir(dirname($this->logoPath))) {
            mkdir(dirname($this->logoPath), 0777, true);
        }

        $image = imagecreatetruecolor(24, 24);
        $green = imagecolorallocate($image, 26, 122, 63);
        imagefill($image, 0, 0, $green);
        imagepng($image, $this->logoPath);
        imagedestroy($image);
        $this->createdLogo = true;
    }
}
