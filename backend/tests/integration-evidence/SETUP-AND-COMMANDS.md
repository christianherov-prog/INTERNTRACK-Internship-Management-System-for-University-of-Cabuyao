**Setup and executed commands**

Run PHP commands from backend. The branch was Internet-Develop. No packages or frontend test ecosystem were installed.

The existing disposable data directory from the previous unit pass was reused. Server startup used:
~~~powershell
Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--no-defaults','--basedir=C:/xampp/mysql','--datadir="D:/Clarence/System Thesis/interntrack-unit-mysql-20260920"','--port=33307','--bind-address=127.0.0.1','--innodb-file-per-table=0','--log-error="D:/Clarence/System Thesis/interntrack-unit-mysql-20260920/integration-server.log"' -WindowStyle Hidden

& C:/xampp/mysql/bin/mysql.exe --no-defaults --host=127.0.0.1 --port=33307 --user=root --batch --raw --execute='SELECT @@datadir, @@port; SELECT COUNT(*) AS migrations FROM interntrack_testing.migrations;'
~~~

The observed data directory/port and migration count are preserved in database-verification.txt. The normal XAMPP data directory is not this test directory. The process-local PATH adjustment makes mysql.exe available to Laravel's schema loader:
~~~powershell
$env:PATH='C:\xampp\mysql\bin;'+$env:PATH
~~~

Existing baseline selection, before new integration tests:
~~~powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter 'PortfolioDataIntegrationTest|ComplianceConsistencyAcrossRolesTest|DtrWorkflowTest|SupervisorInviteFlowTest|PlacementApprovalWorkflowTest|MessagingFlowTest|MeetingAclTest|AttendanceTodayStateRepairTest|JournalWorkflowTest|DocumentComplianceAndRequirementsRepairTest|FacultyRequirementSubmissionReviewTest|EvaluationPeriodGateTest|OfficialFormConsistencyTest|MisdMockSyncTest' --log-junit tests/integration-evidence/baseline.xml --colors=never
~~~

The first attempt inherited committed concurrency fixtures. The same selection was rerun with EVIDENCE_REBUILD=1 and output names baseline-clean.xml/baseline-clean.log:
~~~powershell
$env:EVIDENCE_REBUILD='1'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter 'PortfolioDataIntegrationTest|ComplianceConsistencyAcrossRolesTest|DtrWorkflowTest|SupervisorInviteFlowTest|PlacementApprovalWorkflowTest|MessagingFlowTest|MeetingAclTest|AttendanceTodayStateRepairTest|JournalWorkflowTest|DocumentComplianceAndRequirementsRepairTest|FacultyRequirementSubmissionReviewTest|EvaluationPeriodGateTest|OfficialFormConsistencyTest|MisdMockSyncTest' --log-junit tests/integration-evidence/baseline-clean.xml --colors=never
Remove-Item Env:EVIDENCE_REBUILD
~~~

This rebuild was authorized only after the bootstrap positively verified the disposable database/server. No normal development schema was reset. The clean baseline passed 105/105.

Focused development runs:
~~~powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php tests/Feature/ChapterIVIntegration --log-junit tests/integration-evidence/focused-first.xml --colors=never
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php tests/Feature/ChapterIVIntegration --log-junit tests/integration-evidence/focused-second.xml --colors=never
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php tests/Feature/ChapterIVIntegration --log-junit tests/integration-evidence/focused-third.xml --colors=never
~~~

Each console run was captured with PowerShell *> into its matching .log. The logs and JUnit retain original and retest outcomes; they are not added to the final selected count.

Final reproducible integration selection and report:
~~~powershell
$env:PATH='C:\xampp\mysql\bin;'+$env:PATH
php tests/run-integration-evidence.php
php tests/build-integration-report.php
~~~

run-integration-evidence.php invokes PHPUnit with:
- configuration phpunit.evidence.xml (testing, MySQL port 33307, interntrack_testing)
- bootstrap tests/integration-bootstrap.php (guarded disposable rebuild once, followed by per-test transactions)
- exact class::method filter for all 33 cases from cases.json
- log-junit tests/integration-evidence/final.xml
- colors=never

[command-final.json](command-final.json) records the actual executable and full argument array, including the exact 33-method filter. [exit-final.json](exit-final.json) records exit 1 from the eight retained business failures. An earlier bootstrap refusal is separately archived; no case executed during it.

Per-case isolated rerun, substituting the exact method from CASE-DETAILS.md:
~~~powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/integration-bootstrap.php --filter test_INT_04_TZ_scheduled_afternoon_hours_propagate --log-junit tests/integration-evidence/recheck.xml --colors=never
~~~
This example is a reproduction command; the recorded final run executed the complete selection. Rechecks should use distinct output names and must not replace final evidence silently.

Syntax/report/repository checks:
~~~powershell
php -l tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php
php -l tests/integration-bootstrap.php
php -l tests/run-integration-evidence.php
php -l tests/build-integration-report.php
git -c safe.directory='D:/Clarence/System Thesis/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao' diff --check
~~~

The disposable server is stopped at the end after checking @@datadir with mysql --batch --raw, normalizing slashes, and requiring exact equality to D:/Clarence/System Thesis/interntrack-unit-mysql-20260920. Only then:
~~~powershell
& C:/xampp/mysql/bin/mysqladmin.exe --no-defaults --host=127.0.0.1 --port=33307 --user=root shutdown
~~~
See test-server-shutdown.txt. To reproduce later, restart this disposable server first. Test data-directory files were retained; no live server on port 3306 was stopped.

Final repository commands (from repository root), with process-local safe.directory because sandbox and repository owners differ:
~~~powershell
git -c safe.directory='D:/Clarence/System Thesis/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao' branch --show-current
git -c safe.directory='D:/Clarence/System Thesis/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao' status
~~~
Output is in git-final.txt. No global Git configuration changes, commits or pushes.

