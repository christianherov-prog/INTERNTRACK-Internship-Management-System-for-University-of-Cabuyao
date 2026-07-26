$user = \App\Models\User::where('username', '2300590')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}

$internship = $user->internshipsAsStudent()->first();
if (!$internship) {
    echo "Internship not found\n";
    exit;
}

$internship->update([
    'company_id' => null,
    'supervisor_id' => null,
    'faculty_id' => null,
    'coordinator_id' => null,
    'status' => 'pending_placement',
    'total_hours_rendered' => 0,
]);

// Clear any progress data
\DB::table('journal_entries')->where('internship_id', $internship->id)->delete();
\DB::table('attendance_logs')->where('internship_id', $internship->id)->delete();
\DB::table('documents')->where('internship_id', $internship->id)->delete();

echo "Internship reverted to fresh enrollee status for 2300590\n";
