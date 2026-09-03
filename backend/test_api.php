<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new \App\Http\Controllers\Api\RequirementTemplateController();
// mock request
$request = \Illuminate\Http\Request::create('/api/v1/faculty/requirements/options', 'GET');
$request->setUserResolver(function() {
    return \App\Models\User::where('role', 'faculty')->first();
});

$response = $controller->options($request);
echo $response->getContent();
