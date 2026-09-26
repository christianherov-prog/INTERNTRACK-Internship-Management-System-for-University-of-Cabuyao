<?php
/** Every status is derived from executed JUnit, never from the manifest. */
$dir=__DIR__.'/integration-evidence';
$cases=json_decode(file_get_contents($dir.'/cases.json'),true,512,JSON_THROW_ON_ERROR);
$failures=array_column(json_decode(file_get_contents($dir.'/failure-details.json'),true,512,JSON_THROW_ON_ERROR),null,'id');
$xml=simplexml_load_file($dir.'/final.xml');
$tests=[];
foreach($xml->xpath('//testcase') as $t) $tests[(string)$t['class'].'::'.(string)$t['name']]=$t;
if(count($tests)!==count($cases)) throw new RuntimeException('Selection/JUnit count mismatch.');
$esc=fn($s)=>str_replace(["\r","\n",'|'],['','<br>','&#124;'],(string)$s);
$totals=['executed'=>0,'passed'=>0,'failed'=>0,'skipped'=>0,'errors'=>0,'assertions'=>0];
$areas=[];$results=[];$details="";
$report=file_get_contents($dir.'/CONTEXT.md')."\n\n";
$report.="**Table 4. Results of Integration Testing of INTERNTRACK**\n\n| Test Case ID | Integrated Modules | Test Scenario | Expected Result | Actual Result | Status |\n|---|---|---|---|---|---|\n";
foreach($cases as $c) {
    $t=$tests[$c['class'].'::'.$c['method']]??throw new RuntimeException('Missing executed case '.$c['id']);
    $status=isset($t->error)?'Error':(isset($t->failure)?'Failed':(isset($t->skipped)?'Skipped':'Passed'));
    if($status==='Passed'&&(int)$t['assertions']===0) throw new RuntimeException('Zero-assertion pass '.$c['id']);
    $actual=$status==='Passed'?$c['passed_actual']:($failures[$c['id']]['actual']??trim((string)($t->failure??$t->error??$t->skipped)));
    $key=['Passed'=>'passed','Failed'=>'failed','Error'=>'errors','Skipped'=>'skipped'][$status];
    $totals[$key]++;$totals['executed']+=(int)($status!=='Skipped');$totals['assertions']+=(int)$t['assertions'];
    $areas[$c['area']]??=['executed'=>0,'passed'=>0,'failed'=>0,'skipped'=>0,'errors'=>0];
    $areas[$c['area']][$key]++;$areas[$c['area']]['executed']+=(int)($status!=='Skipped');
    $output=trim((string)($t->failure??$t->error??$t->skipped));
    $results[]=$c+['status'=>$status,'actual'=>$actual,'assertions'=>(int)$t['assertions'],'seconds'=>(float)$t['time'],'failure_output'=>$output];
    $report.='| '.$c['id'].' | '.$esc($c['modules']).' | '.$esc($c['scenario']).' | '.$esc($c['expected']).' | '.$esc($actual).' | '.$status." |\n";
    $details.='**'.$c['id'].' — '.$status."**\n\nSetup / authoritative records: ".$c['setup']."\n\nExact test file: ".$c['file']."\n\nExact method: ".$c['class'].'::'.$c['method']."\n\n";
    $details.='Executed command: [command-final.json](command-final.json), reproduced with php tests/run-integration-evidence.php from backend. Assertions: '.(int)$t['assertions'].". Individual filter: ".$c['class'].'::'.$c['method']."$ (same configuration and guarded bootstrap).\n\n";
    if($c['class']==='Tests\\Feature\\ChapterIVIntegration\\WorkflowIntegrationTest') $details.='Captured downstream values: [observation JSON](observations/'.$c['method'].".json).\n\n";
    if($output!=='') $details.="Failure/skip output:\n\n~~~text\n".$output."\n~~~\n\n";
}
if(count($areas)!==21) throw new RuntimeException('Expected all 21 integration areas.');
$discussion="Table 4 presents the integration testing results for interconnected INTERNTRACK modules. A total of {$totals['executed']} scenarios were executed: {$totals['passed']} passed and {$totals['failed']} failed, with {$totals['errors']} execution errors and {$totals['skipped']} skipped cases. The tests examined authentication, placement, attendance, journals, requirements, Supervisor management, evaluations, portfolios, messaging, appointments, notifications, reporting and account administration. Successful scenarios transferred and retrieved the asserted authoritative records across their tested boundaries. Failed scenarios identified incorrect scheduled attendance credit, omitted first-day absence, unapproved portfolio journals, evaluation-requirement scope, inconsistent report hours, unauthorized appointment persistence and two Coordinator workflow conflicts. Test setup and assertion issues were corrected and rerun; no production behavior was changed. The failed application workflows and role-rule conflicts therefore require correction or policy resolution and subsequent retesting before they can be reported as conforming.";
$report.="\n".$discussion."\n\n**Consolidated integration summary**\n\n| Integration Area | Tests Executed | Passed | Failed | Skipped | Errors | Overall Result |\n|---|---:|---:|---:|---:|---:|---|\n";
$names=['INT-01'=>'Authentication/workspace','INT-02'=>'Profile/internship/placement','INT-03'=>'Placement/Supervisor assignment','INT-04'=>'Attendance/hours/correction','INT-05'=>'Attendance/FO-30','INT-06'=>'Absence/day state','INT-07'=>'Journal review/authorization','INT-08'=>'Journal/FO-31','INT-09'=>'Journal/portfolio','INT-10'=>'Upload/compliance','INT-11'=>'Compliance/report/scope','INT-12'=>'Supervisor approval/assignment','INT-13'=>'Attendance validation','INT-14'=>'Evaluation/Student record','INT-15'=>'Evaluation/portfolio','INT-16'=>'Private messaging','INT-17'=>'Appointments/persistence','INT-18'=>'Action/notifications','INT-19'=>'Broad portfolio assembly','INT-20'=>'Reports/source consistency','INT-21'=>'Admin/account/auth/audit'];
foreach($areas as $id=>$a) $report.="| {$id} ".$names[$id]." | {$a['executed']} | {$a['passed']} | {$a['failed']} | {$a['skipped']} | {$a['errors']} | ".($a['failed']||$a['errors']?'With Failures':'Passed')." |\n";
$report.="| **Total** | **{$totals['executed']}** | **{$totals['passed']}** | **{$totals['failed']}** | **{$totals['skipped']}** | **{$totals['errors']}** | **With Failures** |\n\n";
$report.="The selected run recorded {$totals['assertions']} assertions. Six distinct application defects were reproduced (five data-consistency defects and one atomicity defect), plus two workflow conflicts. The atomicity defect also has authorization impact; these categories overlap and must not be added as independent defect counts. Test-only/environment history is reported separately. Previous unit/full-regression/concurrency totals are not included.\n\n";
$report.="Supporting evidence: [per-case setup, exact method, assertions and failure output](CASE-DETAILS.md), [failure root causes](FAILURE-ANALYSIS.md), [run history](RUN-HISTORY.md), [coverage limitations](COVERAGE-LIMITATIONS.md), [commands](SETUP-AND-COMMANDS.md), [file changes](TEST-FILE-CHANGES.md), [raw JUnit](final.xml), [raw output](final.log), [final repository state](git-final.txt).\n";
file_put_contents($dir.'/CHAPTER-IV-INTEGRATION-TESTING.md',$report);
file_put_contents($dir.'/CASE-DETAILS.md',$details);
file_put_contents($dir.'/RESULTS.json',json_encode(['totals'=>$totals,'areas'=>$areas,'cases'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$f=fopen($dir.'/RESULTS.csv','w');
fputcsv($f,['Test Case ID','Integrated Modules','Test Scenario','Setup','Expected Result','Actual Result','Status','Assertions','Exact Test'],',','"','');
foreach($results as $r) fputcsv($f,[$r['id'],$r['modules'],$r['scenario'],$r['setup'],$r['expected'],$r['actual'],$r['status'],$r['assertions'],$r['class'].'::'.$r['method']],',','"','');
fclose($f);
echo json_encode($totals,JSON_PRETTY_PRINT).PHP_EOL;

