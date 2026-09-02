<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Merge duplicate Faculty accounts into their matching Coordinator accounts.
 *
 * At the University of Cabuyao, one person can hold both Coordinator and Faculty
 * Supervisor roles simultaneously. This migration finds any faculty/coordinator pairs
 * that share the same first_name + last_name in faculty_profiles, re-parents all
 * data from the retiring faculty account to the surviving coordinator account, and
 * deactivates the retiring account.
 *
 * If no real pairs exist (e.g. only demo/seeded accounts which have different names
 * by design), this migration exits cleanly with no changes.
 *
 * To add a real pair manually, add it to the $manualPairs array below with the
 * exact user IDs confirmed from the database.
 */
return new class extends Migration
{
    /**
     * Optional manual override: add confirmed (coordinator_id, faculty_id) pairs here
     * if the name-matching query doesn't catch them (e.g. names differ by a typo).
     *
     * Example:
     *   [['coordinator_id' => 5, 'faculty_id' => 12]],
     */
    private array $manualPairs = [];

    public function up(): void
    {
        // ── Auto-detect pairs by matching first + last name ──────────────────
        $detectedPairs = DB::select("
            SELECT c.id AS coordinator_id, f.id AS faculty_id
            FROM users c
            JOIN faculty_profiles fp2 ON c.id = fp2.user_id
            JOIN faculty_profiles fp1 ON (
                LOWER(fp2.first_name) = LOWER(fp1.first_name)
                AND LOWER(fp2.last_name) = LOWER(fp1.last_name)
            )
            JOIN users f ON f.id = fp1.user_id
            WHERE c.role = 'coordinator'
              AND f.role = 'faculty'
              AND c.id != f.id
              AND c.deleted_at IS NULL
              AND f.deleted_at IS NULL
        ");

        $pairs = array_merge(
            array_map(fn ($p) => ['coordinator_id' => $p->coordinator_id, 'faculty_id' => $p->faculty_id], $detectedPairs),
            $this->manualPairs
        );

        if (empty($pairs)) {
            Log::info('merge_duplicate_faculty_coordinator: No pairs detected, nothing to do.');
            return;
        }

        foreach ($pairs as $pair) {
            $coordinatorId = $pair['coordinator_id'];
            $facultyId     = $pair['faculty_id'];

            Log::info("merge_duplicate_faculty_coordinator: Merging faculty #{$facultyId} -> coordinator #{$coordinatorId}");

            DB::transaction(function () use ($coordinatorId, $facultyId) {
                // 1. Re-parent internships.faculty_id
                DB::table('internships')
                    ->where('faculty_id', $facultyId)
                    ->update(['faculty_id' => $coordinatorId]);

                // 2. Re-parent faculty_section_assignments
                DB::table('faculty_section_assignments')
                    ->where('faculty_user_id', $facultyId)
                    ->update(['faculty_user_id' => $coordinatorId]);

                // 3. Re-parent evaluations (if evaluator_user_id column exists)
                if (DB::getSchemaBuilder()->hasColumn('evaluations', 'evaluator_user_id')) {
                    DB::table('evaluations')
                        ->where('evaluator_user_id', $facultyId)
                        ->update(['evaluator_user_id' => $coordinatorId]);
                }

                // 4. Re-parent document_reviews (if reviewer_id column exists)
                if (DB::getSchemaBuilder()->hasColumn('document_reviews', 'reviewer_id')) {
                    DB::table('document_reviews')
                        ->where('reviewer_id', $facultyId)
                        ->update(['reviewer_id' => $coordinatorId]);
                }

                // 5. Re-parent journal_entries faculty review
                if (DB::getSchemaBuilder()->hasColumn('journal_entries', 'faculty_reviewed_by')) {
                    DB::table('journal_entries')
                        ->where('faculty_reviewed_by', $facultyId)
                        ->update(['faculty_reviewed_by' => $coordinatorId]);
                }

                // 6. Re-parent messages
                DB::table('messages')
                    ->where('sender_id', $facultyId)
                    ->update(['sender_id' => $coordinatorId]);

                // 7. Re-parent conversation_participants (skip if coordinator already in that conversation)
                $existingConversations = DB::table('conversation_participants')
                    ->where('user_id', $coordinatorId)
                    ->pluck('conversation_id');

                DB::table('conversation_participants')
                    ->where('user_id', $facultyId)
                    ->whereNotIn('conversation_id', $existingConversations)
                    ->update(['user_id' => $coordinatorId]);

                // Delete duplicates (faculty was already in conversations where coordinator is)
                DB::table('conversation_participants')
                    ->where('user_id', $facultyId)
                    ->delete();

                // 8. Re-parent notifications
                DB::table('notifications')
                    ->where('user_id', $facultyId)
                    ->update(['user_id' => $coordinatorId]);

                // 9. Revoke all tokens for the retiring faculty account
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $facultyId)
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->delete();

                // 10. Deactivate the retiring faculty account
                DB::table('users')
                    ->where('id', $facultyId)
                    ->update(['is_active' => false]);
            });

            Log::info("merge_duplicate_faculty_coordinator: Done. Faculty #{$facultyId} is now deactivated.");
        }
    }

    public function down(): void
    {
        // This migration cannot be automatically reversed because data re-parenting
        // cannot be undone without knowing the original state. To roll back, restore
        // from a database backup taken before this migration ran.
        Log::warning('merge_duplicate_faculty_coordinator: down() called — this migration is not reversible. Restore from backup if needed.');
    }
};
