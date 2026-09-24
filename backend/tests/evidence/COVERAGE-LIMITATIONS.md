**Coverage boundaries**

This is an evidence-based pass over selected individual functions and narrow component operations, not proof of exhaustive functional coverage. The complete original suite was executed as baseline, but its broad workflow results are not promoted into Chapter IV unit results.

The final selection spans every requested module. It gives particularly detailed coverage to attendance credit/AM-PM/absence/date boundaries, backend access checks, journal boundaries, document states and dynamic compliance counts, evaluation score boundaries, and persisted mutation validation.

The following requested areas remain partial or outside the isolated selection:

| Module | Limitations |
|---|---|
| Authentication | Current role equality and backend restrictions are covered; frontend post-login workspace redirect is not executed as a React test. Authentication baseline validates tokens; no external live MISD call is made. |
| Profile | Profile serialization, contact persistence, invalid input, department relation, no unrelated Faculty fallback, target hours and current internship are covered. All Faculty-assignment auto-sync permutations are outside the narrow selection. |
| Placement | Invalid MOA, exhausted slots, wrong Supervisor role, duplicate open internship, status normalization and participant IDs are covered. Multi-step approval/deployment chains remain integration evidence. |
| Attendance | Includes clock-in/out duplicates, 08:00–17:00 and 09:00–17:00 split-credit math, recorded breaks, afternoon attendance, shift-end absence, weekends, historical state, UTC+8 boundary and correction preservation. Does not exhaust overnight shifts, multiple breaks, geolocation, every audit field or full correction approval workflow. |
| Journals | Includes creation/date overlap/locking, Faculty approval, unrelated Faculty denial and Supervisor denial. Same-week submission is an upsert in current code, not an unconditional duplicate rejection; concurrency uniqueness was rechecked separately. Cross-student submission payload tampering and every required field are not all separate selected cases. |
| Documents | Includes authoritative status precedence, three-item numerator/denominator, complete label, PDF metadata, rejected executable, custom creation and modification denial. Faculty template editing is sampled, not every fixed template. Cross-role whole-page propagation is baseline integration coverage, not a strict unit claim. |
| Supervisor | Includes duplicate identity prevention, existing registration account count, actual approval/rejection states, unauthorized Faculty, remarks validation and assigned/unrelated access. Allowed Acceptance Form PDF/image permutations, request-state naming, isolated email recipient/body checks and account-reuse branches are not exhaustive. |
| Feedback | Includes saved supervisor_note, assignment denial, required/max-length validation and Manila timestamp. Full multi-role visibility/refetch/portfolio exclusion remain workflow coverage. |
| Evaluations | Includes weighted FO-24 boundaries and mixed weights, FO-22 nonnumeric exclusion, period gate, required fields, submission and unrelated evaluator denial. All form schemas, per-role result visibility and aggregation permutations are not exhausted. |
| Portfolio | Includes text save, image restrictions, upload/replacement/deletion/ownership and isolated attendance/journal/evaluation serializers. Serializer tests do not prove approved-only query selection. Complete payload assembly and absence of Faculty Evaluation in the assembled portfolio remain baseline integration coverage. Section-completion UI computation is not executed. |
| Messages | Authorized send/read, unrelated thread denial, participant IDs and archive isolation are covered. Attachment type/size and timestamp serialization are not separately executed in this selection. |
| Appointments | Creation, missing fields, reversed dates, forbidden role, outsider attendees and unauthorized write atomicity are covered. Recurrence/timezone combinations are not exhaustive. |
| Notifications | Recipient scoping, read ownership, type/preferences and opt-in/out persistence are covered. No universal deduplication helper was found in Notification::notify; event-specific duplicate behavior is not claimed. |
| Reports | Progress zero-target/capping, validated-hours aggregation, compliance builder, status and AM/PM format are covered. Multi-module report rendering and all relationship/evaluation aggregates are excluded. |
| Admin/MISD | Admin middleware, status input, supported staff-role validation, section payload rejection, audit persistence/privacy and audit access denial are covered. Live MISD availability and all section/program mapping permutations are not tested. |

No browser screenshots, seeded demonstrations or successful builds are used as proof. No new frontend ecosystem was installed. No statement about responsive UI or React error rendering is supported by this pass. There is no line/branch coverage percentage because no coverage driver/report was run.

The numbered checklist in the request is a requirements inventory, not a one-to-one test-count definition. IDs with suffixes are additional boundaries or narrower checks; combined IDs identify one executed case covering related assertions. Untested scenarios are not counted as PHPUnit skips. Read each scenario/expected result and scope before using a row in the manuscript.

**Reproduction**

From backend, with the disposable server running and migrated: add C:/xampp/mysql/bin to the current process PATH, then run `php tests/run-chapteriv.php`. To run one module, append AUTH, PROFILE, PLACE, ATT, JRN, DOC, SUP, FDBK, EVAL, PORT, MSG, APP, NOTIF, RPT or ADM. Each command-*.json contains the exact executable and argument array actually used. Run `php tests/build-chapteriv-report.php` afterward to generate Markdown/CSV/JSON from the current JUnit files.

phpunit.evidence.xml pins port 33307. tests/evidence-bootstrap.php intentionally refuses a different data directory or an incomplete schema. To rebuild only the verified disposable database, set EVIDENCE_REBUILD=1 for a PHPUnit invocation using this bootstrap; RefreshDatabase will rebuild it. Never point that invocation at a development database. The ordinary phpunit.xml was not altered.

