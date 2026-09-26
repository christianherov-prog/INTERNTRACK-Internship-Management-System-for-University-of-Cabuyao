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


**Table 1. Results of Unit Testing for Authentication**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-AUTH-03-U | User::username/account_id [U] | Chosen login name and stable supervisor ID differ | Username is supervisor.login; account ID remains SUP-0123 | Assertions verified: Username is supervisor.login; account ID remains SUP-0123 | Passed |
| UT-AUTH-05-U | EnsureUserHasRole::handle [U] | Student calls Faculty-only middleware | 403 response and downstream action is not called | Assertions verified: 403 response and downstream action is not called | Passed |
| UT-AUTH-04-U | User::hasExactRole [U] | Coordinator checked against Faculty-only role | Exact Faculty-only check returns false | Assertions verified: Exact Faculty-only check returns false | Passed |
| UT-AUTH-01 | AuthService::login [C] | Valid student credentials | 200 and a nonempty authentication token | Assertions verified: 200 and a nonempty authentication token | Passed |
| UT-AUTH-02 | AuthService::login [C] | Incorrect password | 422 response | Assertions verified: 422 response | Passed |
| UT-AUTH-03 | AuthService::login [C] | Faculty ID exists only on authoritative profile | Correct Faculty account authenticates | Assertions verified: Correct Faculty account authenticates | Passed |
| UT-AUTH-06 | AuthService::login [C] | Inactive Supervisor supplies credentials | 422 response | Assertions verified: 422 response | Passed |
| UT-AUTH-05 | Backend route role authorization [C] | Student calls staff APIs | Faculty and Coordinator endpoints return 403 | Assertions verified: Faculty and Coordinator endpoints return 403 | Passed |

Table 1 presents 8 executed test cases for Authentication: 8 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 3 isolated unit cases and 5 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-AUTH.xml), [raw output](module-AUTH.log), [exact PHPUnit arguments](command-AUTH.json). Reproduce this selection with `php tests/run-chapteriv.php AUTH` from `backend`.

**Table 2. Results of Unit Testing for Student Profile**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-PROFILE-03-U | UpdateProfileRequest::rules [U] | Malformed email and overlong contact | Both email and contact validation fail | Assertions verified: Both email and contact validation fail | Passed |
| UT-PROFILE-02-U | UpdateProfileRequest::rules [U] | Permitted contact and valid email | Validation passes; this does not test persistence | Assertions verified: Validation passes; this does not test persistence | Passed |
| UT-PROFILE-08-U | InternshipAccess::canView [U] | Different student IDs | Access returns false | Assertions verified: Access returns false | Passed |
| UT-PROFILE-01 | UserResource::toArray [C] | Student profile department serialization | Department is structured with authoritative ID/code | Assertions verified: Department is structured with authoritative ID/code | Passed |
| UT-PROFILE-02 | AuthService::updateProfile [C] | Contact update accompanied by unauthorized identity edits | Contact saved as 09180001111; original identity retained | Assertions verified: Contact saved as 09180001111; original identity retained | Passed |
| UT-PROFILE-04 | DepartmentScope relationship resolution [C] | CCS program Student department | Resolves CCS department | Assertions verified: Resolves CCS department | Passed |
| UT-PROFILE-05 | UserResource faculty resolution [C] | Unassigned Student with unrelated Faculty present | No unrelated Faculty fallback | Assertions verified: No unrelated Faculty fallback | Passed |
| UT-PROFILE-07 | ProgramRequirementService::forProgram [C] | Configured HTE hour requirements | Target hours and HTE count match stored configuration | Assertions verified: Target hours and HTE count match stored configuration | Passed |
| UT-PROFILE-06 | User::activeInternship [C] | Current active internship has authoritative company/Supervisor | Resolves exact current internship, company and Supervisor IDs | Assertions verified: Resolves exact current internship, company and Supervisor IDs | Passed |

Table 2 presents 9 executed test cases for Student Profile: 9 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 3 isolated unit cases and 6 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-PROFILE.xml), [raw output](module-PROFILE.log), [exact PHPUnit arguments](command-PROFILE.json). Reproduce this selection with `php tests/run-chapteriv.php PROFILE` from `backend`.

