$users = \App\Models\User::whereIn("school_id", ["2300600", "2300592"])->get();
foreach ($users as $u) {
  $profile = $u->studentProfile;
  echo $u->school_id . "|" . $u->email . "|" . $u->role . "|profile:" . ($profile ? "yes" : "no") . "\n";
}
echo "count=" . $users->count() . "\n";
