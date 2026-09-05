<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'coe.student@uc.edu.ph')->first();
if ($user) {
    $user->username = 'STU-COE';
    $user->save();
    echo "Updated coe.student username to STU-COE!\n";
}

$coord = App\Models\User::where('email', 'coe.coord@uc.edu.ph')->first();
if ($coord) {
    $coord->username = 'COR-COE';
    $coord->save();
    echo "Updated coe.coord username to COR-COE!\n";
}

$faculty = App\Models\User::where('email', 'coe.faculty@uc.edu.ph')->first();
if ($faculty) {
    $faculty->username = 'FAC-COE';
    $faculty->save();
    echo "Updated coe.faculty username to FAC-COE!\n";
}
