<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.connections.mysql.database' => 'interntrack_testing']);
\Illuminate\Support\Facades\DB::purge('mysql');

$columns = \Illuminate\Support\Facades\DB::select('DESCRIBE users');
foreach ($columns as $col) {
    echo $col->Field . "\n";
}