**Table 3. Results of Unit Testing for Placement**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-PLACE-02-U | InternshipStatuses::normalize/openCurrent [U] | Legacy ongoing and completed statuses | Ongoing becomes active; completed does not block another placement | Assertions verified: Ongoing becomes active; completed does not block another placement | Passed |
| UT-PLACE-04-U | InternshipAccess::canManageAsCoordinator [U] | Student attempts Coordinator operation | Permission returns false | Assertions verified: Permission returns false | Passed |
| UT-PLACE-06-U | Internship::participantUserIds [U] | Duplicate Faculty/Coordinator ID and null Supervisor | Returns only distinct authoritative IDs 11 and 22 | Assertions verified: Returns only distinct authoritative IDs 11 and 22 | Passed |
| UT-PLACE-07 | Coordinator placement validation [C] | Company MOA expired | 422; internship stays pending_placement | Assertions verified: 422; internship stays pending_placement | Passed |
| UT-PLACE-SLOTS | Coordinator placement validation [C] | Company has no available slots | 422 | Assertions verified: 422 | Passed |
| UT-PLACE-06 | Coordinator placement validation [C] | Faculty ID supplied as Supervisor | 422 | Assertions verified: 422 | Passed |
| UT-PLACE-05 | InternshipProvisioning::createPendingIfNone [C] | Open internship already exists | No duplicate open internship | Assertions verified: No duplicate open internship | Passed |

Table 3 presents 7 executed test cases for Placement: 7 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 3 isolated unit cases and 4 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-PLACE.xml), [raw output](module-PLACE.log), [exact PHPUnit arguments](command-PLACE.json). Reproduce this selection with `php tests/run-chapteriv.php PLACE` from `backend`.

**Table 4. Results of Unit Testing for Attendance**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-ATT-05-U | ManilaAttendanceClock::creditedHours [U] | 09:00–12:00 and 13:00–17:00 | Returns 7.0 credited hours | Assertions verified: Returns 7.0 credited hours | Passed |
| UT-ATT-08-U | AttendanceDayResolver::isFullDayAbsent [U] | Approved weekday schedule; no attendance before and at 17:00 | Not absent at 16:59; absent at 17:00 | Assertions verified: Not absent at 16:59; absent at 17:00 | Passed |
| UT-ATT-07-U | AttendanceDayResolver::isFullDayAbsent [U] | Afternoon work start after scheduled day ends | Returns false for absence | Assertions verified: Returns false for absence | Passed |
| UT-ATT-08-WEEKEND | AttendanceDayResolver::isFullDayAbsent [U] | Saturday without attendance | Weekend is not marked absent | Assertions verified: Weekend is not marked absent | Passed |
| UT-ATT-11-U | ManilaTime::todayDateString [U] | UTC 16:01 crosses Manila midnight | Returns 2026-09-19 | Assertions verified: Returns 2026-09-19 | Passed |
| UT-ATT-15-U | PortfolioDataService::serializeAttendance [U] | Unsaved authoritative log with 3.25 hours | Preserves 3.25 hours and suppresses unvalidated supervisor signature | Assertions verified: Preserves 3.25 hours and suppresses unvalidated supervisor signature | Passed |
| UT-ATT-13 | DtrWorkflowController::submitCorrection [C] | Submit clock-out correction for yesterday | 201 pending_supervisor; original clock_out stays null; audit exists | Assertions verified: 201 pending_supervisor; original clock_out stays null; audit exists | Passed |
| UT-ATT-01-03 | Attendance clock session [C] | Clock In then Clock Out on same session | 201 pending session; Clock Out returns 200 | Assertions verified: 201 pending session; Clock Out returns 200 | Passed |
| UT-ATT-02 | Attendance duplicate validation [C] | Second Clock In | Second request returns 422 | Assertions verified: Second request returns 422 | Passed |
| UT-ATT-04 | Attendance duplicate validation [C] | Second Clock Out after first timeout | 422; first stored clock_out remains 17:03 | Assertions verified: 422; first stored clock_out remains 17:03 | Passed |
| UT-ATT-09 | Attendance current-state resolver [C] | Historical attendance but no current-day session | Historical log does not produce clocked-in-today state | Assertions verified: Historical log does not produce clocked-in-today state | Passed |
| UT-ATT-12 | Attendance correction date validation [C] | Correction outside three-day limit | 422 | Assertions verified: 422 | Passed |
| UT-ATT-EXISTING-AMPM | ManilaAttendanceClock helpers [U] | eight am is am and one oh three pm is pm | 08:00, 11:59, noon, 13:03 classified into correct AM/PM periods | Assertions verified: 08:00, 11:59, noon, 13:03 classified into correct AM/PM periods | Passed |
| UT-ATT-EXISTING-AFTERNOON | ManilaAttendanceClock helpers [U] | afternoon only leaves am blank not absent | 13:01–13:03 leaves AM blank and no absence flag | Assertions verified: 13:01–13:03 leaves AM blank and no absence flag | Passed |
| UT-ATT-EXISTING-PMOUT | ManilaAttendanceClock helpers [U] | pm clock out never appears under am time out | 17:00 clock out appears only in PM | Assertions verified: 17:00 clock out appears only in PM | Passed |
| UT-ATT-EXISTING-BREAK | ManilaAttendanceClock helpers [U] | normal full day with break resume | 08:02/11:58 and 13:01/17:04 map to correct columns | Assertions verified: 08:02/11:58 and 13:01/17:04 map to correct columns | Passed |
| UT-ATT-EXISTING-MORNING | ManilaAttendanceClock helpers [U] | morning only does not fabricate pm | 08:00–11:30 has no PM entries | Assertions verified: 08:00–11:30 has no PM entries | Passed |
| UT-ATT-EXISTING-NOON | ManilaAttendanceClock helpers [U] | cross noon without break does not fabricate lunch | 11:30–13:30 has no fabricated lunch boundaries | Assertions verified: 11:30–13:30 has no fabricated lunch boundaries | Passed |
| UT-ATT-EXISTING-LATE | ManilaAttendanceClock helpers [U] | late morning clock in is not am absent | 11:00 is not AM absent | Assertions verified: 11:00 is not AM absent | Passed |
| UT-ATT-EXISTING-EXACTNOON | ManilaAttendanceClock helpers [U] | exact noon clock in is pm not day absent | 12:00 classified PM with no absence | Assertions verified: 12:00 classified PM with no absence | Passed |
| UT-ATT-EXISTING-HOURS | ManilaAttendanceClock helpers [U] | hours helper unchanged for canonical credit math | 08:00–12:00 plus 13:00–17:00 credits 8.0 hours | Assertions verified: 08:00–12:00 plus 13:00–17:00 credits 8.0 hours | Passed |
| UT-ATT-06-U | DtrWorkflowService::breakMinutesFor [U] | Completed one-hour break and incomplete break | 60 minutes for completed break; 0 for incomplete break | Assertions verified: 60 minutes for completed break; 0 for incomplete break | Passed |

