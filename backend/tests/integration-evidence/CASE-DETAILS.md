**INT-01 — Passed**

Setup / authoritative records: Five active role accounts; authoritative Student internship.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_01_login_role_workspace_and_api_scope

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 37. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_01_login_role_workspace_and_api_scope$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_01_login_role_workspace_and_api_scope.json).

**INT-02 — Passed**

Setup / authoritative records: Linked Student/program/department, Faculty, HTE, Supervisor and current placement IDs.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_02_profile_internship_and_placement_ids

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 16. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_02_profile_internship_and_placement_ids$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_02_profile_internship_and_placement_ids.json).

**INT-03 — Passed**

Setup / authoritative records: Existing account and profile; pending invite; current placement without Supervisor.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_03_existing_supervisor_approval_placement_and_access

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 20. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_03_existing_supervisor_approval_placement_and_access$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_03_existing_supervisor_approval_placement_and_access.json).

**INT-04 — Passed**

Setup / authoritative records: Active internship; frozen Manila time; no schedule override.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_04_clock_break_validation_and_progress

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 19. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_04_clock_break_validation_and_progress$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_04_clock_break_validation_and_progress.json).

**INT-04-CORRECTION — Passed**

Setup / authoritative records: Historical clock-in record and a correction request.

Exact test file: tests/Feature/DtrWorkflowTest.php

Exact method: Tests\Feature\DtrWorkflowTest::test_correction_requires_supervisor_then_faculty_and_does_not_edit_until_both_approve

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 16. Individual filter: Tests\Feature\DtrWorkflowTest::test_correction_requires_supervisor_then_faculty_and_does_not_edit_until_both_approve$ (same configuration and guarded bootstrap).

**INT-04-TZ — Failed**

Setup / authoritative records: Approved work schedule and real clock actions at UTC instants corresponding to Manila times.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_04_TZ_scheduled_afternoon_hours_propagate

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 14. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_04_TZ_scheduled_afternoon_hours_propagate$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_04_TZ_scheduled_afternoon_hours_propagate.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_04_TZ_scheduled_afternoon_hours_propagate
{"app_timezone":"UTC","schedule":{"start_time":"08:00:00","end_time":"17:00:00","effective_from":"2026-09-18T00:00:00.000000Z","status":"approved"},"log":{"clock_in":"05:00:00","clock_out":"09:00:00","hours_rendered":"1.00","status":"validated"},"dashboard_hours":1,"faculty_hours":1,"fo30":{"id":4,"date":"2026-09-18","timezone":"Asia\/Manila","am_time_in":null,"am_time_out":null,"pm_time_in":"13:00","pm_time_out":"17:00","am_absent":false,"day_absent":false,"hours_rendered":1,"status":"validated","validated":true,"validated_at":"2026-09-18T17:00:00+08:00","hte_signature_path":null,"student_signature_path":null,"hte_signed_name":"Industry Reviewer","student_signed_name":"STUDENT, TEST"}}
Failed asserting that 1 matches expected 4.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:438
~~~

**INT-05 — Passed**

Setup / authoritative records: Real API session; Supervisor signature test asset.

Exact test file: tests/Feature/PortfolioDataIntegrationTest.php

Exact method: Tests\Feature\PortfolioDataIntegrationTest::test_fo30_uses_same_attendance_in_asia_manila_without_inventing_sessions

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 21. Individual filter: Tests\Feature\PortfolioDataIntegrationTest::test_fo30_uses_same_attendance_in_asia_manila_without_inventing_sessions$ (same configuration and guarded bootstrap).

**INT-06 — Failed**

Setup / authoritative records: Approved 08:00–17:00 schedule; internship starts Sep 18; initially no attendance.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_06_schedule_absence_afternoon_and_history

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 23. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_06_schedule_absence_afternoon_and_history$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_06_schedule_absence_afternoon_and_history.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_06_schedule_absence_afternoon_and_history
Completed first workday absent from FO-30 when no historical attendance exists.
Failed asserting that null is not null.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:220
~~~

