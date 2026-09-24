**Final full-suite regression (separate from unit evidence)**

The final complete suite executed 458 cases: **451 passed, 7 failed, 0 skipped, 0 errors; 2,712 assertions**. PHPUnit exited 1. Evidence: [JUnit](final-full-suite.xml), [raw output](final-full-suite.log). These totals include integration, concurrency and static checks. They must not be added to the 141 selected Chapter IV cases, which are a subset.

Exact failed tests:

1. Tests\Feature\ChapterIV\FocusedComponentsTest::test_UT_APP_04_ATOMIC
2. Tests\Feature\ChapterIV\FocusedComponentsTest::test_UT_DOC_EVAL_SCOPE
3. Tests\Feature\ChapterIV\FocusedComponentsTest::test_UT_JRN_11
4. Tests\Feature\ChapterIV\FocusedComponentsTest::test_UT_SUP_FACULTY_ONLY
5. Tests\Feature\ConcurrencyIntegrityTest::test_ten_clock_ins_and_double_clock_in_stay_unique
6. Tests\Feature\Student2300590IdentityTest::test_mock_misd_catalogs_angel_luis_taac_for_2300590
7. Tests\Feature\Student2300590IdentityTest::test_login_sync_replaces_uc_student_stub_with_catalog_name

No PHPUnit cases were skipped. The four selected failures and two original identity failures are explained in [failure analysis](FAILURE-ANALYSIS.md).

The concurrency failure returned nine HTTP 201 responses and one HTTP 500 instead of ten successful clock-ins. The application log identifies SQLSTATE 40001 / MariaDB 1213 during insertion into attendance_logs on verified test port 33307. [Preserved log excerpt](clock-in-deadlock-excerpt.log). StudentController::clockIn wraps a transaction in UniqueWrite::retry, which already retries deadlocks up to four attempts; exhaustion rethrows the exception and yields 500. DtrWorkflowService::clockIn locks the existing attendance query before insertion. The observed immediate cause is the escaping deadlock; the precise competing lock cycle was not captured, so an index/gap-lock explanation remains a hypothesis. Recommend capturing InnoDB deadlock diagnostics and reviewing lock scope, query indexes and bounded retry/backoff. Do not remove the assertion or declare this a unit failure: it is a concurrency reliability defect observed in this environment.

**Issue counts and interpretation**

- Selected component defects: 2 (unauthorized meeting persistence; wrong evaluation form satisfies a requirement).
- Additional reproduced issues outside the selection: 1 mock-catalog identity defect and 1 concurrent clock-in reliability defect.
- Requirement conflicts: 2 (Coordinator journal and Supervisor review versus the requested Faculty-only rules).
- Corrected test-only issues: 9 (seven original assumptions/fixtures/cache-isolation issues listed in FAILURE-ANALYSIS.md, plus the two new-test expectation/schema corrections). The retained login-sync expectation is a further unresolved historical test mismatch.
- Environment/setup issues are documented separately and are not production-defect counts. The unverified portfolio-query concern is also excluded.

No production fixes were applied. A targeted concurrency recheck is retained separately; a successful retry does not erase the failure from this completed suite.

The targeted recheck **passed: 1 test, 5 assertions**, in 18.694 seconds. Evidence: [JUnit](clock-in-concurrency-recheck.xml), [output](clock-in-concurrency-recheck.log). The failure is intermittent; the final full-suite result remains 451 passed and 7 failed.
