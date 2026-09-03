<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset database and seed with fresh 0-progress accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->confirm('⚠️  This will DELETE ALL DATA and reset the database. Continue?', true)) {
            $this->info('❌ Operation cancelled.');
            return;
        }

        $this->info('🔄 Dropping all tables...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info('✅ All tables dropped and recreated.');

        $this->newLine();
        $this->info('🌱 Seeding database with fresh accounts...');
        Artisan::call('db:seed', ['--force' => true]);
        $this->info('✅ Database seeded successfully!');

        $this->newLine();
        $this->info('📋 Created accounts:');
        $this->newLine();

        // Display created accounts
        $accounts = [
            ['Role' => 'STUDENT', 'Username' => '2300600', 'Name' => 'Christian Hero Aboy Valinado', 'Section' => '4ITD'],
            ['Role' => 'FACULTY', 'Username' => 'FAC-1001', 'Name' => 'Prof. Marvin M. Bicua', 'Section' => 'CCS'],
            ['Role' => 'COORDINATOR', 'Username' => 'COR-1001', 'Name' => 'Arcelito C. Quiatchon', 'Section' => 'CCS'],
            ['Role' => 'DIRECTOR', 'Username' => 'DIR-1001', 'Name' => 'Prof. Gina M. Oloresisimo', 'Section' => 'Director'],
        ];

        $this->table(
            ['Role', 'Username', 'Name', 'Section'],
            $accounts
        );

        $this->newLine();
        $this->info('🔐 Password for ALL accounts: interntrack123');
        $this->newLine();
        $this->info('✨ The student account (2300600) has ZERO progress - perfect for testing!');
        $this->newLine();
    }
}
