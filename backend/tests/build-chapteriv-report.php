<?php
/** Generate results from JUnit, never from presumed outcomes or process exit alone. */
$dir=__DIR__.'/evidence';
$cases=array_merge(...array_map(fn($f)=>json_decode(file_get_contents($dir.'/'.$f),true,512,JSON_THROW_ON_ERROR),['unit-cases.json','focused-cases.json','existing-selected-cases.json','supplemental-cases.json','extra-focused-cases.json']));
$modules=['AUTH'=>'Authentication','PROFILE'=>'Student Profile','PLACE'=>'Placement','ATT'=>'Attendance','JRN'=>'Weekly Journal','DOC'=>'Documents & Requirements','SUP'=>'Supervisor Management','FDBK'=>'Feedback','EVAL'=>'Evaluations','PORT'=>'Portfolio','MSG'=>'Messages','APP'=>'Appointments','NOTIF'=>'Notifications','RPT'=>'Reports/Data Builders','ADM'=>'Admin/MISD'];
$observedFailures=[
 'UT-APP-04-ATOMIC'=>'HTTP 403 was returned, but the unauthorized meeting row remained in the database.',
 'UT-DOC-EVAL-SCOPE'=>'The Host Evaluation requirement returned approved despite only a submitted FO-24 record being present.',
 'UT-JRN-11'=>'Coordinator received HTTP 200 from journal approval instead of the required HTTP 403.',
 'UT-SUP-FACULTY-ONLY'=>'Coordinator received HTTP 200 from Supervisor approval instead of the required HTTP 403.',
];
$esc=fn($s)=>str_replace(["\r","\n",'|'],['','<br>','&#124;'],(string)$s);
$report=file_get_contents($dir.'/REPORT-CONTEXT.md')."\n\n";
$summary=[];$all=[];$total=['executed'=>0,'passed'=>0,'failed'=>0,'skipped'=>0,'errors'=>0,'unit'=>0,'component'=>0];
$number=0;
foreach($modules as $key=>$name){
    $selected=array_values(array_filter($cases,fn($c)=>$c['module']===$key));
    $xmlPath=$dir.'/module-'.$key.'.xml';
    if(!is_file($xmlPath)) throw new RuntimeException('Missing current JUnit: '.$key);
    $xml=simplexml_load_file($xmlPath); $tests=[];
    foreach($xml->xpath('//testcase') as $t){$tests[str_replace('.','\\',(string)$t['classname']).'::'.(string)$t['name']]=$t;}
    if(count($tests)!==count($selected)) throw new RuntimeException('Manifest/JUnit count mismatch: '.$key);
    $count=['executed'=>0,'passed'=>0,'failed'=>0,'skipped'=>0,'errors'=>0,'unit'=>0,'component'=>0];
    $report.='**Table '.(++$number).'. Results of Unit Testing for '.$name."**\n\n";
    $report.="| Test Case ID | Module Function Tested | Test Scenario | Expected Result | Actual Result | Status |\n|---|---|---|---|---|---|\n";
    foreach($selected as $c){
        $t=$tests[$c['class'].'::'.$c['method']]??throw new RuntimeException('Missing result '.$c['id']);
        $status=isset($t->error)?'Error':(isset($t->failure)?'Failed':(isset($t->skipped)?'Skipped':'Passed'));
        if($status==='Passed'&&(int)$t['assertions']===0) throw new RuntimeException('Zero-assertion pass '.$c['id']);
        $count[$status==='Passed'?'passed':($status==='Failed'?'failed':($status==='Error'?'errors':'skipped'))]++;
        if($status!=='Skipped')$count['executed']++;
        $unit=str_starts_with($c['class'],'Tests\\Unit\\');$count[$unit?'unit':'component']++;
        $actual=$status==='Passed'?'Assertions verified: '.$c['expected']:($observedFailures[$c['id']]??trim((string)($t->failure??$t->error??$t->skipped)));
        $all[]=array_merge($c,['status'=>$status,'actual'=>$actual,'assertions'=>(int)$t['assertions'],'seconds'=>(float)$t['time']]);
        $report.='| '.$c['id'].' | '.$esc($c['fn']).($unit?' [U]':' [C]').' | '.$esc($c['scenario']).' | '.$esc($c['expected']).' | '.$esc($actual).' | '.$status." |\n";
    }
    $discussion="Table {$number} presents {$count['executed']} executed test cases for {$name}: {$count['passed']} passed, {$count['failed']} failed, {$count['errors']} execution errors, and {$count['skipped']} skipped. The selection comprised {$count['unit']} isolated unit cases and {$count['component']} narrowly scoped Laravel component cases.";
    if($count['failed']||$count['errors'])$discussion.=' The unsuccessful cases and their source-level causes are documented in FAILURE-ANALYSIS.md; the module cannot be reported as passed.';
    else $discussion.=' The selected functions produced the asserted results; this finding does not establish correctness of untested paths.';
    $discussion.=$key==='EVAL'?' One new test initially expected 422 for an unapproved evaluation period; inspection confirmed the implemented 403 gate, and the test was corrected and rerun.':' No production code was corrected. Test-only adjustments and remaining coverage limits are documented separately.';
    $report.="\n".$discussion."\n\nEvidence: [JUnit](module-{$key}.xml), [raw output](module-{$key}.log), [exact PHPUnit arguments](command-{$key}.json). Reproduce this selection with `php tests/run-chapteriv.php {$key}` from `backend`.\n\n";
    $summary[$key]=['module'=>$name]+$count;
    foreach($total as $k=>$v)$total[$k]+=$count[$k];
}
$report.="**Consolidated results**\n\n| Module | Tests Executed | Passed | Failed | Skipped | Errors | Overall Result |\n|---|---:|---:|---:|---:|---:|---|\n";
foreach($summary as $r){$report.="| {$r['module']} | {$r['executed']} | {$r['passed']} | {$r['failed']} | {$r['skipped']} | {$r['errors']} | ".($r['failed']||$r['errors']?'With Failures':'Passed')." |\n";}
$report.="| **Total** | **{$total['executed']}** | **{$total['passed']}** | **{$total['failed']}** | **{$total['skipped']}** | **{$total['errors']}** | **".($total['failed']||$total['errors']?'With Failures':'Passed')."** |\n\n";
$report.="The Chapter IV selection contains {$total['unit']} unit cases and {$total['component']} narrow component cases. These are distinct from the full baseline and integration rechecks; repeated runs are not added to these totals. Skipped means a PHPUnit skip, not an untested checklist item. No selected case is labelled Passed without a matching executed JUnit case and assertions.\n\n";
$report.="See [failure analysis](FAILURE-ANALYSIS.md), [coverage limitations](COVERAGE-LIMITATIONS.md), [test-to-source manifest](results.json), and [final git status](git-status-final.txt). The supported conclusion is limited to the tested functions, and does not describe the system as fully functional.\n";
file_put_contents($dir.'/CHAPTER-IV-UNIT-TESTING.md',$report);
file_put_contents($dir.'/results.json',json_encode(['summary'=>$summary,'totals'=>$total,'cases'=>$all],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$csv=fopen($dir.'/results.csv','w');fputcsv($csv,['Test Case ID','Module Function Tested','Test Scenario','Expected Result','Actual Result','Status','Scope','PHPUnit test'],',','"','');foreach($all as $r)fputcsv($csv,[$r['id'],$r['fn'],$r['scenario'],$r['expected'],$r['actual'],$r['status'],$r['scope'],$r['class'].'::'.$r['method']],',','"','');fclose($csv);
echo json_encode($total,JSON_PRETTY_PRINT).PHP_EOL;
