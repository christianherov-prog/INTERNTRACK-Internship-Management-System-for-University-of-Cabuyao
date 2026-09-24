**Changes made during this integration pass**

Added:

- tests/Feature/ChapterIVIntegration/WorkflowIntegrationTest.php — 24 cross-module tests with dedicated fixtures, RefreshDatabase transactions, fake storage/mail, frozen UTC instants representing Manila wall-clock time, and downstream observation capture.
- tests/integration-bootstrap.php — reuses the actual server/database/migration guard, then rebuilds the verified disposable schema once per selection process.
- tests/run-integration-evidence.php — executes the exact 33-case manifest, archives earlier outputs, and records executable arguments and exit code.
- tests/build-integration-report.php — derives statuses/counts/assertions from JUnit, rejects missing cases and zero-assertion passes, and generates Markdown/CSV/JSON.
- tests/integration-evidence/ — manifest, baseline and retest logs/JUnit, observed payloads, reports, failure classifications, commands and limitations.

Reused nine existing workflow methods without modifying their files: DtrWorkflowTest; PortfolioDataIntegrationTest; SupervisorInviteFlowTest; MessagingFlowTest. Exact methods are listed in cases.json and CASE-DETAILS.md.

No existing test file was edited during this integration pass. The unit-testing changes already visible in Git status belong to the earlier pass. No production file was edited. Existing production changes in UserResource.php, AuthService.php, bootstrap/app.php and frontend LoginPage.jsx, plus untracked root index.html/report.json, were preserved. No commit or push.