**INT-07 — Passed**

Setup / authoritative records: Submitted journal linked to assigned Faculty and Student.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_07_journal_review_history_and_denials

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 11. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_07_journal_review_history_and_denials$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_07_journal_review_history_and_denials.json).

**INT-07-COORD — Failed**

Setup / authoritative records: Coordinator-as-Faculty assignment; submitted journal.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_07_COORD_coordinator_review_propagation

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 7. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_07_COORD_coordinator_review_propagation$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_07_COORD_coordinator_review_propagation.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_07_COORD_coordinator_review_propagation
{"http":200,"database":{"status":"approved","faculty_reviewed_by":35},"student":{"id":2,"internship_id":11,"entry_number":1,"week_number":1,"date":"2026-09-07","end_date":"2026-09-11","activities_summary":"Integration journal 2026-09-07","learnings":"Authoritative IDs","challenges":"Mapping","status":"approved","score":null,"file_path":null,"notes":null,"supervisor_feedback":null,"supervisor_reviewed_by":null,"supervisor_reviewed_at":null,"faculty_feedback":null,"faculty_reviewed_by":35,"faculty_reviewed_at":"2026-09-18T00:00:00.000000Z","created_at":"2026-09-18T00:00:00.000000Z","updated_at":"2026-09-18T00:00:00.000000Z","deleted_at":null,"editable":false,"lock_reason":"Approved journals cannot be edited.","student_name":"Student, Test","program":"Bachelor of Science in Information Technology","company_name":"Integration HTE","company_logo_path":null,"student_signature_path":null},"fo31":{"id":2,"week_number":1,"week":1,"date":"2026-09-07","end_date":"2026-09-11","activities_summary":"Integration journal 2026-09-07","accomplishment":"Integration journal 2026-09-07","challenges":"Mapping","difficulties":"Mapping","learnings":"Authoritative IDs","insights":"Authoritative IDs","file_path":null,"status":"approved"},"portfolio":{"id":2,"week_number":1,"week":1,"date":"2026-09-07","end_date":"2026-09-11","activities_summary":"Integration journal 2026-09-07","accomplishment":"Integration journal 2026-09-07","challenges":"Mapping","difficulties":"Mapping","learnings":"Authoritative IDs","insights":"Authoritative IDs","file_path":null,"status":"approved"}}
Failed asserting that 200 is identical to 403.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:253
~~~

**INT-08 — Passed**

Setup / authoritative records: Real submitted/reviewed journal for Sep 7–11.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_08_approved_journal_to_fo31

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 14. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_08_approved_journal_to_fo31$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_08_approved_journal_to_fo31.json).

**INT-09 — Failed**

Setup / authoritative records: Two nonoverlapping journal records; one approved through API.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_09_portfolio_excludes_unapproved_journals

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 8. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_09_portfolio_excludes_unapproved_journals$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_09_portfolio_excludes_unapproved_journals.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_09_portfolio_excludes_unapproved_journals
Unapproved academic journal leaked into portfolio: [{"id":4,"week_number":1,"week":1,"date":"2026-09-07","end_date":"2026-09-11","activities_summary":"Integration journal 2026-09-07","accomplishment":"Integration journal 2026-09-07","challenges":"Mapping","difficulties":"Mapping","learnings":"Authoritative IDs","insights":"Authoritative IDs","file_path":null,"status":"approved"},{"id":5,"week_number":2,"week":2,"date":"2026-09-14","end_date":"2026-09-18","activities_summary":"Integration journal 2026-09-14","accomplishment":"Integration journal 2026-09-14","challenges":"Mapping","difficulties":"Mapping","learnings":"Authoritative IDs","insights":"Authoritative IDs","file_path":null,"status":"submitted"}]
Failed asserting that true is false.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:276
~~~

**INT-10 — Passed**

