<?php

use App\Support\InternshipProvisioning;
use Illuminate\Database\Migrations\Migration;

/**
 * Duplicate open internships (same student, several current rows) were present
 * again after the 2026-09-08 repair, which made one student appear several times
 * on internship-based lists. Re-run the idempotent repair: the most complete row
 * is kept and the rest are marked cancelled (never deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        InternshipProvisioning::supersedeDuplicateOpenInternships();
    }

    public function down(): void
    {
        // Data repair only; cancelled duplicates are intentionally not reopened.
    }
};
