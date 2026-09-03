<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Starting manual rollback...\n";

// 1. Add role column
if (!Schema::hasColumn('users', 'role')) {
    echo "Adding role column...\n";
    Schema::table('users', function (Blueprint $table) {
        $table->enum('role', ['admin', 'student', 'faculty', 'supervisor', 'director', 'coordinator'])->default('student')->after('remember_token');
    });
}

// 2. Populate role column from Spatie tables
if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
    echo "Populating role column from Spatie tables...\n";
    $userRoles = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->select('model_has_roles.model_id', 'roles.name')
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->get();

    foreach ($userRoles as $ur) {
        DB::table('users')->where('id', $ur->model_id)->update(['role' => $ur->name]);
    }
}

// 3. Drop Spatie tables
$spatieTables = ['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'];
foreach ($spatieTables as $table) {
    if (Schema::hasTable($table)) {
        echo "Dropping table $table...\n";
        Schema::drop($table);
    }
}

// 4. Remove from migrations table
echo "Cleaning up migrations table...\n";
DB::table('migrations')->whereIn('migration', [
    '2026_08_13_115954_create_permission_tables',
    '2026_08_13_120159_migrate_user_roles_to_spatie',
    '2026_08_13_121500_drop_role_column_from_users'
])->delete();

echo "Done.\n";
