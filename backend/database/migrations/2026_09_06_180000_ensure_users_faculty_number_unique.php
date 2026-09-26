<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasUnique = collect(Schema::getIndexes('users'))->contains(
            fn (array $index) => in_array('faculty_number', $index['columns'] ?? [], true)
                && ($index['unique'] ?? false)
        );

        if (! $hasUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('faculty_number', 'users_faculty_number_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep the unique index; dropping it would reintroduce duplicate supervisor IDs.
    }
};
