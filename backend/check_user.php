<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'STU-COE')->first();
if ($user) {
    echo "User found.\n";
    echo "Password verify 'interntrack123': " . (password_verify('interntrack123', $user->password) ? 'YES' : 'NO') . "\n";
    echo "Role: " . $user->role . "\n";
} else {
    echo "User STU-COE not found.\n";
}
