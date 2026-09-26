<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\Program::where("code", "BACHELORO")->first();
if ($p) {
  $p->code = "BSIT";
  $p->save();
  echo "Fixed BSIT\n";
}

$p2 = App\Models\Program::where("name", "Bachelor of Science in Computer Science")->first();
if (!$p2) {
  App\Models\Program::create(["name" => "Bachelor of Science in Computer Science", "code" => "BSCS", "department_id" => 3, "is_active" => true]);
  echo "Created BSCS\n";
}
