**Integration failure analysis**

These are requirement-based assertions against executed workflows. Production behavior was not changed. Coordinator cases are classified as workflow conflicts because the current source intentionally permits the role; they are not silently relabelled as passes.

**INT-04-TZ — Data consistency defect**

- Observed: Expected 4 hours; stored log, Student dashboard, Faculty progress and FO-30 all returned 1 hour, despite displaying 13:00–17:00.
- Root cause: DtrWorkflowService::finalizeClockOut passes both UTC attendance times and Manila schedule times through combineDateAndTime, which parses using app timezone UTC. The 05:00–09:00 UTC session overlaps the incorrectly interpreted 08:00–17:00 UTC schedule for only one hour.
- Source: app/Services/DtrWorkflowService.php (finalizeClockOut, combineDateAndTime); app/Services/AttendanceDayResolver.php (scheduleEndAt uses Manila explicitly)
- Downstream effect: Incorrect credited hours propagate after validation into progress and official forms.
- Recommended correction: Interpret schedule wall-clock times in Asia/Manila, convert to the same instant basis as stored attendance, and regression-test morning/afternoon/break boundaries.
- Production changed: No. Retest after production correction: not performed.

**INT-06 — Data consistency defect**

- Observed: After 17:01 on the first scheduled workday, FO-30 contained no absence row. Afternoon presence and subsequent not_clocked_in state passed their assertions.
- Root cause: AttendanceDayResolver::mergeFo30Attendance returns an empty array when there are no serialized attendance dates; it also starts at the first recorded date, not the internship/schedule start.
- Source: app/Services/AttendanceDayResolver.php; app/Services/OfficialFormDataService.php; app/Services/PortfolioDataService.php
- Downstream effect: A fully missed first scheduled day is omitted from official-form data even though schedule/day logic can determine absence. The test does not claim a dashboard absence counter exists.
- Recommended correction: Build the expected workday range from authoritative internship and approved-schedule dates even with zero attendance rows; derive absence only after shift end.
- Production changed: No. Retest after production correction: not performed.

**INT-07-COORD — Workflow/business-rule conflict**

- Observed: Coordinator received 200; journal became approved with Coordinator reviewer ID. Student history, FO-31 and portfolio all returned that approved journal.
- Root cause: Faculty route group permits faculty,coordinator; reviewJournal enforces assignment/department, not exclusive Faculty role. Current source intentionally supports a Coordinator Faculty workspace.
- Source: routes/api.php; app/Http/Controllers/Api/FacultyController.php (reviewJournal)
- Downstream effect: Coordinator review propagates as authoritative approved content across all three downstream surfaces.
- Recommended correction: Resolve the final role policy. If Faculty-only is authoritative, restrict review to Faculty before mutation; otherwise revise the documented requirement explicitly.
- Production changed: No. Retest after production correction: not performed.

**INT-09 — Data consistency defect**

- Observed: Portfolio returned the approved journal once AND the second journal with status submitted.
- Root cause: PortfolioDataService::payload queries academic journals for the internship without an approved-status filter.
- Source: app/Services/PortfolioDataService.php (payload); app/Models/JournalEntry.php (academic scope)
- Downstream effect: Unapproved journal content appears in the portfolio payload. No claim is made about whether the browser subsequently filters it.
- Recommended correction: Apply the agreed approved-only rule at the authoritative portfolio query; preserve separate submission history and test both paths.
- Production changed: No. Retest after production correction: not performed.

**INT-11-SCOPE — Data consistency defect**

- Observed: Only FO-24 was submitted, but Host Evaluation showed 1/1 approved and 100% on Student dashboard/documents, Faculty and Coordinator progress, and both compliance reports.
- Root cause: DocumentComplianceService::systemGeneratedSatisfaction uses any submitted internship evaluation for both performance_evaluation and host_evaluation, without form_type/evaluator_type constraints.
- Source: app/Services/DocumentComplianceService.php (systemGeneratedSatisfaction)
- Downstream effect: The same incorrect form match propagates consistently to six surfaces; agreement does not establish correctness.
- Recommended correction: Map each evaluation requirement to its applicable official form and evaluator and constrain the query before counting satisfaction.
- Production changed: No. Retest after production correction: not performed.

**INT-12-COORD — Workflow/business-rule conflict**

- Observed: Coordinator received 200; invite became approved, internship Supervisor ID was assigned, Supervisor saw the intern and Student dashboard displayed that Supervisor.
- Root cause: SupervisorRegistrationController::assertFacultyMayReview explicitly permits a same-department Coordinator, and Faculty middleware admits Coordinator.
- Source: app/Http/Controllers/Api/SupervisorRegistrationController.php (assertFacultyMayReview, approve); routes/api.php
- Downstream effect: Coordinator approval activates assignment and downstream access under current code, contrary to the supplied Faculty-only expectation.
- Recommended correction: Resolve the final role policy; if Faculty-only is authoritative, enforce it before account/assignment/notification changes.
- Production changed: No. Retest after production correction: not performed.

**INT-17-ATOMIC — Transaction/atomicity defect**

- Observed: Unauthorized Coordinator received 403, but one meeting row persisted and appeared in the creator list. It had zero attendees, no Student visibility and zero new notifications.
- Root cause: MeetingController::store calls Meeting::create before checking internship ownership/attendee authority; rejection occurs before attendee/notification creation and there is no encompassing rollback.
- Source: app/Http/Controllers/Api/MeetingController.php (store, index)
- Downstream effect: Denied write leaves an orphan scheduled meeting visible to its creator. Student/notification propagation was tested and was absent.
- Recommended correction: Authorize and validate audience before insertion; create meeting and attendees transactionally and dispatch notifications after success.
- Production changed: No. Retest after production correction: not performed.

**INT-20 — Data consistency defect**

- Observed: For 8 validated source hours and stored internship total 0, Faculty summary returned 8, but Coordinator summary and both performance reports returned 0. Checked company, approved journal/document counts and evaluation average 80 matched.
- Root cause: Coordinator reportStudentSummary reads total_hours_rendered directly. Both reportPerformance implementations average the stored field, while Faculty summary uses InternshipProgressService::snapshot.
- Source: app/Http/Controllers/Api/CoordinatorController.php (reportStudentSummary, reportPerformance); app/Http/Controllers/Api/FacultyController.php (reportStudentSummary, reportPerformance)
- Downstream effect: Report outputs disagree for the same source records whenever stored totals lag authoritative attendance. Coordinator summary also lacks the applicable-requirement denominator field supplied by Faculty summary.
- Recommended correction: Use a shared authoritative progress aggregation for all report types; reconcile raw document counts with applicable compliance summaries rather than relying on stale cached totals.
- Production changed: No. Retest after production correction: not performed.

**Distinct issue counts**

Six reproduced application defects: five data-consistency defects and one transaction/atomicity defect. The atomicity defect is also an unauthorized-persistence defect (one authorization-impact issue, overlapping the six, not a seventh defect). Two additional workflow/business-rule conflicts concern Coordinator authority. Total distinct reported issues: eight. No concurrent load was run in this selection; the previous clock-in deadlock is not added to integration totals.

Exact failed methods, assertions and failure output are in final.xml/final.log and RESULTS.json. Expected behavior and integrated modules are in cases.json. Observations are captured before transaction rollback, including for failing tests.

