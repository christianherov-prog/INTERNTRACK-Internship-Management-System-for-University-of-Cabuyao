**Failures and corrections**

No application behavior was changed. The following four selected cases intentionally retain failing assertions against the requested requirements:

| Test | Expected | Executed observation | Root cause and source | Recommended correction |
|---|---|---|---|---|
| UT-APP-04-ATOMIC | Reject an unauthorized meeting without persisting it | HTTP 403, but a matching meeting row remains | MeetingController::store calls Meeting::create before checking internship ownership and attendee scope | Validate authority and audience before insertion; make meeting/attendee creation atomic |
| UT-DOC-EVAL-SCOPE | Host Evaluation requires its applicable submitted form | A submitted FO-24 alone makes Host Evaluation approved | DocumentComplianceService::systemGeneratedSatisfaction checks for any submitted internship evaluation and does not constrain form_type/evaluator_type | Resolve each requirement to its official form/evaluator and query only the applicable submitted record |
| UT-JRN-11 | Coordinator cannot review a weekly journal under the requested Faculty-only rule | Coordinator receives 200 when occupying faculty_id | routes/api.php admits faculty,coordinator to the Faculty group; FacultyController::reviewJournal checks assigned ID/department but not exclusive role | Reconcile the deployed Coordinator-as-Faculty design with the requested final rule; enforce exclusive Faculty role if that rule is authoritative |
| UT-SUP-FACULTY-ONLY | Only authorized Faculty approve Supervisor requests | Same-department Coordinator receives 200 | Faculty route middleware and SupervisorRegistrationController::assertFacultyMayReview explicitly permit Coordinator department scope | Reconcile the same role-rule discrepancy and enforce Faculty-only review if required |

The first two are reproduced application defects. The latter two are reproduced discrepancies between the user's Faculty-only requirement and current intentional role behavior; existing RoleAuthorizationLeakTest expects Coordinator access. They are reported as failures against the supplied requirement, not silently converted into passing current-behavior tests.

**Unchanged baseline failures**

| Exact original PHPUnit test | Classification and root cause | Test-only action / subsequent evidence |
|---|---|---|
| AbsorptionFlowTest::test_student_can_declare_hired_after_completed_internship | Environment: migration fails with SQLSTATE HY000/1025 and OS error 32 on an InnoDB file rename; server log identifies a Windows sharing violation. The external program holding the file was not identified. | Rechecked successfully in baseline-rechecks.xml; later DDL failures retained in schema-rebuild*.xml/log |
| ConcurrencyIntegrityTest::test_ten_journals_and_same_week_double_submit_stay_unique | Outdated assertion queries client week_number=3 although server derives the week from internship start | Assert uniqueness by the submitted date and internship; concurrency-recheck.xml executed 1 case with 14 assertions, passed |
| DepartmentOwnershipRepairTest::test_login_does_not_fall_back_to_first_unrelated_coordinator | Fixture deliberately assigns the wrong department and expects login to repair it; current AuthService explicitly removed login-time reconciliation | Fixture now supplies the authoritative CCS Coordinator and retains assertions preventing substitution of the first unrelated CHAS account |
| FacultyJournalsPageRepairTest::test_supervisor_journal_validation_remains_absent_from_nav_and_api | Outdated static UI expectation for a dedicated Faculty Journals sidebar link | Assert current Assigned Students entry and its Journal Review Queue; Supervisor route-denial checks retained |
| FacultyJournalsPageRepairTest::test_assigned_faculty_sees_authorized_journal_and_review_persists | Relative internship start (now minus 40 days) conflicts with fixed journal dates and asserted Week 1 | Pin fixture start to 2026-08-24; targeted recheck passed |
| InternshipProgressConsistencyTest::test_nursing_internship_uses_2703_hours_and_five_htes | Test-process state: migration-time SchemaCache value can survive across tests; isolated recheck passes | TestCase resets this request-lifetime cache after test setup; production cache unchanged |
| MisdMockSyncTest::test_mock_student_sync_is_idempotent_and_preserves_local_account_fields | Old fixture expectation: mock record 2300600 now has section 4ITA, not 4ITD | Expect canonical 4ITA; targeted recheck passed |
| PortfolioDataIntegrationTest::test_fo31_journal_and_student_signature_path | Old expectation trusts client Week 3; June 1 to September 1 is chronological Week 14 | Expect independently calculated Week 14; targeted recheck passed |
| Student2300590IdentityTest::test_mock_misd_catalogs_angel_luis_taac_for_2300590 | Mock-data defect: MockMisdRepository::students declares duplicate 2300590 array keys; later John/Taac-Taac entry overwrites Angel Luis/Taac - Taac | Production mock catalog left unchanged; reconcile duplicate identity using authoritative MISD data |
| Student2300590IdentityTest::test_login_sync_replaces_uc_student_stub_with_catalog_name | Current login intentionally skips profile synchronization; original test expects the removed side effect. Duplicate mock identity also prevents treating the catalog as a reliable fix. | Retained as an open historical expectation mismatch, not used as unit evidence of automatic synchronization |

**New test corrections and environment handling**

UT-DOC-13 initially queried a guessed requirement_targets table. The model maps to ojt_requirement_targets. The assertion was corrected to use the targets relationship and was rerun successfully. This was a test schema assumption, not a production defect.

The first focused run executed 22 cases with 18 passes and 4 failures. UT-EVAL-02 incorrectly expected HTTP 422 for an unapproved evaluation period. Internship::abortUnlessEvaluationPeriodApproved explicitly returns 403; the test was corrected to assert 403, the exact Faculty-approval message, and no saved evaluation. The three other failures in that run remained. The Supervisor role-rule case was added afterward.

Test-only changes also let concurrency workers inherit the configured database port, clear request-lifetime SchemaCache between tests, and guard the evidence database. An attempted recheck was refused when port 3306 pointed to C:/xampp/mysql/data; no test case ran in that refused attempt. Later incomplete-schema bootstrap refusals are run errors, not failed business assertions or skipped tests. Earlier raw attempts are retained where available. The final report generator rejects missing/mismatched JUnit results and zero-assertion passes.

The source also shows PortfolioDataService::payload querying academic journals without an approved-only filter. This is a **source-review concern**, not an executed unit-test failure: approval-only retrieval and full portfolio assembly require additional focused query coverage. It is excluded from the reproduced defect count.
