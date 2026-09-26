**Test-only changes**

Added:
- tests/Support/IsolatedTestCase.php: Laravel container with a database-query prohibition and frozen/reset time.
- tests/Unit/ChapterIV/{AUTH,PROFILE,PLACE,ATT,JRN,DOC,SUP,FDBK,EVAL,PORT,MSG,APP,NOTIF,RPT,ADM}UnitTest.php and SupplementalUnitTest.php: 53 new isolated cases.
- tests/Feature/ChapterIV/FocusedComponentsTest.php: 36 new focused component cases; fake local storage/mail and test-only fixtures.
- phpunit.evidence.xml: dedicated test-server port.
- tests/evidence-bootstrap.php: actual data-directory/database/environment/migration safety checks.
- tests/run-chapteriv.php and tests/build-chapteriv-report.php: reproducible module selections and JUnit-based reporting.
- tests/evidence/: manifests, raw execution logs, JUnit, Markdown/CSV/JSON results, commands and limitation/failure documentation.

Modified:
- tests/Feature/ConcurrencyIntegrityTest.php: validate same-period uniqueness without trusting client-supplied week number.
- tests/Feature/DepartmentOwnershipRepairTest.php: use a valid authoritative department assignment when testing absence of first-account fallback.
- tests/Feature/FacultyJournalsPageRepairTest.php: fixed journal start date and current Assigned Students review-queue UI location.
- tests/Feature/MisdMockSyncTest.php: current mock section 4ITA.
- tests/Feature/PortfolioDataIntegrationTest.php: chronological Week 14 for June 1–September 1.
- tests/Support/RunsParallelRequests.php: worker port follows test configuration.
- tests/TestCase.php: reset request-lifetime SchemaCache after migrations/test setup.

New-test corrections during execution:
1. UT-EVAL-02 expected 422 but the actual, source-defined evaluation-period gate is 403. The corrected test also asserts the exact message and absence of persisted evaluation.
2. UT-DOC-13 initially asserted a guessed requirement_targets table. RequirementTarget actually maps to ojt_requirement_targets. The corrected assertion uses the model's targets relationship and verifies the stored section target.

The agent modified **zero production files**. The pre-existing dirty production files and root index.html/report.json are the user's existing changes and were preserved. See git-status-final.txt for the final state. Source files are not reformatted or repaired as part of this pass.

