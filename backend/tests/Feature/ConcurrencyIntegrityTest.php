<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\Notification;
use App\Models\OjtRequirementTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\Support\RunsParallelRequests;
use Tests\TestCase;

class ConcurrencyIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;
    use RunsParallelRequests;

    /** Child artisan workers must see committed rows. */
    protected $connectionsToTransact = [];

    /** @var list<array<string, mixed>> */
    private static array $party = [];

    public static function tearDownAfterClass(): void
    {
        self::$party = [];
        RefreshDatabaseState::$migrated = false;
        parent::tearDownAfterClass();
    }

    /** @return list<array<string, mixed>> */
    private function party(): array
    {
        if (self::$party === []) {
            self::$party = $this->seedTenActiveStudents();
        }

        foreach (self::$party as $i => $row) {
            self::$party[$i]['token'] = $row['student']->createToken('concurrency')->plainTextToken;
            self::$party[$i]['supervisor_token'] = $row['supervisor']->createToken('concurrency')->plainTextToken;
            self::$party[$i]['faculty_token'] = $row['faculty']->createToken('concurrency')->plainTextToken;
        }

        return self::$party;
    }

    /** @return list<array{student:User,token:string,internship:Internship,supervisor:User}> */
    private function seedTenActiveStudents(): array
    {
        $prefix = 'C'.substr(str_replace('.', '', uniqid('', true)), -10);
        $faculty = $this->makeUser('faculty', $prefix.'-FAC');
        \App\Models\FacultySectionAssignment::updateOrCreate(
            [
                'program' => 'BSIT',
                'section' => '4ITD',
                'school_year' => '2024-2025',
                'semester' => 2,
            ],
            [
                'faculty_user_id' => $faculty->id,
                'is_active' => true,
            ]
        );
        $coordinator = $this->makeUser('coordinator', $prefix.'-COR');
        $company = $this->makeEligibleCompany(['company_name' => 'Concurrency HTE '.$prefix, 'slots_available' => 20]);

        OjtRequirementTemplate::firstOrCreate(
            ['name' => 'Concurrency Requirement'],
            [
                'description' => 'Concurrency upload target',
                'category' => 'during',
                'sort_order' => 1,
                'is_active' => true,
                'created_by' => $coordinator->id,
            ]
        );

        $party = [];
        for ($i = 1; $i <= 10; $i++) {
            $student = $this->makeStudentWithSection();
            $student->studentProfile->update([
                'first_name' => 'Student',
                'last_name' => sprintf('CONC-%02d', $i),
            ]);
            $supervisor = $this->makeUser('supervisor', sprintf($prefix.'-S%02d', $i));
            $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
            $party[] = [
                'student' => $student,
                'token' => $student->createToken('concurrency')->plainTextToken,
                'internship' => $internship,
                'supervisor' => $supervisor,
                'supervisor_token' => $supervisor->createToken('concurrency')->plainTextToken,
                'faculty' => $faculty,
                'coordinator' => $coordinator,
                'company' => $company,
            ];
        }

        return $party;
    }

    public function test_ten_students_login_sessions_are_isolated(): void
    {
        $party = $this->party();
        $jobs = array_map(fn ($row) => [
            'method' => 'POST',
            'uri' => '/api/v1/auth/login',
            'json' => [
                'username' => $row['student']->student_number,
                'password' => 'password',
            ],
        ], $party);

        $results = $this->parallelRequests($jobs);
        $this->assertCount(10, $results);

        $ids = [];
        foreach ($results as $i => $result) {
            $this->assertSame(200, $result['json']['status'] ?? null, $result['stdout'].$result['stderr']);
            $user = $result['json']['body']['user'] ?? [];
            $userId = $user['id'] ?? $user['data']['id'] ?? null;
            $this->assertSame($party[$i]['student']->id, $userId);
            $this->assertNotContains($userId, $ids);
            $ids[] = $userId;
        }
    }

    public function test_ten_students_apply_and_double_apply_does_not_duplicate(): void
    {
        $party = $this->party();
        $companyId = $party[0]['company']->id;

        $results = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'POST',
            'uri' => '/api/v1/student/applications',
            'token' => $row['token'],
            'json' => ['company_id' => $companyId],
        ], $party));

        foreach ($results as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout']);
            $this->assertNotEmpty($result['json']['body']['application']['company_name'] ?? null);
        }

        $this->assertSame(10, InternshipApplication::query()->count());
        $this->assertSame(10, InternshipApplication::query()->distinct()->count('student_id'));

        $student = $party[0];
        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/student/applications', 'token' => $student['token'], 'json' => ['company_id' => $companyId]],
            ['method' => 'POST', 'uri' => '/api/v1/student/applications', 'token' => $student['token'], 'json' => ['company_id' => $companyId]],
        ]);

        foreach ($dupes as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout']);
        }
        $this->assertSame(1, InternshipApplication::query()->where('student_id', $student['student']->id)->count());
    }

    public function test_ten_clock_ins_and_double_clock_in_stay_unique(): void
    {
        $party = $this->party();

        $results = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'POST',
            'uri' => '/api/v1/student/attendance/clock-in',
            'token' => $row['token'],
        ], $party));

        $created = collect($results)->where(fn ($r) => ($r['json']['status'] ?? 0) === 201)->count();
        $this->assertSame(10, $created, json_encode(array_map(fn ($r) => $r['json'], $results)));
        $this->assertSame(10, AttendanceLog::query()->count());
        $this->assertSame(10, AttendanceLog::query()->distinct()->count('internship_id'));

        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/student/attendance/clock-in', 'token' => $party[0]['token']],
            ['method' => 'POST', 'uri' => '/api/v1/student/attendance/clock-in', 'token' => $party[0]['token']],
        ]);
        $this->assertTrue(collect($dupes)->every(fn ($r) => ($r['json']['status'] ?? 0) === 422));
        $this->assertSame(1, AttendanceLog::query()->where('internship_id', $party[0]['internship']->id)->count());
    }

    public function test_ten_journals_and_same_week_double_submit_stay_unique(): void
    {
        $party = $this->party();
        $payload = fn ($summary) => [
            'week_number' => 3,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => $summary,
        ];

        $results = $this->parallelRequests(array_map(fn ($row, $i) => [
            'method' => 'POST',
            'uri' => '/api/v1/student/logbook',
            'token' => $row['token'],
            'json' => $payload('Week work '.$i),
        ], $party, array_keys($party)));

        foreach ($results as $result) {
            $this->assertSame(201, $result['json']['status'] ?? 0, $result['stdout']);
        }
        $this->assertSame(10, JournalEntry::query()->count());

        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/student/logbook', 'token' => $party[0]['token'], 'json' => $payload('First')],
            ['method' => 'POST', 'uri' => '/api/v1/student/logbook', 'token' => $party[0]['token'], 'json' => $payload('Second')],
        ]);
        foreach ($dupes as $result) {
            $this->assertSame(201, $result['json']['status'] ?? 0, $result['stdout']);
        }
        // Week numbers are derived from the internship start, not the client payload.
        $this->assertSame(1, JournalEntry::query()->where('internship_id', $party[0]['internship']->id)->whereDate('date', '2026-09-01')->count());
    }

    public function test_ten_document_uploads_and_double_upload_stay_unique(): void
    {
        $party = $this->party();

        $results = $this->parallelRequests(array_map(fn ($row, $i) => [
            'method' => 'POST',
            'uri' => '/api/v1/student/documents/upload',
            'token' => $row['token'],
            'json' => [
                'document_type' => 'Concurrency Requirement',
                'drive_link' => 'https://drive.example.test/student-'.($i + 1),
            ],
        ], $party, array_keys($party)));

        foreach ($results as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout']);
        }
        $this->assertSame(10, Document::query()->count());
        $this->assertSame(10, Document::query()->distinct()->count('internship_id'));

        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/student/documents/upload', 'token' => $party[0]['token'], 'json' => [
                'document_type' => 'Concurrency Requirement',
                'drive_link' => 'https://drive.example.test/a',
            ]],
            ['method' => 'POST', 'uri' => '/api/v1/student/documents/upload', 'token' => $party[0]['token'], 'json' => [
                'document_type' => 'Concurrency Requirement',
                'drive_link' => 'https://drive.example.test/b',
            ]],
        ]);
        foreach ($dupes as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout']);
        }
        $this->assertSame(1, Document::query()->where('internship_id', $party[0]['internship']->id)->count());
    }

    public function test_ten_messages_do_not_cross_threads(): void
    {
        $party = $this->party();

        $results = $this->parallelRequests(array_map(fn ($row, $i) => [
            'method' => 'POST',
            'uri' => '/api/v1/messages',
            'token' => $row['token'],
            'json' => [
                'internship_id' => $row['internship']->id,
                'recipient_id' => $row['supervisor']->id,
                'body' => 'Hello from student '.($i + 1),
            ],
        ], $party, array_keys($party)));

        foreach ($results as $result) {
            $this->assertTrue(($result['json']['status'] ?? 0) < 300, $result['stdout']);
        }
        $this->assertSame(10, Message::query()->count());
        foreach ($party as $i => $row) {
            $message = Message::query()->where('sender_id', $row['student']->id)->first();
            $this->assertNotNull($message);
            $this->assertSame($row['supervisor']->id, $message->recipient_id);
            $this->assertSame($row['internship']->id, $message->internship_id);
            $this->assertSame('Hello from student '.($i + 1), $message->body);
        }
    }

    public function test_profile_updates_do_not_leak_across_students(): void
    {
        $party = $this->party();

        $results = $this->parallelRequests(array_map(fn ($row, $i) => [
            'method' => 'PUT',
            'uri' => '/api/v1/auth/profile',
            'token' => $row['token'],
            'json' => ['contact_number' => '090000000'.sprintf('%02d', $i + 1)],
        ], $party, array_keys($party)));

        foreach ($results as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout'].json_encode($result['json']));
        }

        foreach ($party as $i => $row) {
            $this->assertSame(
                '090000000'.sprintf('%02d', $i + 1),
                $row['student']->fresh('studentProfile')->studentProfile->contact_number
            );
        }
    }

    public function test_document_approve_reject_race_is_deterministic(): void
    {
        $party = $this->party();
        $row = $party[0];
        Sanctum::actingAs($row['student']);
        $this->postJson('/api/v1/student/documents/upload', [
            'document_type' => 'Concurrency Requirement',
            'drive_link' => 'https://drive.example.test/race',
        ])->assertSuccessful();

        $document = Document::query()->where('internship_id', $row['internship']->id)->firstOrFail();
        $facultyToken = $row['faculty']->createToken('concurrency')->plainTextToken;
        $coordinatorToken = $row['coordinator']->createToken('concurrency')->plainTextToken;

        $results = $this->parallelRequests([
            [
                'method' => 'POST',
                'uri' => '/api/v1/faculty/documents/'.$document->id.'/review',
                'token' => $facultyToken,
                'json' => ['action' => 'approve', 'remarks' => 'Faculty approve'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/coordinator/documents/'.$document->id.'/review',
                'token' => $coordinatorToken,
                'json' => ['action' => 'reject', 'remarks' => 'Coordinator reject'],
            ],
        ]);

        $statuses = collect($results)->pluck('json.status')->all();
        $this->assertTrue(collect($statuses)->every(fn ($status) => $status === 200), json_encode($results));

        $document->refresh();
        $this->assertContains($document->status, ['approved', 'rejected']);
        $this->assertGreaterThanOrEqual(1, $document->reviews()->count());
        $this->assertContains($document->status, $document->reviews()->pluck('to_status')->all());
    }

    public function test_placement_race_consumes_only_one_slot(): void
    {
        $coordA = $this->makeUser('coordinator', 'CONC-PLC-A');
        $coordB = $this->makeUser('coordinator', 'CONC-PLC-B');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $supervisor = $this->makeUser('supervisor', 'CONC-PLC-SUP');
        $company = $this->makeEligibleCompany(['slots_available' => 1, 'company_name' => 'One Slot HTE']);

        $results = $this->parallelRequests([
            [
                'method' => 'POST',
                'uri' => '/api/v1/coordinator/internships/'.$internship->id.'/place',
                'token' => $coordA->createToken('concurrency')->plainTextToken,
                'json' => ['company_id' => $company->id, 'supervisor_id' => $supervisor->id],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/coordinator/internships/'.$internship->id.'/place',
                'token' => $coordB->createToken('concurrency')->plainTextToken,
                'json' => ['company_id' => $company->id, 'supervisor_id' => $supervisor->id],
            ],
        ]);

        $successes = collect($results)->where(fn ($r) => ($r['json']['status'] ?? 0) < 300)->count();
        $this->assertSame(1, $successes, json_encode($results));
        $this->assertSame('active', $internship->fresh()->status);
        $this->assertSame(0, (int) $company->fresh()->slots_available);
    }

    public function test_ten_supervisor_ids_are_unique(): void
    {
        $jobs = array_fill(0, 10, ['action' => 'next-supervisor-id']);
        $results = $this->parallelRequests($jobs);
        $ids = collect($results)->map(fn ($r) => $r['json']['faculty_number'] ?? null)->filter()->values();

        $this->assertCount(10, $ids, json_encode($results));
        $this->assertSame($ids->count(), $ids->unique()->count());
        $this->assertTrue($ids->every(fn ($id) => (bool) preg_match('/^SUP-\d{4}$/', $id)));
    }

    public function test_double_evaluation_does_not_duplicate_or_500(): void
    {
        $party = $this->party();
        foreach ($party as $item) {
            $this->approveEvaluationPeriod($item['internship'], $item['faculty']);
        }
        $row = $party[0];
        $payload = [
            'evaluation_period' => 'midterm',
            'form_type' => 'FO-24',
            'responses' => [
                'c1' => 90, 'c2' => 90, 'c3' => 90, 'c4' => 90, 'c5' => 90,
                'c6' => 90, 'c7' => 90, 'c8' => 90, 'c9' => 90, 'c10' => 90,
            ],
        ];

        $ten = $this->parallelRequests(array_map(fn ($item) => [
            'method' => 'POST',
            'uri' => '/api/v1/supervisor/evaluations/'.$item['internship']->id,
            'token' => $item['supervisor_token'],
            'json' => $payload,
        ], $party));

        foreach ($ten as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [200, 201], true), $result['stdout']);
        }
        $this->assertSame(10, Evaluation::query()->count());

        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/supervisor/evaluations/'.$row['internship']->id, 'token' => $row['supervisor_token'], 'json' => $payload],
            ['method' => 'POST', 'uri' => '/api/v1/supervisor/evaluations/'.$row['internship']->id, 'token' => $row['supervisor_token'], 'json' => $payload],
        ]);
        foreach ($dupes as $result) {
            $this->assertTrue(($result['json']['status'] ?? 0) < 300, $result['stdout']);
        }
        $this->assertSame(1, Evaluation::query()->where('internship_id', $row['internship']->id)->count());
    }

    public function test_student_cannot_read_another_student_under_concurrency(): void
    {
        $party = $this->party();
        $results = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'GET',
            'uri' => '/api/v1/student/records',
            'token' => $row['token'],
        ], $party));

        foreach ($results as $i => $result) {
            $this->assertSame(200, $result['json']['status'] ?? 0, $result['stdout']);
            $body = json_encode($result['json']['body']);
            $this->assertStringNotContainsString($party[($i + 1) % 10]['student']->student_number, $body);
        }

        $leaks = $this->parallelRequests([
            ['method' => 'GET', 'uri' => '/api/v1/faculty/assigned-students', 'token' => $party[0]['token']],
            ['method' => 'GET', 'uri' => '/api/v1/coordinator/monitoring', 'token' => $party[0]['token']],
            ['method' => 'GET', 'uri' => '/api/v1/admin/coordinators', 'token' => $party[0]['token']],
        ]);
        foreach ($leaks as $result) {
            $this->assertSame(403, $result['json']['status'] ?? 0, $result['stdout']);
        }
    }

    public function test_mixed_role_overlap_keeps_counts_aligned(): void
    {
        $party = $this->party();
        $director = $this->makeUser('director', 'CONC-DIR-01');
        $admin = $this->makeUser('admin', 'CONC-MISD-01');

        $jobs = [];
        foreach ($party as $i => $row) {
            $jobs[] = [
                'method' => 'POST',
                'uri' => '/api/v1/student/documents/upload',
                'token' => $row['token'],
                'json' => [
                    'document_type' => 'Concurrency Requirement',
                    'drive_link' => 'https://drive.example.test/mixed-'.$i,
                ],
            ];
        }
        $jobs[] = ['method' => 'GET', 'uri' => '/api/v1/faculty/assigned-students', 'token' => $party[0]['faculty']->createToken('mix')->plainTextToken];
        $jobs[] = ['method' => 'GET', 'uri' => '/api/v1/coordinator/monitoring', 'token' => $party[0]['coordinator']->createToken('mix')->plainTextToken];
        $jobs[] = ['method' => 'GET', 'uri' => '/api/v1/dashboard/summary', 'token' => $director->createToken('mix')->plainTextToken];
        $jobs[] = ['method' => 'GET', 'uri' => '/api/v1/admin/dashboard', 'token' => $admin->createToken('mix')->plainTextToken];
        foreach (array_slice($party, 0, 5) as $row) {
            $this->approveEvaluationPeriod($row['internship'], $row['faculty']);
            $jobs[] = [
                'method' => 'POST',
                'uri' => '/api/v1/supervisor/evaluations/'.$row['internship']->id,
                'token' => $row['supervisor_token'],
                'json' => [
                    'evaluation_period' => 'final',
                    'form_type' => 'FO-24',
                    'responses' => [
                        'c1' => 88, 'c2' => 88, 'c3' => 88, 'c4' => 88, 'c5' => 88,
                        'c6' => 88, 'c7' => 88, 'c8' => 88, 'c9' => 88, 'c10' => 88,
                    ],
                ],
            ];
        }

        $results = $this->parallelRequests($jobs);
        $failures = collect($results)->filter(fn ($r) => ($r['json']['status'] ?? 500) >= 500);
        $this->assertTrue($failures->isEmpty(), json_encode($failures->values()));

        $this->assertSame(10, Document::query()->count());
        $this->assertSame(5, Evaluation::query()->where('evaluation_period', 'final')->count());
        $this->assertGreaterThan(0, Notification::query()->count());

        $hours = Internship::query()->min('total_hours_rendered');
        $this->assertTrue($hours === null || (float) $hours >= 0);
    }

    public function test_ten_supervisors_cannot_review_journals_faculty_can(): void
    {
        $party = $this->party();
        foreach ($party as $i => $row) {
            JournalEntry::create([
                'internship_id' => $row['internship']->id,
                'week_number' => 8,
                'entry_number' => 8,
                'date' => '2026-09-01',
                'end_date' => '2026-09-05',
                'activities_summary' => 'Faculty review '.$i,
                'status' => 'submitted',
            ]);
        }

        $blocked = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'PATCH',
            'uri' => '/api/v1/supervisor/journals/'.JournalEntry::where('internship_id', $row['internship']->id)->where('week_number', 8)->value('id').'/review',
            'token' => $row['supervisor_token'],
            'json' => ['action' => 'approved', 'feedback' => 'Validated'],
        ], $party));

        foreach ($blocked as $result) {
            $this->assertTrue(in_array($result['json']['status'] ?? 0, [404, 405], true), $result['stdout']);
        }
        $this->assertSame(0, JournalEntry::query()->where('week_number', 8)->whereNotNull('faculty_reviewed_at')->count());

        $results = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'PATCH',
            'uri' => '/api/v1/faculty/journals/'.JournalEntry::where('internship_id', $row['internship']->id)->where('week_number', 8)->value('id').'/review',
            'token' => $row['faculty_token'],
            'json' => ['action' => 'approved', 'score' => 90, 'feedback' => 'Approved'],
        ], $party));

        foreach ($results as $result) {
            $this->assertSame(200, $result['json']['status'] ?? 0, $result['stdout']);
        }
        $this->assertSame(10, JournalEntry::query()->where('week_number', 8)->whereNotNull('faculty_reviewed_at')->count());
    }

    public function test_ten_faculty_evaluations_do_not_duplicate(): void
    {
        $party = $this->party();
        $results = $this->parallelRequests(array_map(fn ($row) => [
            'method' => 'POST',
            'uri' => '/api/v1/faculty/evaluations/'.$row['internship']->id,
            'token' => $row['faculty_token'],
            'json' => [
                'evaluation_period' => 'midterm',
                'overall_score' => 88,
                'general_comments' => 'Solid progress',
            ],
        ], $party));

        foreach ($results as $result) {
            $this->assertSame(201, $result['json']['status'] ?? 0, $result['stdout']);
        }
        $this->assertSame(10, Evaluation::query()->where('form_type', 'faculty_eval')->where('evaluation_period', 'midterm')->count());

        $dupes = $this->parallelRequests([
            ['method' => 'POST', 'uri' => '/api/v1/faculty/evaluations/'.$party[0]['internship']->id, 'token' => $party[0]['faculty_token'], 'json' => ['evaluation_period' => 'midterm', 'overall_score' => 90]],
            ['method' => 'POST', 'uri' => '/api/v1/faculty/evaluations/'.$party[0]['internship']->id, 'token' => $party[0]['faculty_token'], 'json' => ['evaluation_period' => 'midterm', 'overall_score' => 91]],
        ]);
        foreach ($dupes as $result) {
            $this->assertTrue(($result['json']['status'] ?? 0) < 300, $result['stdout']);
        }
        $this->assertSame(1, Evaluation::query()
            ->where('internship_id', $party[0]['internship']->id)
            ->where('form_type', 'faculty_eval')
            ->where('evaluation_period', 'midterm')
            ->count());
    }

    public function test_integrity_invariants_after_concurrent_writes(): void
    {
        $this->party();

        $this->assertSame(0, (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT internship_id, date, COUNT(*) n FROM attendance_logs WHERE deleted_at IS NULL GROUP BY internship_id, date HAVING n > 1
            ) d'
        )->c);
        $this->assertSame(0, (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT internship_id, week_number, COUNT(*) n FROM journal_entries WHERE deleted_at IS NULL AND week_number IS NOT NULL GROUP BY internship_id, week_number HAVING n > 1
            ) d'
        )->c);
        $this->assertSame(0, (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT faculty_number, COUNT(*) n FROM users WHERE faculty_number IS NOT NULL GROUP BY faculty_number HAVING n > 1
            ) d'
        )->c);
    }
}
