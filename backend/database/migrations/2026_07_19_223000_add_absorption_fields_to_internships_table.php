<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->enum('absorption_status', ['pending', 'absorbed', 'not_hired'])
                ->nullable()
                ->after('status_reason');
            $table->date('absorbed_at')->nullable()->after('absorption_status');
            $table->string('job_title')->nullable()->after('absorbed_at');
            $table->text('absorption_notes')->nullable()->after('job_title');
            $table->foreignId('absorption_recorded_by')->nullable()->after('absorption_notes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('absorption_recorded_at')->nullable()->after('absorption_recorded_by');
            $table->string('absorption_recorded_by_role', 30)->nullable()->after('absorption_recorded_at');

            $table->boolean('student_declared_hired')->default(false)->after('absorption_recorded_by_role');
            $table->timestamp('student_declared_at')->nullable()->after('student_declared_hired');
            $table->text('student_declaration_notes')->nullable()->after('student_declared_at');
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('absorption_recorded_by');
            $table->dropColumn([
                'absorption_status',
                'absorbed_at',
                'job_title',
                'absorption_notes',
                'absorption_recorded_at',
                'absorption_recorded_by_role',
                'student_declared_hired',
                'student_declared_at',
                'student_declaration_notes',
            ]);
        });
    }
};
