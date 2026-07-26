<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'signer_name')) {
                $table->string('signer_name')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('evaluations', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('signer_name');
            }
            if (!Schema::hasColumn('evaluations', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_path');
            }
        });

        Schema::table('document_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('document_reviews', 'signer_name')) {
                $table->string('signer_name')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('document_reviews', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('signer_name');
            }
            if (!Schema::hasColumn('document_reviews', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_path');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'attestation_name')) {
                $table->string('attestation_name')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('documents', 'attested_at')) {
                $table->timestamp('attested_at')->nullable()->after('attestation_name');
            }
        });

        // Develop already ships a flat `messages` table (internship-scoped DM).
        // Keep that schema; only add conversation/meeting tables from the V2 realtime migration.
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('internship_id')->unique()->constrained('internships')->cascadeOnDelete();
                $table->string('subject')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'user_id']);
            });
        }

        // Flat internship-scoped `messages` is created in 2026_07_21_100000.
        // Do NOT create conversation-scoped messages here (would clash with MessageController).

        if (!Schema::hasTable('meetings')) {
            Schema::create('meetings', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('type', 40)->default('other'); // orientation|check_in|defense_prep|other
                $table->text('description')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('location')->nullable();
                $table->string('meeting_url')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('internship_id')->nullable()->constrained('internships')->nullOnDelete();
                $table->string('status', 30)->default('scheduled'); // scheduled|cancelled|completed
                $table->timestamps();
                $table->index(['starts_at', 'status']);
            });
        }

        if (!Schema::hasTable('meeting_attendees')) {
            Schema::create('meeting_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('rsvp', 20)->default('pending'); // pending|accepted|declined|maybe
                $table->timestamps();
                $table->unique(['meeting_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
        // messages table owned by 2026_07_21_100000 — do not drop here
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['attestation_name', 'attested_at']);
        });

        Schema::table('document_reviews', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'signature_path', 'signed_at']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'signature_path', 'signed_at']);
        });
    }
};
