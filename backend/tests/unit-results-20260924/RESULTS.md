# Unit test results - September 24, 2026

Branch: Internet-Develop; base commit: c927209. Test-only change: ManilaAttendanceClockFo30Test now uses IsolatedTestCase, which rejects database queries, removing its unnecessary MySQL startup dependency. No application code changed.

Executed the complete PHPUnit Unit suite: 64 tests, 132 assertions, 0 failures, 0 errors, 0 skipped. PHPUnit reported 11.256 seconds and 50 MB. PHP 8.5.8 / PHPUnit 11.5.56.

Command (from backend):

```powershell
php vendor/phpunit/phpunit/phpunit --testsuite Unit --log-junit tests/unit-results-20260924/unit-suite.xml --colors=never
```

Scope: existing backend unit tests only. These results do not cover database integration, HTTP workflows, browser behavior, or frontend component tests. The preliminary 54-test run is a subset and is not added to the final count.

Evidence: [JUnit XML](unit-suite.xml), [execution log](unit-suite.log), [case table CSV](test-cases.csv).

| Module | Tests | Passed | Failed | Errors | Skipped |
|---|---:|---:|---:|---:|---:|
| Administration | 3 | 3 | 0 | 0 | 0 |
| Applications | 1 | 1 | 0 | 0 | 0 |
| Attendance | 17 | 17 | 0 | 0 | 0 |
| Authentication | 4 | 4 | 0 | 0 | 0 |
| Documents | 5 | 5 | 0 | 0 | 0 |
| Evaluation | 9 | 9 | 0 | 0 | 0 |
| Feedback | 2 | 2 | 0 | 0 | 0 |
| Journals | 4 | 4 | 0 | 0 | 0 |
| Messaging | 1 | 1 | 0 | 0 | 0 |
| Notifications | 2 | 2 | 0 | 0 | 0 |
| Placement | 3 | 3 | 0 | 0 | 0 |
| Portfolio | 3 | 3 | 0 | 0 | 0 |
| Reports | 5 | 5 | 0 | 0 | 0 |
| Student profile | 3 | 3 | 0 | 0 | 0 |
| Supervision | 2 | 2 | 0 | 0 | 0 |
| **Total** | **64** | **64** | **0** | **0** | **0** |

## Individual test cases

