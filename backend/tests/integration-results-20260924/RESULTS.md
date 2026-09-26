# Integration testing results - September 24, 2026

Branch: Internet-Develop, base commit c927209 plus the local unit-test isolation change and configurable integration observation output. No production code changed.

Executed the existing 33-case integration selection across 21 areas using PHP 8.5.8 and PHPUnit 11.5.56. Totals: {"tests":33,"passed":33,"failed":0,"errors":0,"skipped":0,"assertions":525}. These totals come from this run's JUnit XML; historical outcomes are not reused.

Database: interntrack_testing at 127.0.0.1:33307, in the dedicated interntrack-unit-mysql-20260920 directory. The existing bootstrap verifies the server data directory and migration state, rebuilds the disposable schema once, and rolls back test transactions.

Scope: Laravel HTTP APIs, services, and database interactions. Mail and storage are faked; queues are synchronous, broadcasting is disabled, and MISD is mocked. This is not browser testing or verification of live external services.

Evidence: [JUnit](integration.xml), [execution log](integration.log), [command/filter](command.txt), [database verification](database-verification.txt), [CSV table](test-cases.csv). Per-scenario observations are saved in observations/.

| Area | Tests | Passed | Failed | Errors | Skipped |
|---|---:|---:|---:|---:|---:|
| INT-01 | 1 | 1 | 0 | 0 | 0 |
| INT-02 | 1 | 1 | 0 | 0 | 0 |
| INT-03 | 1 | 1 | 0 | 0 | 0 |
| INT-04 | 3 | 3 | 0 | 0 | 0 |
| INT-05 | 1 | 1 | 0 | 0 | 0 |
| INT-06 | 1 | 1 | 0 | 0 | 0 |
| INT-07 | 2 | 2 | 0 | 0 | 0 |
| INT-08 | 1 | 1 | 0 | 0 | 0 |
| INT-09 | 1 | 1 | 0 | 0 | 0 |
| INT-10 | 1 | 1 | 0 | 0 | 0 |
| INT-11 | 3 | 3 | 0 | 0 | 0 |
| INT-12 | 4 | 4 | 0 | 0 | 0 |
| INT-13 | 1 | 1 | 0 | 0 | 0 |
| INT-14 | 1 | 1 | 0 | 0 | 0 |
| INT-15 | 2 | 2 | 0 | 0 | 0 |
| INT-16 | 3 | 3 | 0 | 0 | 0 |
| INT-17 | 2 | 2 | 0 | 0 | 0 |
| INT-18 | 1 | 1 | 0 | 0 | 0 |
| INT-19 | 1 | 1 | 0 | 0 | 0 |
| INT-20 | 1 | 1 | 0 | 0 | 0 |
| INT-21 | 1 | 1 | 0 | 0 | 0 |
| **Total** | **33** | **33** | **0** | **0** | **0** |

## Scenario results

