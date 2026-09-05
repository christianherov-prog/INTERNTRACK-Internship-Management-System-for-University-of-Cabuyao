<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('role', 'coordinator')->first();
auth()->login($user);
$request = request();
$request->setUserResolver(function() use ($user) { return $user; });

try {
    $res = app()->make(App\Http\Controllers\Api\CoordinatorController::class)->records($request);
    echo "RECORDS OK\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
