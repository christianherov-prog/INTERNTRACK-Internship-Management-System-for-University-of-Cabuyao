<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RefactorControllers extends Command
{
    protected $signature = 'refactor:controllers';
    protected $description = 'Replace ->section with ->section in controllers';

    public function handle()
    {
        $dir = app_path('Http/Controllers/Api');
        $files = File::allFiles($dir);

        foreach ($files as $file) {
            $content = File::get($file);
            $modified = false;
            
            // Skip AcademicStructureController and RequirementTemplateController
            if (in_array($file->getFilename(), ['AcademicStructureController.php', 'RequirementTemplateController.php'])) {
                continue;
            }

            // Replace $profile->section -> $profile->section
            // We want to avoid matching $assignment->section because assignment now has ->section which is a relation! Wait!
            // $assignment->section was a string before. Now it's a relation. So $assignment->section!
            
            $content = preg_replace('/(\$profile\??->section)(?!_id|\?->name|->id)/', '$1?->name', $content, -1, $count1);
            $content = preg_replace('/(\$studentProfile\??->section)(?!_id|\?->name|->id)/', '$1?->name', $content, -1, $count2);
            $content = preg_replace('/(\$assignment\??->section)(?!_id|\?->name|->id)/', '$1?->name', $content, -1, $count3);
            $content = preg_replace('/(\$local\??->section)(?!_id|\?->name|->id)/', '$1?->name', $content, -1, $count4);
            $content = preg_replace('/(\$a\??->section)(?!_id|\?->name|->id)/', '$1?->name', $content, -1, $count5);
            
            if ($count1 > 0 || $count2 > 0 || $count3 > 0 || $count4 > 0 || $count5 > 0) {
                File::put($file, $content);
                $this->info("Refactored: " . $file->getFilename());
            }
        }
    }
}
