**Execution history — do not add reruns to final totals**

| Run | Tests | Passed | Failed | Errors | Skipped | Evidence |
|---|---:|---:|---:|---:|---:|---|
| Existing baseline, inherited concurrency fixtures | 105 | 44 | 2 | 59 | 0 | baseline.xml / baseline.log |
| Existing baseline, verified disposable schema rebuilt | 105 | 105 | 0 | 0 | 0 | baseline-clean.xml / baseline-clean.log |
| New focused tests, first run | 21 | 12 | 9 | 0 | 0 | focused-first.xml / focused-first.log |
| New focused tests, second run | 23 | 16 | 7 | 0 | 0 | focused-second.xml / focused-second.log |
| New focused tests, third run | 24 | 16 | 8 | 0 | 0 | focused-third.xml / focused-third.log |
| Final selected integration run | 33 | 25 | 8 | 0 | 0 | final.xml / final.log |

The clean baseline recorded 836 assertions. Final selected evidence recorded 509 assertions. Baseline selection contains some component/static checks and is not an additional integration scenario total.

**Setup and test-only corrections**

1. The disposable server retained committed fixtures from the previous concurrency recheck. The first baseline encountered duplicate section-assignment constraints and existing-record assumptions. Its 59 errors and two failures are retained. Rebuilding only the positively verified disposable database allowed the unchanged 105-case baseline to pass.
2. New appointment fixtures incorrectly used type consultation. Meeting::TYPES supports orientation, check_in, defense_prep and other. Correcting the fixture to check_in allowed INT-17 and INT-18 to execute their workflows and exposed the real INT-17-ATOMIC persistence failure. No production enum was changed.
3. INT-20 compared decimal string 80.00 to aggregate string 80.000000. It now compares numeric values with a small tolerance. This removes a formatting-only test failure; the actual hour-consistency assertion still fails.
4. The first integration-bootstrap draft assumed users/internships/assignments must be empty after migration. The 2026_08_29_060000_seed_chas_cas_cbaa_accounts migration legitimately seeds accounts. The bootstrap refused the run before any cases executed; its log is archived as prior-*-final.log. It now verifies the disposable target and forces one RefreshDatabase rebuild, avoiding both committed-fixture carryover and a false empty-schema assumption.

Thus there was one inherited test-data/environment issue and three test/harness assumption issues. No production fixes were made. Moving business assertions after captured observations in INT-06 and INT-20 allowed downstream inspection without weakening expected behavior. The scheduled-afternoon follow-up was added after observations showed an unexpected one-hour credit.

Observation directories from earlier attempts are archived as observations-first, observations-second and observations-before-final-*. Current observations correspond to final.xml. A bootstrap refusal is not a PHPUnit skipped case or an executed scenario. Prior unit-testing evidence is retained unchanged.

