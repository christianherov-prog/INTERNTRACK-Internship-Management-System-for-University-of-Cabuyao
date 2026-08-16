<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'coe.student@uc.edu.ph')->first();
if ($user) {
    echo "User found by email!\n";
    echo "Username: " . $user->username . "\n";
} else {
    echo "User not found by email.\n";
}
