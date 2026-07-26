<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sex (Male/Female only) on all profile tables.
 * iEnroll-sourced for university roles; supervisor is manual.
 */
return new class extends Migration {
    public function up(): void
    {
        // Clean invalid student sex values before tightening enum
        if (Schema::hasColumn('student_profiles', 'sex')) {
            DB::table('student_profiles')
                ->whereNotNull('sex')
                ->whereNotIn('sex', ['Male', 'Female'])
                ->update(['sex' => null]);

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE student_profiles MODIFY sex ENUM('Male','Female') NULL");
            }
        }

        if (!Schema::hasColumn('faculty_profiles', 'sex')) {
            Schema::table('faculty_profiles', function (Blueprint $table) {
                $table->enum('sex', ['Male', 'Female'])->nullable()->after('contact_number');
            });
        }

        if (!Schema::hasColumn('supervisor_profiles', 'sex')) {
            Schema::table('supervisor_profiles', function (Blueprint $table) {
                $table->enum('sex', ['Male', 'Female'])->nullable()->after('contact_number');
            });
        }

        if (!Schema::hasColumn('users', 'sex')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('sex', ['Male', 'Female'])->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sex')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sex');
            });
        }

        if (Schema::hasColumn('supervisor_profiles', 'sex')) {
            Schema::table('supervisor_profiles', function (Blueprint $table) {
                $table->dropColumn('sex');
            });
        }

        if (Schema::hasColumn('faculty_profiles', 'sex')) {
            Schema::table('faculty_profiles', function (Blueprint $table) {
                $table->dropColumn('sex');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasColumn('student_profiles', 'sex')) {
            DB::statement("ALTER TABLE student_profiles MODIFY sex ENUM('Male','Female','Other') NULL");
        }
    }
};