Table 4 presents 22 executed test cases for Attendance: 22 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 16 isolated unit cases and 6 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-ATT.xml), [raw output](module-ATT.log), [exact PHPUnit arguments](command-ATT.json). Reproduce this selection with `php tests/run-chapteriv.php ATT` from `backend`.

**Table 5. Results of Unit Testing for Weekly Journal**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-JRN-02-U | JournalPeriodValidator::validateAndResolveWeek [U] | Range starts before internship start | ValidationException with exact before-start message | Assertions verified: ValidationException with exact before-start message | Passed |
| UT-JRN-05-U | JournalPeriodValidator::validateAndResolveWeek [U] | End date precedes start | ValidationException identifies end_date | Assertions verified: ValidationException identifies end_date | Passed |
| UT-JRN-WEEK | JournalPeriodValidator::weekNumberFor [U] | Start date and next seven-day block | Returns week 1 then week 2 | Assertions verified: Returns week 1 then week 2 | Passed |
| UT-JRN-13-U | Fo31JournalPresenter::dateRange/weekLabel [U] | Week 2 September 7–11 | Returns September 7–11, 2026 and Week 2 | Assertions verified: Returns September 7–11, 2026 and Week 2 | Passed |
| UT-JRN-08 | FacultyController::reviewJournal [C] | Assigned Faculty reviews submitted weekly journal | 200; approved record has Faculty reviewer ID | Assertions verified: 200; approved record has Faculty reviewer ID | Passed |
| UT-JRN-09 | FacultyController::reviewJournal [C] | Unrelated Faculty reviews submitted journal | 404; journal remains submitted | Assertions verified: 404; journal remains submitted | Passed |
| UT-JRN-10 | FacultyController::reviewJournal [C] | Industry Supervisor calls journal review endpoint | 403; journal remains submitted | Assertions verified: 403; journal remains submitted | Passed |
| UT-JRN-11 | FacultyController::reviewJournal [C] | Coordinator occupies faculty_id relationship | Faculty-only requirement denies Coordinator | Coordinator received HTTP 200 from journal approval instead of the required HTTP 403. | Failed |
| UT-JRN-01 | Student journal submission [C] | Period begins on internship start | 201; chronological week 1 and correct date | Assertions verified: 201; chronological week 1 and correct date | Passed |
| UT-JRN-03 | JournalPeriodValidator overlap query [C] | Overlapping and nonoverlapping ranges | Overlap rejected; disjoint range accepted | Assertions verified: Overlap rejected; disjoint range accepted | Passed |
| UT-JRN-12 | Student journal edit protection [C] | Edit approved weekly journal | 422 | Assertions verified: 422 | Passed |
| UT-JRN-FUTURE | JournalPeriodValidator future bound [C] | Future Manila period | 422 with future-period message | Assertions verified: 422 with future-period message | Passed |

Table 5 presents 12 executed test cases for Weekly Journal: 11 passed, 1 failed, 0 execution errors, and 0 skipped. The selection comprised 4 isolated unit cases and 8 narrowly scoped Laravel component cases. The unsuccessful cases and their source-level causes are documented in FAILURE-ANALYSIS.md; the module cannot be reported as passed. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-JRN.xml), [raw output](module-JRN.log), [exact PHPUnit arguments](command-JRN.json). Reproduce this selection with `php tests/run-chapteriv.php JRN` from `backend`.