Setup / authoritative records: One active upload template and stored PDF attachment.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_10_upload_approval_all_compliance_views

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 35. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_10_upload_approval_all_compliance_views$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_10_upload_approval_all_compliance_views.json).

**INT-11 — Passed**

Setup / authoritative records: Four active templates; three persisted documents in different states.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_mixed_status_report_denominator

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 19. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_mixed_status_report_denominator$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_11_mixed_status_report_denominator.json).

**INT-11-COMPLETE — Passed**

Setup / authoritative records: Two active templates, actual uploads and Faculty review actions.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_COMPLETE_approval_updates_complete_label

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 26. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_COMPLETE_approval_updates_complete_label$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_11_COMPLETE_approval_updates_complete_label.json).

**INT-11-SCOPE — Failed**

Setup / authoritative records: Host Evaluation template; Faculty-opened period; Supervisor FO-24 submission.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_SCOPE_fo24_does_not_satisfy_host_evaluation

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 13. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_SCOPE_fo24_does_not_satisfy_host_evaluation$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_11_SCOPE_fo24_does_not_satisfy_host_evaluation.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_11_SCOPE_fo24_does_not_satisfy_host_evaluation
student incorrectly satisfied Host Evaluation; {"student":{"approved":1,"total":1,"pct":100},"student_documents":{"approved":1,"total":1,"pct":100},"faculty":{"approved":1,"total":1,"pct":100},"faculty_report":{"approved":1,"total":1,"pct":100},"coordinator":{"approved":1,"total":1,"pct":100},"coordinator_report":{"approved":1,"total":1,"pct":100}}
Failed asserting that 1 is identical to 0.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:308
~~~

**INT-12-COORD — Failed**

Setup / authoritative records: Registered invite for existing Supervisor; internship initially has no Supervisor.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_12_COORD_coordinator_approval_access_propagation

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 5. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_12_COORD_coordinator_approval_access_propagation$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_12_COORD_coordinator_approval_access_propagation.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_12_COORD_coordinator_approval_access_propagation
{"http":200,"invite_status":"approved","assigned_supervisor":61,"supervisor_list":{"data":[{"id":17,"term":"AY 2024-2025, Sem 2","status":"active","status_label":"Active","status_reason":null,"target_hours":360,"total_hours_rendered":0,"remaining_hours":360,"progress_pct":0,"company":{"id":12,"company_name":"Integration HTE","company_logo_path":null},"student":{"id":60,"username":"2061-71933","student_profile":{"first_name":"Test","last_name":"Student","student_number":"2061-71933","course_name":"Bachelor of Science in Information Technology","program":"Bachelor of Science in Information Technology"}},"supervisor_name":"REVIEWER, INDUSTRY","student_signature_path":null,"supervisor_signature_path":null,"attendance_logs":[],"evaluation_eligibility":{"progress_pct":0,"hours_rendered":0,"target_hours":360,"remaining_hours":360,"midterm_eligible":false,"final_eligible":false,"status":"not_yet_eligible","label":"Not Yet Eligible","reason":"Not yet eligible for midterm evaluation (0 \/ 360 hours = 0%; 50% required)."}}],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}},"student":{"id":17,"term":"AY 2024-2025, Sem 2","status":"active","status_label":"Active","status_reason":null,"company_name":"Integration HTE","supervisor_name":"Industry Reviewer","supervisor_faculty_number":"SUPERVISOR-4916","start_date":"2026-09-01","placements":[],"current_placement":null}}
Failed asserting that 200 is identical to 403.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:321
~~~

**INT-12-MAIL — Passed**

Setup / authoritative records: Two real invite registrations; fake mail transport.

Exact test file: tests/Feature/SupervisorInviteFlowTest.php

Exact method: Tests\Feature\SupervisorInviteFlowTest::test_approval_and_rejection_emails_go_to_supervisor_once

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 12. Individual filter: Tests\Feature\SupervisorInviteFlowTest::test_approval_and_rejection_emails_go_to_supervisor_once$ (same configuration and guarded bootstrap).

