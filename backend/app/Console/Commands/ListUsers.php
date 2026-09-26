<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::with(['studentProfile', 'facultyProfile'])->get();

        if ($users->isEmpty()) {
            $this->error('❌ No users found in the database!');
            $this->info('💡 Run: php artisan migrate:fresh --seed');
            return;
        }

        $this->info('📋 Users in Database:');
        $this->newLine();

        $tableData = [];
        
        foreach ($users as $user) {
            $profile = $user->studentProfile ?? $user->facultyProfile;
            $name = 'N/A';
            
            if ($profile) {
                if ($user->studentProfile) {
                    $name = trim("{$profile->first_name} {$profile->middle_name} {$profile->last_name}");
                } elseif ($user->facultyProfile) {
                    $name = trim("{$profile->first_name} {$profile->middle_name} {$profile->last_name}");
                }
            }

            $tableData[] = [
                'ID' => $user->id,
                'Username' => $user->username,
                'Role' => strtoupper($user->role),
                'Name' => $name,
                'Email' => $user->email ?? 'N/A',
                'Active' => $user->is_active ? '✓' : '✗',
            ];
        }

        $this->table(
            ['ID', 'Username', 'Role', 'Name', 'Email', 'Active'],
            $tableData
        );

        $this->newLine();
        $this->info('✅ Total users: ' . $users->count());
        $this->newLine();
        $this->info('🔐 Default password for all users: interntrack123');
    }
}