**Table 6. Results of Unit Testing for Documents & Requirements**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-DOC-05-U | DocumentComplianceService::resolveTemplateState [U] | Submission status approved | Resolver returns approved from document ID 4 | Assertions verified: Resolver returns approved from document ID 4 | Passed |
| UT-DOC-07-U | DocumentComplianceService::resolveTemplateState [U] | Submission status pending_faculty | Resolver returns pending from document ID 4 | Assertions verified: Resolver returns pending from document ID 4 | Passed |
| UT-DOC-08-U | DocumentComplianceService::resolveTemplateState [U] | Submission status rejected | Resolver returns rejected from document ID 4 | Assertions verified: Resolver returns rejected from document ID 4 | Passed |
| UT-DOC-06-U | DocumentComplianceService::resolveTemplateState [U] | No submissions for custom requirement | Returns missing and no document ID | Assertions verified: Returns missing and no document ID | Passed |
| UT-DOC-APPROVAL-PRIORITY | DocumentComplianceService::resolveTemplateState [U] | Rejected row precedes approved historical row in supplied collection | Approved ID remains authoritative | Assertions verified: Approved ID remains authoritative | Passed |
| UT-DOC-09-10 | DocumentComplianceService::evaluateStudent [C] | Three applicable customs; approved, pending, missing | Denominator 3; approved numerator 1; pending 1; missing 1; 33% | Assertions verified: Denominator 3; approved numerator 1; pending 1; missing 1; 33% | Passed |
| UT-DOC-EVAL-SCOPE | DocumentComplianceService::resolveTemplateState [C] | Host-evaluation requirement with only submitted FO-24 performance evaluation | Unrelated form must not satisfy Host Evaluation | The Host Evaluation requirement returned approved despite only a submitted FO-24 record being present. | Failed |
| UT-DOC-15 | RequirementTemplateController authorization [C] | Student attempts to modify Faculty template | 403 | Assertions verified: 403 | Passed |
| UT-DOC-01 | RequirementAudience query [C] | Different program/section requirements | Only matching requirements appear | Assertions verified: Only matching requirements appear | Passed |
| UT-DOC-11 | Document compliance completion state [C] | All applicable requirements versus incomplete set | Complete label only when all requirements are satisfied | Assertions verified: Complete label only when all requirements are satisfied | Passed |
| UT-DOC-12 | Requirement template update [C] | Faculty edits standard Application Letter | Updated fields persist; system_code and is_system retained | Assertions verified: Updated fields persist; system_code and is_system retained | Passed |
| UT-DOC-14 | Coordinator requirement list [C] | Standard plus custom requirements | Custom remains listed; standard requirements excluded | Assertions verified: Custom remains listed; standard requirements excluded | Passed |
| UT-DOC-02-04 | StudentController::uploadDocument [C] | Allowed PDF for applicable requirement | 201; attachment filename, MIME, size and private file preserved | Assertions verified: 201; attachment filename, MIME, size and private file preserved | Passed |
| UT-DOC-03 | StudentController::uploadDocument [C] | Executable supplied for applicable requirement | 422; file validation error and no document | Assertions verified: 422; file validation error and no document | Passed |
| UT-DOC-13 | RequirementTemplateController::store [C] | Coordinator creates section-targeted custom requirement | 201; custom requirement owner and section target persist | Assertions verified: 201; custom requirement owner and section target persist | Passed |

Table 6 presents 15 executed test cases for Documents & Requirements: 14 passed, 1 failed, 0 execution errors, and 0 skipped. The selection comprised 5 isolated unit cases and 10 narrowly scoped Laravel component cases. The unsuccessful cases and their source-level causes are documented in FAILURE-ANALYSIS.md; the module cannot be reported as passed. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-DOC.xml), [raw output](module-DOC.log), [exact PHPUnit arguments](command-DOC.json). Reproduce this selection with `php tests/run-chapteriv.php DOC` from `backend`.