**INT-12-NEW — Passed**

Setup / authoritative records: New Supervisor registration via Student invite, PDF upload.

Exact test file: tests/Feature/SupervisorInviteFlowTest.php

Exact method: Tests\Feature\SupervisorInviteFlowTest::test_new_supervisor_registers_via_invite_and_faculty_approves

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 16. Individual filter: Tests\Feature\SupervisorInviteFlowTest::test_new_supervisor_registers_via_invite_and_faculty_approves$ (same configuration and guarded bootstrap).

**INT-12-REJECT — Passed**

Setup / authoritative records: Student invite and valid Acceptance Form registration.

Exact test file: tests/Feature/SupervisorInviteFlowTest.php

Exact method: Tests\Feature\SupervisorInviteFlowTest::test_rejection_requires_remarks

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 7. Individual filter: Tests\Feature\SupervisorInviteFlowTest::test_rejection_requires_remarks$ (same configuration and guarded bootstrap).

**INT-13 — Passed**

Setup / authoritative records: Actual 08:00–12:00 session.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_13_validation_authorization_fo30_and_monitoring

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 14. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_13_validation_authorization_fo30_and_monitoring$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_13_validation_authorization_fo30_and_monitoring.json).

**INT-14 — Passed**

Setup / authoritative records: Assigned evaluator, internship and all ten ratings of 80.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_14_evaluation_submission_student_record

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 13. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_14_evaluation_submission_student_record$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_14_evaluation_submission_student_record.json).

**INT-15-FORMS — Passed**

Setup / authoritative records: Approved period; Student/Supervisor/Faculty API submissions; signature fixtures.

Exact test file: tests/Feature/PortfolioDataIntegrationTest.php

Exact method: Tests\Feature\PortfolioDataIntegrationTest::test_evaluations_fo22_fo23_fo24_and_fo03_without_faculty_eval

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 26. Individual filter: Tests\Feature\PortfolioDataIntegrationTest::test_evaluations_fo22_fo23_fo24_and_fo03_without_faculty_eval$ (same configuration and guarded bootstrap).

**INT-15-PENDING — Passed**

Setup / authoritative records: Persisted FO-24 draft with null submitted_at.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_15_pending_evaluation_not_completed

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 6. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_15_pending_evaluation_not_completed$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_15_pending_evaluation_not_completed.json).

**INT-16-ARCHIVE — Passed**

Setup / authoritative records: Persisted Coordinator-to-Student message.

Exact test file: tests/Feature/MessagingFlowTest.php

Exact method: Tests\Feature\MessagingFlowTest::test_archive_is_per_user_and_reversible

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 12. Individual filter: Tests\Feature\MessagingFlowTest::test_archive_is_per_user_and_reversible$ (same configuration and guarded bootstrap).

**INT-16-DENY — Passed**

Setup / authoritative records: Student/Faculty private message and unrelated Student/Coordinator.

Exact test file: tests/Feature/MessagingFlowTest.php

Exact method: Tests\Feature\MessagingFlowTest::test_outsider_cannot_send_or_read_thread

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 3. Individual filter: Tests\Feature\MessagingFlowTest::test_outsider_cannot_send_or_read_thread$ (same configuration and guarded bootstrap).

**INT-16-SEND — Passed**

Setup / authoritative records: Four authoritative internship participants.

Exact test file: tests/Feature/MessagingFlowTest.php

Exact method: Tests\Feature\MessagingFlowTest::test_participants_can_send_and_read_messages

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 13. Individual filter: Tests\Feature\MessagingFlowTest::test_participants_can_send_and_read_messages$ (same configuration and guarded bootstrap).

**INT-17 — Passed**

Setup / authoritative records: Assigned internship participants; supported check_in type.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_17_authorized_meeting_student_view

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 10. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_17_authorized_meeting_student_view$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_17_authorized_meeting_student_view.json).

**INT-17-ATOMIC — Failed**

