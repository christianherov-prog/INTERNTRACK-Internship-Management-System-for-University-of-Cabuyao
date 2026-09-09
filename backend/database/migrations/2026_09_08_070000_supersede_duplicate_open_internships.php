<?php

use App\Support\InternshipProvisioning;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internships')) {
            return;
        }

        InternshipProvisioning::supersedeDuplicateOpenInternships();
    }

    public function down(): void
    {
        // Data repair is not reversible without restoring cancelled duplicate rows.
    }
};