**Table 7. Results of Unit Testing for Supervisor Management**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-SUP-12-U | InternshipAccess::canView [U] | Supervisor 8 accesses internship assigned to 7 | Access returns false | Assertions verified: Access returns false | Passed |
| UT-SUP-13-U | InternshipAccess::canView [U] | Supervisor 7 accesses internship assigned to 7 | Access returns true | Assertions verified: Access returns true | Passed |
| UT-SUP-07-10 | SupervisorRegistrationController::approve [C] | Assigned Faculty approves registered Supervisor fixture | 200; approved invite; Supervisor active and assigned; account count unchanged | Assertions verified: 200; approved invite; Supervisor active and assigned; account count unchanged | Passed |
| UT-SUP-11 | SupervisorRegistrationController::reject [C] | Assigned Faculty rejects registered Supervisor with remarks | 200; rejected invite; inactive Supervisor not assigned | Assertions verified: 200; rejected invite; inactive Supervisor not assigned | Passed |
| UT-SUP-FACULTY-ONLY | SupervisorRegistrationController::approve [C] | Same-department Coordinator attempts approval | Faculty-only requirement denies Coordinator | Coordinator received HTTP 200 from Supervisor approval instead of the required HTTP 403. | Failed |
| UT-SUP-03 | Supervisor unique identity validation [C] | Already-assigned account ID | Duplicate identity rejected | Assertions verified: Duplicate identity rejected | Passed |
| UT-SUP-09 | SupervisorRegistrationController::reject [C] | Rejection omits remarks | 422 remarks error; invite remains registered; Supervisor not assigned | Assertions verified: 422 remarks error; invite remains registered; Supervisor not assigned | Passed |
| UT-SUP-05 | Acceptance Form upload validation [C] | Missing or invalid Acceptance Form | Validation rejects missing/invalid file | Assertions verified: Validation rejects missing/invalid file | Passed |
| UT-SUP-08 | Supervisor approval authorization [C] | Unrelated Faculty approves invitation | Forbidden response; assignment not approved | Assertions verified: Forbidden response; assignment not approved | Passed |
| UT-SUP-02 | Supervisor account reuse [C] | Register with existing identity | No duplicate account | Assertions verified: No duplicate account | Passed |

Table 7 presents 10 executed test cases for Supervisor Management: 9 passed, 1 failed, 0 execution errors, and 0 skipped. The selection comprised 2 isolated unit cases and 8 narrowly scoped Laravel component cases. The unsuccessful cases and their source-level causes are documented in FAILURE-ANALYSIS.md; the module cannot be reported as passed. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-SUP.xml), [raw output](module-SUP.log), [exact PHPUnit arguments](command-SUP.json). Reproduce this selection with `php tests/run-chapteriv.php SUP` from `backend`.

**Table 8. Results of Unit Testing for Feedback**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-FDBK-02-U | SupervisorFeedbackService::assertAssignedSupervisor [U] | Unrelated Supervisor | Throws HTTP 403 | Assertions verified: Throws HTTP 403 | Passed |
| UT-FDBK-07-U | SupervisorFeedbackService::serialize [U] | Stored UTC review timestamp | Manila display is Sep 18, 2026 1:00 PM | Assertions verified: Manila display is Sep 18, 2026 1:00 PM | Passed |
| UT-FDBK-03 | SupervisorController::feedback [C] | Assigned Supervisor submits empty feedback | 422 feedback validation error | Assertions verified: 422 feedback validation error | Passed |
| UT-FDBK-01 | SupervisorFeedbackService::upsert [C] | Assigned Supervisor saves one feedback note | Note persisted as supervisor_note with reviewer and text | Assertions verified: Note persisted as supervisor_note with reviewer and text | Passed |
| UT-FDBK-04 | Supervisor feedback length validation [C] | Maximum length and maximum plus one | Maximum accepted; over limit rejected | Assertions verified: Maximum accepted; over limit rejected | Passed |

Table 8 presents 5 executed test cases for Feedback: 5 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 2 isolated unit cases and 3 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-FDBK.xml), [raw output](module-FDBK.log), [exact PHPUnit arguments](command-FDBK.json). Reproduce this selection with `php tests/run-chapteriv.php FDBK` from `backend`.

