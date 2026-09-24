<?php
$dir = __DIR__;
$cases = json_decode(file_get_contents($dir.'/../integration-evidence/cases.json'), true, 512, JSON_THROW_ON_ERROR);
$xml = simplexml_load_file($dir.'/integration.xml');
$tests = [];
foreach ($xml->xpath('//testcase') as $test) {
    $tests[(string)$test['class'].'::'.(string)$test['name']] = $test;
}
if (count($tests) !== count($cases)) throw new RuntimeException('Manifest and executed test counts differ.');
$totals = ['tests'=>count($tests), 'passed'=>0, 'failed'=>0, 'errors'=>0, 'skipped'=>0, 'assertions'=>0];
$rows = [];
$areas = [];
$escape = fn($value) => str_replace(["\r", "\n", '|'], ['', '<br>', '&#124;'], (string)$value);
$details = "| Case | Integrated modules | Scenario | Expected result | Actual result | Status |\n|---|---|---|---|---|---|\n";
foreach ($cases as $case) {
    $test = $tests[$case['class'].'::'.$case['method']] ?? throw new RuntimeException('Missing '.$case['id']);
    $status = isset($test->error) ? 'errors' : (isset($test->failure) ? 'failed' : (isset($test->skipped) ? 'skipped' : 'passed'));
    $actual = $status === 'passed' ? $case['passed_actual'] : trim((string)($test->error ?? $test->failure ?? $test->skipped));
    $totals[$status]++;
    $totals['assertions'] += (int)$test['assertions'];
    $areas[$case['area']] ??= ['tests'=>0, 'passed'=>0, 'failed'=>0, 'errors'=>0, 'skipped'=>0];
    $areas[$case['area']]['tests']++;
    $areas[$case['area']][$status]++;
    $row = [$case['id'], $case['modules'], $case['scenario'], $case['expected'], $actual, ucfirst($status), (int)$test['assertions'], $case['class'].'::'.$case['method']];
    $rows[] = $row;
    $details .= '| '.implode(' | ', array_map($escape, array_slice($row, 0, 6)))." |\n";
}
$csv = fopen($dir.'/test-cases.csv', 'w');
fputcsv($csv, ['Case','Integrated modules','Scenario','Expected result','Actual result','Status','Assertions','Test'], ',', '"', '');
foreach ($rows as $row) fputcsv($csv, $row, ',', '"', '');
fclose($csv);
$summary = "| Area | Tests | Passed | Failed | Errors | Skipped |\n|---|---:|---:|---:|---:|---:|\n";
foreach ($areas as $area=>$counts) $summary .= '| '.$area.' | '.implode(' | ', $counts)." |\n";
$summary .= "| **Total** | **{$totals['tests']}** | **{$totals['passed']}** | **{$totals['failed']}** | **{$totals['errors']}** | **{$totals['skipped']}** |\n";
$report = "# Integration testing results - September 24, 2026\n\nBranch: Internet-Develop, base commit c927209 plus the local unit-test isolation change and configurable integration observation output. No production code changed.\n\nExecuted the existing 33-case integration selection across 21 areas using PHP 8.5.8 and PHPUnit 11.5.56. Totals: ".json_encode($totals).". These totals come from this run's JUnit XML; historical outcomes are not reused.\n\nDatabase: interntrack_testing at 127.0.0.1:33307, in the dedicated interntrack-unit-mysql-20260920 directory. The existing bootstrap verifies the server data directory and migration state, rebuilds the disposable schema once, and rolls back test transactions.\n\nScope: Laravel HTTP APIs, services, and database interactions. Mail and storage are faked; queues are synchronous, broadcasting is disabled, and MISD is mocked. This is not browser testing or verification of live external services.\n\nEvidence: [JUnit](integration.xml), [execution log](integration.log), [command/filter](command.txt), [database verification](database-verification.txt), [CSV table](test-cases.csv). Per-scenario observations are saved in observations/.\n\n".$summary."\n## Scenario results\n\n".$details;
file_put_contents($dir.'/RESULTS.md', $report);
file_put_contents($dir.'/results.json', json_encode(['totals'=>$totals,'areas'=>$areas,'cases'=>$rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($totals, JSON_PRETTY_PRINT).PHP_EOL;
foreach ($rows as $row) if ($row[5] !== 'Passed') echo $row[0].' '.$row[1].PHP_EOL.$row[4].PHP_EOL;
