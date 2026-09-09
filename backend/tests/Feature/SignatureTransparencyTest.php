<?php

namespace Tests\Feature;

use App\Support\SignatureCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SignatureTransparencyTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_dark_canvas_signature_becomes_transparent_png(): void
    {
        $png = $this->pngWithBackground(0, 0, 0, 255, 255, 255);
        $out = SignatureCapture::transparentPngFromBinary($png);
        $this->assertCornerTransparent($out);
        $this->assertNotSame($png, $out);
    }

    public function test_white_paper_signature_becomes_transparent_png(): void
    {
        $png = $this->pngWithBackground(255, 255, 255, 20, 20, 20);
        $out = SignatureCapture::transparentPngFromBinary($png);
        $this->assertCornerTransparent($out);
    }

    public function test_signature_upload_stores_transparent_processed_png(): void
    {
        Storage::fake('local');
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $tmp = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($tmp, $this->pngWithBackground(0, 0, 0, 255, 255, 255));
        $file = new UploadedFile($tmp, 'sig.png', 'image/png', null, true);

        $this->post('/api/v1/auth/signature', [
            'signature' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_signature', true);

        $path = "signatures/{$student->id}_processed.png";
        Storage::disk('local')->assertExists($path);
        $this->assertCornerTransparent(Storage::disk('local')->get($path));
    }

    public function test_ccs_portfolio_builder_does_not_render_other_required_appendices(): void
    {
        $builder = file_get_contents(base_path('../frontend/src/pages/student/portfolio/CCSPortfolioBuilder.jsx'));
        $this->assertIsString($builder);
        $this->assertStringNotContainsString('Other Required Appendices', $builder);
        $this->assertStringNotContainsString('optional scanned copy', $builder);
        $this->assertStringContainsString('Evaluations Status', $builder);
    }

    private function pngWithBackground(int $bgR, int $bgG, int $bgB, int $inkR, int $inkG, int $inkB): string
    {
        $im = imagecreatetruecolor(48, 24);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $bg = imagecolorallocate($im, $bgR, $bgG, $bgB);
        $ink = imagecolorallocate($im, $inkR, $inkG, $inkB);
        imagefilledrectangle($im, 0, 0, 47, 23, $bg);
        imageline($im, 4, 12, 44, 12, $ink);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    private function assertCornerTransparent(string $png): void
    {
        $im = imagecreatefromstring($png);
        $this->assertNotFalse($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $rgba = imagecolorat($im, 0, 0);
        $alpha = ($rgba & 0x7F000000) >> 24;
        imagedestroy($im);
        $this->assertGreaterThanOrEqual(100, $alpha, 'Signature corner should be transparent.');
    }
}