**Table 9. Results of Unit Testing for Evaluations**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-EVAL-04-96 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 96 | Weighted average 96, rating Excellent | Assertions verified: Weighted average 96, rating Excellent | Passed |
| UT-EVAL-04-90 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 90 | Weighted average 90, rating Very Good | Assertions verified: Weighted average 90, rating Very Good | Passed |
| UT-EVAL-04-85 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 85 | Weighted average 85, rating Good | Assertions verified: Weighted average 85, rating Good | Passed |
| UT-EVAL-04-80 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 80 | Weighted average 80, rating Fair | Assertions verified: Weighted average 80, rating Fair | Passed |
| UT-EVAL-04-75 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 75 | Weighted average 75, rating Passed | Assertions verified: Weighted average 75, rating Passed | Passed |
| UT-EVAL-04-74 | Evaluation::computeScores [U] | All ten FO-24 criteria rated 74 | Weighted average 74, rating Failed | Assertions verified: Weighted average 74, rating Failed | Passed |
| UT-EVAL-04-MIXED | Evaluation::computeScores [U] | FO-22 q1=5 q2=4 with comment and unrelated numeric field | Average 4.5; total 9; Outstanding | Assertions verified: Average 4.5; total 9; Outstanding | Passed |
| UT-EVAL-06-U | Evaluation::computeScores [U] | Absent responses | No score or submission timestamp fabricated | Assertions verified: No score or submission timestamp fabricated | Passed |
| UT-EVAL-03 | SupervisorController::submitEvaluation [C] | Required evaluation fields omitted | 422 evaluation_period/form_type/responses errors | Assertions verified: 422 evaluation_period/form_type/responses errors | Passed |
| UT-EVAL-02 | SupervisorController::submitEvaluation [C] | Assigned evaluator but period not approved | 403 Faculty approval message and no evaluation created | Assertions verified: 403 Faculty approval message and no evaluation created | Passed |
| UT-EVAL-05 | SupervisorController::submitEvaluation [C] | Assigned evaluator with approved period and complete FO-24 | 201; submitted record has correct evaluator and average 90 | Assertions verified: 201; submitted record has correct evaluator and average 90 | Passed |
| UT-EVAL-07 | SupervisorController::submitEvaluation [C] | Unrelated Supervisor with valid form payload | 404; no evaluation record saved | Assertions verified: 404; no evaluation record saved | Passed |
| UT-EVAL-04-WEIGHTED | Evaluation::computeScores [U] | FO-24 first criterion 100; other nine criteria 80 | Weighted average 85.0; Good | Assertions verified: Weighted average 85.0; Good | Passed |

Table 9 presents 13 executed test cases for Evaluations: 13 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 9 isolated unit cases and 4 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. One new test initially expected 422 for an unapproved evaluation period; inspection confirmed the implemented 403 gate, and the test was corrected and rerun.

Evidence: [JUnit](module-EVAL.xml), [raw output](module-EVAL.log), [exact PHPUnit arguments](command-EVAL.json). Reproduce this selection with `php tests/run-chapteriv.php EVAL` from `backend`.

**Table 10. Results of Unit Testing for Portfolio**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-PORT-11-U | InternshipAccess::internshipIdFromPath [U] | Private portfolio path | Resolves internship ID 42, unrelated path returns null | Assertions verified: Resolves internship ID 42, unrelated path returns null | Passed |
| UT-PORT-03-04 | StudentPortfolioController::uploadPhoto [C] | Upload PNG then replace singleton company logo | Both uploads return 201; new file exists; old file removed; one active logo document | Assertions verified: Both uploads return 201; new file exists; old file removed; one active logo document | Passed |
| UT-PORT-05 | StudentPortfolioController::deletePhoto [C] | Delete own stored portfolio image fixture | 200; stored file and active document removed | Assertions verified: 200; stored file and active document removed | Passed |
| UT-PORT-11 | StudentPortfolioController::deletePhoto [C] | Student tries deleting another student image fixture | 403; document and file remain | Assertions verified: 403; document and file remain | Passed |
| UT-PORT-02 | Portfolio image validation [C] | PDF certificate supplied to image-only upload | 422 | Assertions verified: 422 | Passed |
| UT-PORT-FILE | Portfolio image validation [C] | PDF logo and executable certificate | Both return 422 | Assertions verified: Both return 422 | Passed |
| UT-PORT-01 | Portfolio text update [C] | Student supplies chapter text | Chapter text persists | Assertions verified: Chapter text persists | Passed |
| UT-PORT-08-U | PortfolioDataService::serializeEvaluation [U] | FO-24 record with no submitted_at then a stored timestamp | Pending before submission; completed after timestamp; form code preserved | Assertions verified: Pending before submission; completed after timestamp; form code preserved | Passed |
| UT-PORT-06-U | PortfolioDataService::serializeJournal [U] | Approved weekly record with stored activities | Preserves approved status, week 2, dates and stored activities | Assertions verified: Preserves approved status, week 2, dates and stored activities | Passed |

Table 10 presents 9 executed test cases for Portfolio: 9 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 3 isolated unit cases and 6 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-PORT.xml), [raw output](module-PORT.log), [exact PHPUnit arguments](command-PORT.json). Reproduce this selection with `php tests/run-chapteriv.php PORT` from `backend`.

**Table 11. Results of Unit Testing for Messages**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-MSG-02-U | Internship::isParticipant [U] | Authoritative participant and outsider | Known Faculty accepted; unrelated ID rejected | Assertions verified: Known Faculty accepted; unrelated ID rejected | Passed |
| UT-MSG-03 | Private message thread authorization [C] | Unrelated user reads or sends | Private thread requests rejected | Assertions verified: Private thread requests rejected | Passed |
| UT-MSG-04 | Message archive state [C] | User archives and restores own thread | Archive affects only acting user and is reversible | Assertions verified: Archive affects only acting user and is reversible | Passed |
| UT-MSG-01 | Message send and retrieval [C] | Authorized internship participant sends message | Message saved and available to intended thread | Assertions verified: Message saved and available to intended thread | Passed |

