**Chapter IV — Results of Integration Testing**

Branch tested: `Internet-Develop`, current working tree. Pre-existing production edits were preserved. This pass changed no production files and performed no commit or push.

Environment: Windows PowerShell; PHP 8.5.8; PHPUnit 11.5.56; Laravel 12; MariaDB 10.4.32/MySQL driver. Frontend: React 18/Vite 6. Tested interfaces are Laravel HTTP APIs plus real database/service/data-builder interactions, not browser automation or isolated helper calls.

Database: `interntrack_testing` at `127.0.0.1:33307`, actual data directory `D:/Clarence/System Thesis/interntrack-unit-mysql-20260920`. The bootstrap verifies APP_ENV=testing, database, server data directory and migrations before allowing a schema rebuild. The integration bootstrap rebuilds this verified disposable schema once per selected-run process, then RefreshDatabase rolls back each case. Migrations seed some academic accounts, so a clean migrated database is not empty. Normal development database/server was not reset or reconfigured. The verified schema has 101 migrations. New tests fake mail/storage, use synchronous queues, null broadcasting and mocked MISD. No live email, realtime delivery or external MISD availability is claimed.

**Existing baseline**

Before adding focused integration tests, the clean selected existing feature/integration suite executed **105 tests: 105 passed, 0 failed, 0 skipped, 0 errors; 836 assertions**. See [baseline JUnit](baseline-clean.xml) and [output](baseline-clean.log). That baseline includes component/static checks and is NOT relabelled as 105 integration scenarios. The earlier contaminated-fixture attempt is preserved in [run history](RUN-HISTORY.md).

**Selection and interpretation**

Table 4 reports **33 distinct integration cases across all 21 requested areas**: 24 new cross-module tests and 9 reused existing workflows. Suffixes represent separate subscenarios; INT-01 exercises five representative roles within one case. Repeated runs, unit cases and concurrency tests are not added. Every Passed result requires a matching JUnit case with executed assertions. A failed case may contain successful earlier assertions; it remains Failed. Downstream observations are captured before asserting known-denial expectations, so failure impact remains reviewable.

Inspection covered relevant routes/middleware, controllers, domain models/services, schema/migrations, factories/fixtures, notifications/events/mail and previous Chapter IV findings. Authoritative links include Student profile/program/department; internship participant/company IDs; current placement and program HTE requirement; internship attendance/journals/documents/evaluations; document attachments; invite-linked Supervisor accounts; meeting attendees; notification recipients and message participants. No assignment is selected by unrelated first account or display-name fallback. Prepared source records are test fixtures in the disposable database, not production/demo screenshots used as proof.