| Module | Test | Scenario | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| Administration |test_UT_ADM_01_U |Faculty calls Admin-only operation |403 and downstream action not called |All 1 assertions passed |Passed |
| Administration |test_UT_ADM_04_U |Nested password/token and permitted role |Secrets removed recursively; role retained |All 1 assertions passed |Passed |
| Applications |test_UT_APP_04_U |Student directly invokes restricted controller method |Throws HTTP 403 before validation or database writes |All 1 assertions passed |Passed |
| Attendance |test_UT_ATT_05_U |09:00â€“12:00 and 13:00â€“17:00 |Returns 7.0 credited hours |All 1 assertions passed |Passed |
| Attendance |test_UT_ATT_08_U |Approved weekday schedule; no attendance before and at 17:00 |Not absent at 16:59; absent at 17:00 |All 2 assertions passed |Passed |
| Attendance |test_UT_ATT_07_U |Afternoon work start after scheduled day ends |Returns false for absence |All 1 assertions passed |Passed |
| Attendance |test_UT_ATT_08_WEEKEND |Saturday without attendance |Weekend is not marked absent |All 2 assertions passed |Passed |
| Attendance |test_UT_ATT_11_U |UTC 16:01 crosses Manila midnight |Returns 2026-09-19 |All 1 assertions passed |Passed |
| Attendance |test_UT_ATT_15_U |Unsaved authoritative log with 3.25 hours |Preserves 3.25 hours and suppresses unvalidated supervisor signature |All 3 assertions passed |Passed |
| Authentication |test_UT_AUTH_03_U |Chosen login name and stable supervisor ID differ |Username is supervisor.login; account ID remains SUP-0123 |All 2 assertions passed |Passed |
| Authentication |test_UT_AUTH_05_U |Student calls Faculty-only middleware |403 response and downstream action is not called |All 1 assertions passed |Passed |
| Authentication |test_UT_AUTH_04_U |Coordinator checked against Faculty-only role |Exact Faculty-only check returns false |All 1 assertions passed |Passed |
| Documents |test_UT_DOC_05_U |Submission status approved |Resolver returns approved from document ID 4 |All 2 assertions passed |Passed |
| Documents |test_UT_DOC_07_U |Submission status pending_faculty |Resolver returns pending from document ID 4 |All 2 assertions passed |Passed |
| Documents |test_UT_DOC_08_U |Submission status rejected |Resolver returns rejected from document ID 4 |All 2 assertions passed |Passed |
| Documents |test_UT_DOC_06_U |No submissions for custom requirement |Returns missing and no document ID |All 2 assertions passed |Passed |
| Documents |test_UT_DOC_APPROVAL_PRIORITY |Rejected row precedes approved historical row in supplied collection |Approved ID remains authoritative |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_96 |All ten FO-24 criteria rated 96 |Weighted average 96, rating Excellent |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_90 |All ten FO-24 criteria rated 90 |Weighted average 90, rating Very Good |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_85 |All ten FO-24 criteria rated 85 |Weighted average 85, rating Good |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_80 |All ten FO-24 criteria rated 80 |Weighted average 80, rating Fair |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_75 |All ten FO-24 criteria rated 75 |Weighted average 75, rating Passed |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_74 |All ten FO-24 criteria rated 74 |Weighted average 74, rating Failed |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_MIXED |FO-22 q1=5 q2=4 with comment and unrelated numeric field |Average 4.5; total 9; Outstanding |All 3 assertions passed |Passed |
| Evaluation |test_UT_EVAL_06_U |Absent responses |No score or submission timestamp fabricated |All 2 assertions passed |Passed |
| Feedback |test_UT_FDBK_02_U |Unrelated Supervisor |Throws HTTP 403 |All 1 assertions passed |Passed |
| Feedback |test_UT_FDBK_07_U |Stored UTC review timestamp |Manila display is Sep 18, 2026 1:00 PM |All 2 assertions passed |Passed |
| Journals |test_UT_JRN_02_U |Range starts before internship start |ValidationException with exact before-start message |All 1 assertions passed |Passed |
| Journals |test_UT_JRN_05_U |End date precedes start |ValidationException identifies end_date |All 1 assertions passed |Passed |
| Journals |test_UT_JRN_WEEK |Start date and next seven-day block |Returns week 1 then week 2 |All 2 assertions passed |Passed |
| Journals |test_UT_JRN_13_U |Week 2 September 7â€“11 |Returns September 7â€“11, 2026 and Week 2 |All 2 assertions passed |Passed |
| Messaging |test_UT_MSG_02_U |Authoritative participant and outsider |Known Faculty accepted; unrelated ID rejected |All 2 assertions passed |Passed |
| Notifications |test_UT_NOTIF_02_U |Message and meeting notification types |Map to directMessages and meetingInvites |All 2 assertions passed |Passed |
| Notifications |test_UT_NOTIF_PREF |Saved opt-out overrides Student default |directMessages false; attendanceAlerts true |All 2 assertions passed |Passed |
| Placement |test_UT_PLACE_02_U |Legacy ongoing and completed statuses |Ongoing becomes active; completed does not block another placement |All 2 assertions passed |Passed |
| Placement |test_UT_PLACE_04_U |Student attempts Coordinator operation |Permission returns false |All 1 assertions passed |Passed |
| Placement |test_UT_PLACE_06_U |Duplicate Faculty/Coordinator ID and null Supervisor |Returns only distinct authoritative IDs 11 and 22 |All 1 assertions passed |Passed |
| Portfolio |test_UT_PORT_11_U |Private portfolio path |Resolves internship ID 42, unrelated path returns null |All 2 assertions passed |Passed |
| Student profile |test_UT_PROFILE_03_U |Malformed email and overlong contact |Both email and contact validation fail |All 3 assertions passed |Passed |
| Student profile |test_UT_PROFILE_02_U |Permitted contact and valid email |Validation passes; this does not test persistence |All 1 assertions passed |Passed |
| Student profile |test_UT_PROFILE_08_U |Different student IDs |Access returns false |All 1 assertions passed |Passed |
| Reports |test_UT_RPT_01_500_80 |80 rendered / 500 target hours |Progress returns 16% |All 1 assertions passed |Passed |
| Reports |test_UT_RPT_01_500_550 |550 rendered / 500 target hours |Progress returns 100% |All 1 assertions passed |Passed |
| Reports |test_UT_RPT_01_0_80 |80 rendered / 0 target hours |Progress returns 0% |All 1 assertions passed |Passed |
| Reports |test_UT_RPT_05_U |for_evaluation status |Returns For Evaluation |All 1 assertions passed |Passed |
| Reports |test_UT_RPT_06_U |13:03 wall clock |Returns 1:03 PM |All 1 assertions passed |Passed |
| Supervision |test_UT_SUP_12_U |Supervisor 8 accesses internship assigned to 7 |Access returns false |All 1 assertions passed |Passed |
| Supervision |test_UT_SUP_13_U |Supervisor 7 accesses internship assigned to 7 |Access returns true |All 1 assertions passed |Passed |
| Attendance |test_UT_ATT_06_U |Completed one-hour break and incomplete break |60 minutes for completed break; 0 for incomplete break |All 2 assertions passed |Passed |
| Evaluation |test_UT_EVAL_04_WEIGHTED |FO-24 first criterion 100; other nine criteria 80 |Weighted average 85.0; Good |All 2 assertions passed |Passed |
| Portfolio |test_UT_PORT_08_U |FO-24 record with no submitted_at then a stored timestamp |Pending before submission; completed after timestamp; form code preserved |All 5 assertions passed |Passed |
| Portfolio |test_UT_PORT_06_U |Approved weekly record with stored activities |Preserves approved status, week 2, dates and stored activities |All 4 assertions passed |Passed |
| Administration |test_UT_ADM_03_U |Attempt unsupported staff role student |role validation error before external lookup or database writes |All 1 assertions passed |Passed |
| Authentication |test_lockout_message_matches_retry_after_header |lockout message matches retry after header |All assertions in the named test pass |All 6 assertions passed |Passed |
| Attendance |test_eight_am_is_am_and_one_oh_three_pm_is_pm |eight am is am and one oh three pm is pm |08:00, 11:59, noon, 13:03 classified into correct AM/PM periods |All 4 assertions passed |Passed |
| Attendance |test_early_morning_utc_clock_stays_on_the_manila_attendance_date |early morning utc clock stays on the manila attendance date |All assertions in the named test pass |All 2 assertions passed |Passed |
| Attendance |test_afternoon_only_leaves_am_blank_not_absent |afternoon only leaves am blank not absent |13:01â€“13:03 leaves AM blank and no absence flag |All 6 assertions passed |Passed |
| Attendance |test_pm_clock_out_never_appears_under_am_time_out |pm clock out never appears under am time out |17:00 clock out appears only in PM |All 4 assertions passed |Passed |
| Attendance |test_normal_full_day_with_break_resume |normal full day with break resume |08:02/11:58 and 13:01/17:04 map to correct columns |All 4 assertions passed |Passed |
| Attendance |test_morning_only_does_not_fabricate_pm |morning only does not fabricate pm |08:00â€“11:30 has no PM entries |All 4 assertions passed |Passed |
| Attendance |test_cross_noon_without_break_does_not_fabricate_lunch |cross noon without break does not fabricate lunch |11:30â€“13:30 has no fabricated lunch boundaries |All 4 assertions passed |Passed |
| Attendance |test_late_morning_clock_in_is_not_am_absent |late morning clock in is not am absent |11:00 is not AM absent |All 2 assertions passed |Passed |
| Attendance |test_exact_noon_clock_in_is_pm_not_day_absent |exact noon clock in is pm not day absent |12:00 classified PM with no absence |All 4 assertions passed |Passed |
| Attendance |test_hours_helper_unchanged_for_canonical_credit_math |hours helper unchanged for canonical credit math |08:00â€“12:00 plus 13:00â€“17:00 credits 8.0 hours |All 1 assertions passed |Passed |