Table 11 presents 4 executed test cases for Messages: 4 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 1 isolated unit cases and 3 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-MSG.xml), [raw output](module-MSG.log), [exact PHPUnit arguments](command-MSG.json). Reproduce this selection with `php tests/run-chapteriv.php MSG` from `backend`.

**Table 12. Results of Unit Testing for Appointments**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-APP-04-U | MeetingController::store [U] | Student directly invokes restricted controller method | Throws HTTP 403 before validation or database writes | Assertions verified: Throws HTTP 403 before validation or database writes | Passed |
| UT-APP-01 | MeetingController::store [C] | Faculty creates a valid unscoped meeting | 201; scheduled meeting saved with Faculty creator ID | Assertions verified: 201; scheduled meeting saved with Faculty creator ID | Passed |
| UT-APP-02 | MeetingController::store [C] | Required title/type/start omitted | 422 with all three field errors | Assertions verified: 422 with all three field errors | Passed |
| UT-APP-03 | MeetingController::store [C] | End precedes start | 422 ends_at error; no meeting saved | Assertions verified: 422 ends_at error; no meeting saved | Passed |
| UT-APP-04-ATOMIC | MeetingController::store [C] | Other Coordinator attaches unmanaged internship | 403 and no unauthorized meeting persists | HTTP 403 was returned, but the unauthorized meeting row remained in the database. | Failed |
| UT-APP-05 | Meeting participant validation [C] | Unrelated attendee ID on internship meeting | 422 | Assertions verified: 422 | Passed |

Table 12 presents 6 executed test cases for Appointments: 5 passed, 1 failed, 0 execution errors, and 0 skipped. The selection comprised 1 isolated unit cases and 5 narrowly scoped Laravel component cases. The unsuccessful cases and their source-level causes are documented in FAILURE-ANALYSIS.md; the module cannot be reported as passed. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-APP.xml), [raw output](module-APP.log), [exact PHPUnit arguments](command-APP.json). Reproduce this selection with `php tests/run-chapteriv.php APP` from `backend`.

**Table 13. Results of Unit Testing for Notifications**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-NOTIF-02-U | NotificationPreferences::prefKeyForType [U] | Message and meeting notification types | Map to directMessages and meetingInvites | Assertions verified: Map to directMessages and meetingInvites | Passed |
| UT-NOTIF-PREF | User::wantsNotification [U] | Saved opt-out overrides Student default | directMessages false; attendanceAlerts true | Assertions verified: directMessages false; attendanceAlerts true | Passed |
| UT-NOTIF-01 | NotificationController::index [C] | Two users have private notifications | Only current user notification returned | Assertions verified: Only current user notification returned | Passed |
| UT-NOTIF-03 | NotificationController::markRead [C] | Owner reads stored unread notification | 200 and read_at changes from null to a timestamp | Assertions verified: 200 and read_at changes from null to a timestamp | Passed |
| UT-NOTIF-04 | NotificationController::markRead [C] | Unrelated user attempts to read private notification | 404 and read_at stays null | Assertions verified: 404 and read_at stays null | Passed |
| UT-NOTIF-OPT-OUT | Notification::notify [C] | Recipient opts out | No notification created | Assertions verified: No notification created | Passed |
| UT-NOTIF-OPT-IN | Notification::notify [C] | Recipient opts in | Notification created for recipient | Assertions verified: Notification created for recipient | Passed |

Table 13 presents 7 executed test cases for Notifications: 7 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 2 isolated unit cases and 5 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-NOTIF.xml), [raw output](module-NOTIF.log), [exact PHPUnit arguments](command-NOTIF.json). Reproduce this selection with `php tests/run-chapteriv.php NOTIF` from `backend`.

**Table 14. Results of Unit Testing for Reports/Data Builders**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-RPT-01-500-80 | Internship::progress_percent [U] | 80 rendered / 500 target hours | Progress returns 16% | Assertions verified: Progress returns 16% | Passed |
| UT-RPT-01-500-550 | Internship::progress_percent [U] | 550 rendered / 500 target hours | Progress returns 100% | Assertions verified: Progress returns 100% | Passed |
| UT-RPT-01-0-80 | Internship::progress_percent [U] | 80 rendered / 0 target hours | Progress returns 0% | Assertions verified: Progress returns 0% | Passed |
| UT-RPT-05-U | InternshipStatuses::label [U] | for_evaluation status | Returns For Evaluation | Assertions verified: Returns For Evaluation | Passed |
| UT-RPT-06-U | OfficialFormAsset::clockLabel [U] | 13:03 wall clock | Returns 1:03 PM | Assertions verified: Returns 1:03 PM | Passed |
| UT-RPT-03 | Internship::computeTotalHours [C] | Validated 8h and pending 4h attendance | Returns 8h from validated records | Assertions verified: Returns 8h from validated records | Passed |
| UT-RPT-02 | Faculty compliance data builder [C] | Approved Application Letter | Approved count/status and dynamic denominator agree | Assertions verified: Approved count/status and dynamic denominator agree | Passed |