| Case | Integrated modules | Scenario | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| INT-01 | Authentication + role workspace | Authenticate Student, Faculty, Coordinator, Supervisor and Admin with real tokens; request workspace and forbidden API. | Correct account/role and dashboard; forbidden API returns 403. | All five roles authenticated, returned their dashboard/role and were denied the tested foreign role API. | Passed |
| INT-02 | Profile + internship + placement | Read Student dashboard, records, portfolio and Faculty progress for a current placement. | All payloads identify the same Student, program, internship and HTE. | Dashboard/current placement, records, portfolio identity and Faculty progress matched the linked records. | Passed |
| INT-03 | Placement + Supervisor assignment | Existing Supervisor accepts an invite; Faculty approves; read both workspaces and deny unrelated Supervisor. | No duplicate account; no assignment before approval; correct assignment and access afterward. | Existing account was reused; approval set both Supervisor IDs; Student/assigned Supervisor views matched and outsider form access was denied. | Passed |
| INT-04 | Attendance + rendered hours | Clock 08:00–17:00 with a recorded 12:00–13:00 break; validate and compare progress. | One daily session, 8 credited hours, duplicate actions denied, progress changes only after validation. | Same session credited 8 hours; duplicates were denied; Student, Faculty and Coordinator progress showed 8 after validation. | Passed |
| INT-04-CORRECTION | Attendance correction + Supervisor + Faculty + audit | Submit missing clock-out correction; require Supervisor then Faculty approval. | No edit before both approvals; original/applied values and multiple audit entries preserved. | Early Faculty action was rejected; record stayed unchanged until both approvals; original/applied times and audit entries persisted. | Passed |
| INT-04-TZ | Schedule + attendance + progress + FO-30 | Approve 08:00–17:00 Manila schedule; clock 13:00–17:00; validate and compare downstream hours. | Four credited hours in the log, dashboard, Faculty monitoring and FO-30. | Four hours propagate consistently. | Passed |
| INT-05 | Attendance + FO-30 + signature state | Clock 08:01–17:02, compare attendance and portfolio FO-30, then Supervisor validates. | Same dates/time entries/hours; no invented lunch; signature only after validation. | Attendance and FO-30 matched 08:01/17:02 and source hours; lunch fields stayed null; validation enabled the Supervisor signature. | Passed |
| INT-06 | Schedule + absence + attendance views | Check first workday before/after shift end, afternoon session on next workday and later historical state. | No early absence; first completed day shown absent; afternoon present; historical session not active today. | All schedule/day-state checks hold. | Passed |
| INT-07 | Journal + Faculty review + Student history | Submit weekly journal; deny outsider Faculty/Supervisor; assigned Faculty approves; Student rereads. | Only assigned Faculty review succeeds; reviewer/status persists and history updates. | Unrelated Faculty and Supervisor were denied; assigned Faculty approval/reviewer appeared in Student history. | Passed |
| INT-07-COORD | Journal + Student history + FO-31 + portfolio | Attempt review with Coordinator occupying faculty_id; inspect all downstream views before asserting denial. | Faculty-only rule denies action; journal remains submitted in all views. | Denied review does not propagate. | Passed |
| INT-08 | Approved journal + FO-31 | Approve journal, reject overlapping submission and retrieve official form bundle. | FO-31 has correct Student, week, dates, content/status and no overlapping record. | FO-31 matched the approved journal and Student; overlapping submission was rejected and exactly one journal remained. | Passed |
| INT-09 | Journal + portfolio | Read portfolio with one approved and one submitted journal. | Approved journal appears once; submitted journal is excluded. | Only approved journal is returned. | Passed |
| INT-10 | Upload + approval + compliance | Upload PDF and approve as Faculty; compare dashboard, documents, monitoring and reports. | 0 approved before review; 1/1 and 100% in all six surfaces after approval. | PDF attachment persisted; approval changed all six tested compliance surfaces to 1/1, 100%. | Passed |
| INT-11 | Compliance + role views + reports | Read authoritative Approved, Pending, Missing and Rejected requirement mix. | Applicable denominator 4; approved 1; 25% across all six surfaces; pending/rejected preserved. | Student, Faculty, Coordinator and reports agreed on 1/4, 25%; pending/rejected each counted once. | Passed |
| INT-11-COMPLETE | Document approval + complete status | Upload and approve both applicable requirements, then read all compliance surfaces. | 2/2 and 100%; Faculty/Coordinator label Complete. | All six compliance surfaces showed 2/2, 100%; both monitoring views returned Complete. | Passed |
| INT-11-SCOPE | Evaluation + compliance + reports | Submit only FO-24 and inspect Host Evaluation status across six surfaces. | Host Evaluation remains unsatisfied on every surface. | Unrelated form does not satisfy Host Evaluation. | Passed |
| INT-12-COORD | Supervisor approval + assignment + workspaces | Coordinator attempts approval; inspect assignment, Supervisor list and Student display. | Faculty-only rule rejects approval; assignment stays inactive. | Unauthorized approval cannot activate assignment. | Passed |
| INT-12-MAIL | Supervisor approval/rejection + email + assignment | Approve one registration and reject another; inspect faked mail and persisted assignment. | Correct recipients/messages; duplicate approval denied; no tested plaintext password; rejection does not assign. | Correct approval/rejection emails captured; repeat approval returned 404; approval notice count was one; tested password absent; rejected invite remained unassigned. | Passed |
| INT-12-NEW | Registration + Faculty approval + account assignment | Register with Acceptance Form; deny preapproval login; Faculty approves. | Form stored, inactive/unassigned before approval, active/correct assignment afterward. | PDF stored; preapproval login denied; Faculty approval activated the new account and correct internship assignment. | Passed |
| INT-12-REJECT | Supervisor registration + rejection validation | Register Supervisor then try rejection without remarks. | 422 with remarks error; registered state and unassigned internship preserved. | Rejection without remarks returned 422; invite stayed registered and internship stayed unassigned. | Passed |
| INT-13 | Supervisor validation + FO-30 + monitoring | Deny unrelated Supervisor then validate as assigned Supervisor and reread FO-30/progress. | Only assigned Supervisor validates; 4 hours and validated state reach official form/Faculty. | Unrelated Supervisor was denied; assigned validation produced 4 hours in FO-30 and Faculty monitoring. | Passed |
| INT-14 | Evaluation submission + Student record | Faculty opens evaluation period; Supervisor submits FO-24; Student retrieves record. | Submitted record keeps correct Student/internship, evaluator, form and score 80. | FO-24 persisted with correct evaluator/internship, submission timestamp and score 80; Student retrieved the same record. | Passed |
| INT-15-FORMS | Evaluation submission + portfolio official forms | Submit four official forms and Faculty evaluation; build Student portfolio. | Correct official forms/scores/comments/signatures; no removed faculty_eval. | FO-22/23/24/03 appeared completed with checked content/signatures; weighted FO-24 score was 85; faculty_eval was excluded. | Passed |
| INT-15-PENDING | Evaluation + portfolio status | Read portfolio for an unsubmitted FO-24. | Portfolio retains pending state and null submission date. | Portfolio returned pending and null submitted_at for the draft. | Passed |
| INT-16-ARCHIVE | Messaging + per-user archive views | Coordinator archives a conversation; Student checks own inbox; Coordinator restores. | Archive affects only actor and is reversible. | Coordinator thread moved to archived while Student copy stayed active; unarchive restored Coordinator active thread. | Passed |
| INT-16-DENY | Private messaging + authorization | Attempt outsider send/read against a persisted private thread. | Outsider send/read denied. | Unrelated Student send and unrelated Coordinator read both returned 403. | Passed |
| INT-16-SEND | Messaging + participants + conversation retrieval | Student sends to Faculty; Faculty reads; Coordinator replies; Student lists threads. | Messages persist in intended threads and read state updates. | Faculty retrieved Student message and read_at was set; Coordinator message appeared in Student conversations. | Passed |
| INT-17 | Appointment + Student view | Assigned Faculty schedules; Student reads; reject Student creation and reversed dates. | Appointment visible only after authorized create; invalid requests persist nothing. | Faculty meeting appeared in Student list; Student creation and reversed dates were rejected without those records. | Passed |
| INT-17-ATOMIC | Appointment + persistence + lists + notifications | Unrelated Coordinator creates appointment; inspect database, creator/Student lists and notifications. | 403 and no row, attendees, visibility or notification. | Denied request leaves no downstream record. | Passed |
| INT-18 | Appointment action + notifications + preferences | Create opted-out and opted-in invitations; test recipient/read ownership. | Opt-out suppresses; opt-in creates correct invitation; no outsider leakage; owner-only read. | Opt-out suppressed Student invitation; opt-in created the matching message; outsider received none and could not mark it read; owner could. | Passed |
| INT-19 | Profile + HTE + journals + attendance + evaluations + portfolio | Assemble broad portfolio after real journal/evaluation/text/image actions; check isolation. | Correct identity, HTE, source records and uploaded content without duplicate selected sections/foreign journal/faculty_eval. | Portfolio matched identity/HTE, approved journal, 4-hour source, completed FO-24, essay and one logo; foreign journal and faculty_eval were absent. | Passed |
| INT-20 | Reports + authoritative source modules | Compare Faculty/Coordinator summary and performance against journals, evaluation, documents and validated hours. | Reports agree with source hours, HTE, journal/document counts and evaluation score. | All report values match authoritative sources. | Passed |
| INT-21 | Admin account + authentication + authorization + audit | Deny Student staff update; Admin disables/reactivates Faculty; exercise login and role APIs. | Status persists, disabled login denied, reactivated role works, audit excludes secrets. | Unauthorized update was denied; deactivation blocked login; reactivation restored Faculty access; audit contained no tested password/token fields. | Passed |
