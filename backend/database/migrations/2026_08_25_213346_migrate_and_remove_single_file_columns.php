<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate requirement template files
        $templates = DB::table('ojt_requirement_templates')->whereNotNull('template_file_path')->get();
        foreach ($templates as $template) {
            DB::table('requirement_template_attachments')->insert([
                'requirement_template_id' => $template->id,
                'file_path' => $template->template_file_path,
                'file_name' => $template->template_file_name ?: $template->name . ' Template',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Migrate student submission files
        $documents = DB::table('documents')->whereNotNull('file_path')->get();
        foreach ($documents as $document) {
            DB::table('document_attachments')->insert([
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'file_name' => $document->file_name ?: $document->document_type . ' Submission',
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the old columns
        Schema::table('ojt_requirement_templates', function (Blueprint $table) {
            $table->dropColumn(['template_file_path', 'template_file_name']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_size', 'mime_type']);
        });
    }

    public function down(): void
    {
        Schema::table('ojt_requirement_templates', function (Blueprint $table) {
            $table->string('template_file_path')->nullable();
            $table->string('template_file_name')->nullable();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
        });
    }
};
