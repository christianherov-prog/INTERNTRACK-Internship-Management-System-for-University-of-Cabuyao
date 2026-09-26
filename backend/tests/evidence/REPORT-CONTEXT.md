**INTERNTRACK — Chapter IV: Results of Unit Testing Per Module**

Branch tested: `Internet-Develop`. This pass tested the current working tree, including pre-existing changes in `app/Http/Resources/UserResource.php`, `app/Services/AuthService.php`, `bootstrap/app.php`, and `frontend/src/pages/LoginPage.jsx`. The agent changed no production files. No commit or push was performed.

Environment: Windows PowerShell, PHP 8.5.8, PHPUnit 11.5.56, Laravel 12, MariaDB 10.4.32 through Laravel's MySQL driver. The frontend is React 18/Vite 6. There is no Jest, Vitest, or React Testing Library configuration/test script in frontend/package.json. No frontend test framework was installed and no JavaScript test execution is claimed. Existing PHP source-string assertions about frontend files are static checks, not React component tests.

Database: `interntrack_testing`, in the independently initialized data directory `D:/Clarence/System Thesis/interntrack-unit-mysql-20260920`. Initial runs used port 3306; remaining runs use 33307 after another server occupied 3306. The evidence bootstrap verifies APP_ENV, database name, the actual server data directory, and applied migrations. The developer databases were not reset. The test server's `innodb_file_per_table` was disabled after Windows sharing violations during DDL. This storage setting does not change application schemas or business logic.

There is no .env.testing. Existing phpunit.xml forces testing, the test database, array cache/session/mail, synchronous queue, null broadcasting and mock MISD. phpunit.evidence.xml is a test-only copy with the dedicated port. Test records use existing factories and CreatesInternshipFixtures; new file tests use Storage::fake and mail is faked. Narrow database tests use RefreshDatabase transactions. The evidence bootstrap reuses the verified migrated schema across module processes; it does not bypass per-test transaction rollback. Broad concurrency tests run separately.

Inspection covered the route middleware and controller/service/model paths for all fifteen modules, the schema dump, incremental migrations, UserFactory, fixture helpers, existing test methods, and frontend utilities. Authoritative links include users → student_profiles → programs → departments; internships carry student_id, faculty_id, coordinator_id, supervisor_id and company_id; placement rows carry internship/program requirement/company/Supervisor IDs; attendance, journals, documents, evaluations and portfolios link to internships; uploaded files live in document_attachments. Existing isolated and narrow tests were reused.

**Baseline before test changes**

The first full-suite attempt stopped before assertions because MySQL was unavailable. A subsequent attempt was interrupted after repeated schema-loader errors; the diagnostic run recorded 9 passing unit cases and 1 setup error because mysql.exe was absent from PATH. These are not completed-suite totals.

After the test server and process PATH were configured, the unchanged full suite completed **369 tests: 359 passed, 9 failed, 1 error, 0 skipped; 2,420 assertions**. Evidence: [baseline JUnit](baseline-ready.xml), [baseline output](baseline-ready.log). The error was a Windows/InnoDB table-file rename failure during migration. This baseline includes workflows, concurrency, integration and static source checks; its 369 cases must not be presented as unit tests.

**Scope and interpretation**

Final broad regression: **458 executed, 451 passed, 7 failed, 0 errors, 0 skipped; 2,712 assertions**. Its exact failures, including the intermittent concurrent clock-in deadlock, are in [final regression evidence](FINAL-REGRESSION.md). The Chapter IV selection below is a subset, not an additional 141 cases. It contains **62 isolated unit tests (62 passed)** and **79 narrow component tests (75 passed, 4 failed)**.

Deliverables: [test files added/modified](TEST-FILE-CHANGES.md), [executed commands and setup](SETUP-AND-COMMANDS.md), [failure root causes and recommendations](FAILURE-ANALYSIS.md), [known coverage limits](COVERAGE-LIMITATIONS.md), and [full regression issue counts](FINAL-REGRESSION.md).

[U] denotes isolated function/helper/model tests without persisted fixtures or HTTP dispatch. New isolated tests reject database queries; the nine reused attendance unit cases still inherit the existing MySQL-dependent application bootstrap. [C] denotes narrowly scoped Laravel component tests using HTTP and/or a dedicated database. They test a specific operation or rule and are explicitly **not strict database-independent unit tests**. Keep this qualification in the manuscript. Broad report/portfolio rendering, notification propagation workflows and concurrency scenarios are excluded from the following totals.

A Passed entry means the matching PHPUnit case actually executed and all its assertions passed. “Assertions verified” identifies the specific observed assertion outcome; it is not a manual demonstration or screenshot. A test case may assert several conditions. Separate scoring boundaries are separate cases, not separate functions. Combined IDs cover related conditions in a single executed case; do not count them twice.
