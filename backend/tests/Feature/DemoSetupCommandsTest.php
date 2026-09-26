<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Setup commands used after restoring the controlled demonstration snapshot.
 */
class DemoSetupCommandsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_demo_files_restore_to_the_target_and_keep_existing_files(): void
    {
        $target = storage_path('framework/testing/demo-files-'.uniqid());
        try {
            $this->artisan('interntrack:restore-demo-files', ['--target' => $target])->assertSuccessful();

            $expected = count(File::allFiles(database_path('demo-files/private')));
            $this->assertGreaterThan(0, $expected);
            $this->assertCount($expected, File::allFiles($target));
            $this->assertFileExists($target.'/signatures/97_processed.png');

            // Existing files are kept unless --force is given.
            File::put($target.'/signatures/97_processed.png', 'local');
            $this->artisan('interntrack:restore-demo-files', ['--target' => $target])->assertSuccessful();
            $this->assertSame('local', File::get($target.'/signatures/97_processed.png'));

            $this->artisan('interntrack:restore-demo-files', ['--target' => $target, '--force' => true])->assertSuccessful();
            $this->assertNotSame('local', File::get($target.'/signatures/97_processed.png'));
        } finally {
            File::deleteDirectory($target);
        }
    }

    public function test_demo_passwords_are_set_locally_and_short_passwords_are_refused(): void
    {
        $student = $this->makeUser('student');
        $supervisor = $this->makeUser('supervisor');
        $supervisor->forceFill(['failed_login_attempts' => 5, 'locked_at' => now()])->save();

        $this->artisan('interntrack:set-demo-passwords', ['--password' => 'short'])->assertFailed();
        $this->assertFalse(Hash::check('short', $student->fresh()->password));

        $this->artisan('interntrack:set-demo-passwords', ['--password' => 'LocalDemo2026'])->assertSuccessful();
        foreach ([$student, $supervisor] as $user) {
            $fresh = User::find($user->id);
            $this->assertTrue(Hash::check('LocalDemo2026', $fresh->password));
        }
        $this->assertNull($supervisor->fresh()->locked_at);
    }

    public function test_demo_passwords_refuse_to_run_in_production(): void
    {
        $user = $this->makeUser('student');
        $this->app['env'] = 'production';
        try {
            $this->artisan('interntrack:set-demo-passwords', ['--password' => 'LocalDemo2026'])->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertFalse(Hash::check('LocalDemo2026', $user->fresh()->password));
    }
}