Table 14 presents 7 executed test cases for Reports/Data Builders: 7 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 5 isolated unit cases and 2 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-RPT.xml), [raw output](module-RPT.log), [exact PHPUnit arguments](command-RPT.json). Reproduce this selection with `php tests/run-chapteriv.php RPT` from `backend`.

**Table 15. Results of Unit Testing for Admin/MISD**

| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| UT-ADM-01-U | EnsureUserHasRole::handle [U] | Faculty calls Admin-only operation | 403 and downstream action not called | Assertions verified: 403 and downstream action not called | Passed |
| UT-ADM-04-U | MisdAdminController::sanitizeAuditPayload [U] | Nested password/token and permitted role | Secrets removed recursively; role retained | Assertions verified: Secrets removed recursively; role retained | Passed |
| UT-ADM-02 | MisdAdminController::updateStaff [C] | Admin supplies nonboolean account status | 422 is_active error; active status unchanged | Assertions verified: 422 is_active error; active status unchanged | Passed |
| UT-ADM-05 | MisdAdminController::auditLog [C] | Student requests Admin audit log | 403 backend denial | Assertions verified: 403 backend denial | Passed |
| UT-ADM-06 | MisdAdminController::storeSectionAssignment [C] | Admin omits required section assignment fields | 422 validation response | Assertions verified: 422 validation response | Passed |
| UT-ADM-03-U | StaffAssignmentService::assign [U] | Attempt unsupported staff role student | role validation error before external lookup or database writes | Assertions verified: role validation error before external lookup or database writes | Passed |
| UT-ADM-04 | audit_log helper [C] | Explicit actor, action and nonsensitive metadata | Audit record stores actor ID, action and probe metadata | Assertions verified: Audit record stores actor ID, action and probe metadata | Passed |

Table 15 presents 7 executed test cases for Admin/MISD: 7 passed, 0 failed, 0 execution errors, and 0 skipped. The selection comprised 3 isolated unit cases and 4 narrowly scoped Laravel component cases. The selected functions produced the asserted results; this finding does not establish correctness of untested paths. No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.

Evidence: [JUnit](module-ADM.xml), [raw output](module-ADM.log), [exact PHPUnit arguments](command-ADM.json). Reproduce this selection with `php tests/run-chapteriv.php ADM` from `backend`.

**Consolidated results**

| Module | Tests Executed | Passed | Failed | Skipped | Errors | Overall Result |
|---|---:|---:|---:|---:|---:|---|
| Authentication | 8 | 8 | 0 | 0 | 0 | Passed |
| Student Profile | 9 | 9 | 0 | 0 | 0 | Passed |
| Placement | 7 | 7 | 0 | 0 | 0 | Passed |
| Attendance | 22 | 22 | 0 | 0 | 0 | Passed |
| Weekly Journal | 12 | 11 | 1 | 0 | 0 | With Failures |
| Documents & Requirements | 15 | 14 | 1 | 0 | 0 | With Failures |
| Supervisor Management | 10 | 9 | 1 | 0 | 0 | With Failures |
| Feedback | 5 | 5 | 0 | 0 | 0 | Passed |
| Evaluations | 13 | 13 | 0 | 0 | 0 | Passed |
| Portfolio | 9 | 9 | 0 | 0 | 0 | Passed |
| Messages | 4 | 4 | 0 | 0 | 0 | Passed |
| Appointments | 6 | 5 | 1 | 0 | 0 | With Failures |
| Notifications | 7 | 7 | 0 | 0 | 0 | Passed |
| Reports/Data Builders | 7 | 7 | 0 | 0 | 0 | Passed |
| Admin/MISD | 7 | 7 | 0 | 0 | 0 | Passed |
| **Total** | **141** | **137** | **4** | **0** | **0** | **With Failures** |

The Chapter IV selection contains 62 unit cases and 79 narrow component cases. These are distinct from the full baseline and integration rechecks; repeated runs are not added to these totals. Skipped means a PHPUnit skip, not an untested checklist item. No selected case is labelled Passed without a matching executed JUnit case and assertions.

See [failure analysis](FAILURE-ANALYSIS.md), [coverage limitations](COVERAGE-LIMITATIONS.md), [test-to-source manifest](results.json), and [final git status](git-status-final.txt). The supported conclusion is limited to the tested functions, and does not describe the system as fully functional.
