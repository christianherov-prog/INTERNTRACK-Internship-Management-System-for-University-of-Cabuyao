<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data repair: remove the manual-test "Clarence Magtibay" supervisor
 * requests from Faculty/Supervisor Review History (the rows with remarks
 * such as "Where is the acceptance form?", "No", "NOOOOO!!!" and the
 * CAPSTONE_PROGRESS_REPORT.pdf / Progress Report.pdf attachments filed
 * under Student 2300600). Matched by their exact identity, never by id, so
 * the repair is idempotent and a no-op on databases that never had them.
 *
 * Only these review-history rows (and their attachment files, if any) are
 * removed. Legitimate supervisor requests, the Review History feature, and
 * the immutable audit_logs trail are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $studentId = DB::table('student_profiles')->where('student_number', '2300600')->value('user_id');
        if (! $studentId) {
            return;
        }

        $rows = DB::table('supervisor_invite_tokens')
            ->where('student_id', $studentId)
            ->where('first_name', 'Clarence')
            ->where('last_name', 'Magtibay')
            ->whereIn('email', ['pogi@gmail.com', 'magtibay@gmail.com'])
            ->get(['id', 'fo29_file_path', 'acceptance_form_paths']);

        foreach ($rows as $row) {
            $paths = array_filter([$row->fo29_file_path]);
            foreach (json_decode((string) $row->acceptance_form_paths, true) ?: [] as $form) {
                if (! empty($form['path'])) {
                    $paths[] = $form['path'];
                }
            }

            foreach (array_unique($paths) as $path) {
                // Keep a file that any other record still points at.
                $stillUsed = DB::table('supervisor_invite_tokens')
                    ->where('id', '!=', $row->id)
                    ->where(fn ($q) => $q->where('fo29_file_path', $path)
                        ->orWhere('acceptance_form_paths', 'like', '%'.str_replace('/', '\\\\/', $path).'%'))
                    ->exists();
                if (! $stillUsed && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }

            DB::table('notifications')->where(fn ($q) => $q
                ->where('data', 'like', '%"invite_id":'.$row->id.',%')
                ->orWhere('data', 'like', '%"invite_id":'.$row->id.'}%'))
                ->delete();
            DB::table('supervisor_invite_tokens')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        // Sample data is intentionally not restored.
    }
};
