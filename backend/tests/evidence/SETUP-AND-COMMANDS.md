**Commands and evidence**

All PHP commands below run from backend unless noted. Exact per-module executable/argument arrays are in command-AUTH.json through command-ADM.json; the module logs also contain them. PowerShell redirection captures native stdout/stderr in the corresponding log.

Environment inspection:
```powershell
php -v
php -m
Get-Content phpunit.xml, tests/TestCase.php, tests/CreatesApplication.php, composer.json
Get-Content ../frontend/package.json
rg --files app database tests ../frontend/src
rg -n 'public function test_' tests/Feature
```

The unmodified baseline:
```powershell
php vendor/phpunit/phpunit/phpunit --log-junit tests/evidence/baseline.xml --colors=never
php vendor/phpunit/phpunit/phpunit --log-junit tests/evidence/baseline-isolated.xml --colors=never
php vendor/phpunit/phpunit/phpunit --stop-on-error --log-junit tests/evidence/baseline-diagnostic.xml --colors=never
$env:PATH = 'C:\xampp\mysql\bin;' + $env:PATH
php vendor/phpunit/phpunit/phpunit --log-junit tests/evidence/baseline-ready.xml --colors=never
```

The first command aborted because the server was unavailable. The second was interrupted due to repeated environment failures; it is not a completed baseline. The diagnostic identified missing mysql.exe in PATH. baseline-ready.xml is the completed 369-case baseline.

Disposable server initialization (performed once, from the workspace):
```powershell
& C:/xampp/mysql/bin/mysql_install_db.exe '--datadir=D:\Clarence\System Thesis\interntrack-unit-mysql-20260920' --port=3306
```

The server was launched with Start-Process -WindowStyle Hidden, mysqld.exe --no-defaults --basedir=C:/xampp/mysql, the quoted disposable --datadir, --bind-address=127.0.0.1, and initially --port=3306 (subsequently --port=33307). Logs are in that disposable directory. No existing XAMPP service was stopped or reconfigured. SQL verification used SELECT @@datadir, @@port. CREATE DATABASE IF NOT EXISTS interntrack_testing was issued only after verifying the new server. To avoid the observed Windows table-file sharing errors, SET GLOBAL innodb_file_per_table=OFF was issued only to verified disposable port 33307.

Initial new-test runs:
```powershell
php vendor/phpunit/phpunit/phpunit tests/Unit/ChapterIV --log-junit tests/evidence/isolated-first.xml --colors=never
php vendor/phpunit/phpunit/phpunit tests/Feature/ChapterIV --log-junit tests/evidence/focused-first.xml --colors=never
```

Guarded schema preparation/rebuild (on the disposable server only):
```powershell
$env:EVIDENCE_REBUILD='1'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter 'Tests\\Feature\\AuthTest::test_login_success_returns_token$' --log-junit tests/evidence/schema-rebuild-shared.xml --colors=never
Remove-Item Env:EVIDENCE_REBUILD
```
Earlier failed rebuild attempts are schema-rebuild.xml and schema-rebuild-retry.xml. The successful shared-tablespace attempt is schema-rebuild-shared.xml. RefreshDatabase rebuilds only the guarded test schema.

Module evidence:
```powershell
php tests/run-chapteriv.php
php tests/run-chapteriv.php DOC
php tests/build-chapteriv-report.php
```
The DOC-only rerun followed correction of the test's table-name assumption. Prior module attempts are archived as prior-*-module-* files; these are not added to the final totals.

Targeted correction checks:
```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter 'test_nursing_internship_uses_2703_hours_and_five_htes|test_mock_student_sync_is_idempotent_and_preserves_local_account_fields|test_assigned_faculty_sees_authorized_journal_and_review_persists|test_fo31_journal_and_student_signature_path|test_student_can_declare_hired_after_completed_internship' --log-junit tests/evidence/baseline-rechecks.xml --colors=never
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter 'test_ten_journals_and_same_week_double_submit_stay_unique' --log-junit tests/evidence/concurrency-recheck.xml --colors=never
```

Final full regression suite, kept separate from Chapter IV:
```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --log-junit tests/evidence/final-full-suite.xml --colors=never
```

Repository verification uses process-local safe.directory because the sandbox user differs from the repository owner:
```powershell
git -c safe.directory='D:/Clarence/System Thesis/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao' diff --check
git -c safe.directory='D:/Clarence/System Thesis/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao' status --short
```
No global Git configuration was changed. No commit or push commands were executed. Migration console messages about skipped backfill records are not PHPUnit skipped tests.

Additional concurrency recheck after the final suite exposed an escaping deadlock:
```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.evidence.xml --bootstrap tests/evidence-bootstrap.php --filter test_ten_clock_ins_and_double_clock_in_stay_unique --log-junit tests/evidence/clock-in-concurrency-recheck.xml --colors=never
```