Setup / authoritative records: Same-department Coordinator outside authoritative assignment.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_17_ATOMIC_denied_meeting_downstream_visibility

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 6. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_17_ATOMIC_denied_meeting_downstream_visibility$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_17_ATOMIC_denied_meeting_downstream_visibility.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_17_ATOMIC_denied_meeting_downstream_visibility
{"http":403,"persisted_count":1,"attendees":0,"creator_visible":true,"student_visible":false,"new_notifications":0}
Failed asserting that 1 is identical to 0.

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:474
~~~

**INT-18 — Passed**

Setup / authoritative records: Student preferences toggled; unrelated user.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_18_action_notification_preferences_and_read_ownership

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 13. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_18_action_notification_preferences_and_read_ownership$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_18_action_notification_preferences_and_read_ownership.json).

**INT-19 — Passed**

Setup / authoritative records: Approved journal; submitted FO-24; validated 4-hour record; logo/text; second Student journal.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_19_broad_portfolio_authoritative_sources

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 18. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_19_broad_portfolio_authoritative_sources$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_19_broad_portfolio_authoritative_sources.json).

**INT-20 — Failed**

Setup / authoritative records: 8 validated hours with stored internship total 0; approved journal/document; submitted FO-24 score 80.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_20_reports_match_authoritative_sources

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 21. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_20_reports_match_authoritative_sources$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_20_reports_match_authoritative_sources.json).

Failure/skip output:

~~~text
Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_20_reports_match_authoritative_sources
Reports disagree with validated attendance: {"faculty":{"summary":{"student_name":"Test Student","student_number":"2061-82263","program":"Bachelor of Science in Information Technology","company":"Integration HTE","status":"active","hours_rendered":8,"target_hours":360,"progress_pct":2.2,"validated_days":1,"approved_journals":1,"approved_docs":1,"required_docs":1,"compliance_pct":100,"start_date":"2026-09-01","end_date":null,"final_grade":null},"performance":{"by_program":[{"program":"Bachelor of Science in Information Technology","total":1,"completed":0,"avg_hours":0,"avg_grade":0}],"eval_averages":[{"evaluator_type":"supervisor","avg_overall":"80.000000","signature_url":null}],"generated_at":"2026-09-18 00:00:00"}},"coordinator":{"summary":{"student_name":"Student, Test","student_number":"2061-82263","program":"Bachelor of Science in Information Technology","company":"Integration HTE","industry":"\u2014","status":"active","hours_rendered":0,"target_hours":360,"progress_pct":0,"validated_days":1,"approved_journals":1,"approved_docs":1,"start_date":"2026-09-01","end_date":null,"final_grade":null},"performance":{"by_program":[{"program":"Bachelor of Science in Information Technology","total":1,"completed":0,"avg_hours":0,"avg_grade":0}],"eval_averages":[{"evaluator_type":"supervisor","avg_overall":"80.000000","signature_url":null}],"filters":{"programs":[{"id":28,"name":"Bachelor of Science in Information Technology"}],"industries":[]},"applied":{"program":null,"industry":null},"generated_at":"2026-09-18 00:00:00"}}}
Failed asserting that two arrays are identical.
--- Expected
+++ Actual
@@ @@
-Array &0 []
+Array &0 [
+    0 => 'faculty performance hours',
+    1 => 'coordinator summary hours',
+    2 => 'coordinator performance hours',
+]

D:\Clarence\System Thesis\INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao\backend\tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest.php:542
~~~

**INT-21 — Passed**

Setup / authoritative records: Admin, Faculty and Student accounts.

Exact test file: tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php

Exact method: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_21_admin_account_change_login_and_audit

Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: 15. Individual filter: Tests\Feature\ChapterIVIntegration\WorkflowIntegrationTest::test_INT_21_admin_account_change_login_and_audit$ (same configuration and guarded bootstrap).

Captured downstream values: [observation JSON](observations/test_INT_21_admin_account_change_login_and_audit.json).

