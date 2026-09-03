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
    $res = app()->make(App\Http\Controllers\Api\RequirementTemplateController::class)->index($request);
    echo "INDEX OK\n";
    
    $res2 = app()->make(App\Http\Controllers\Api\RequirementTemplateController::class)->options($request);
    echo "OPTIONS OK\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
