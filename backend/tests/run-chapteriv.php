<?php
/** Run selected cases once per module and preserve raw PHPUnit/JUnit evidence. */
chdir(dirname(__DIR__));
$cases = array_merge(...array_map(fn($f)=>json_decode(file_get_contents(__DIR__.'/evidence/'.$f),true,512,JSON_THROW_ON_ERROR), ['unit-cases.json','focused-cases.json','existing-selected-cases.json','supplemental-cases.json','extra-focused-cases.json']));
$modules = ['AUTH','PROFILE','PLACE','ATT','JRN','DOC','SUP','FDBK','EVAL','PORT','MSG','APP','NOTIF','RPT','ADM'];
if (isset($argv[1])) $modules = array_intersect($modules, explode(',', $argv[1]));
$commands = [];
foreach ($modules as $module) {
    // Preserve previous attempts and prevent stale JUnit files after bootstrap errors.
    foreach (['xml','log'] as $extension) {
        $previous='tests/evidence/module-'.$module.'.'.$extension;
        if (is_file($previous)) rename($previous,'tests/evidence/prior-'.date('Ymd-His').'-module-'.$module.'.'.$extension);
    }
    $selected = array_values(array_filter($cases,fn($c)=>$c['module']===$module));
    $filter = '/^(?:'.implode('|',array_map(fn($c)=>preg_quote($c['class'].'::'.$c['method'],'/'),$selected)).')$/';
    $args = [PHP_BINARY,'vendor/phpunit/phpunit/phpunit','--configuration','phpunit.evidence.xml','--bootstrap','tests/evidence-bootstrap.php','--filter',$filter,'--log-junit','tests/evidence/module-'.$module.'.xml','--colors=never'];
    $log = fopen('tests/evidence/module-'.$module.'.log','w');
    fwrite($log, 'Command argv: '.json_encode($args,JSON_UNESCAPED_SLASHES).PHP_EOL);
    $process = proc_open($args, [0=>['pipe','r'],1=>$log,2=>$log],$pipes,getcwd());
    if (!is_resource($process)) throw new RuntimeException('Cannot launch PHPUnit');
    fclose($pipes[0]);
    $exit=proc_close($process); fclose($log);
    $commands[$module]=['args'=>$args,'exit'=>$exit,'selected'=>count($selected)];
    file_put_contents('tests/evidence/command-'.$module.'.json',json_encode($commands[$module],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo $module.': selected '.count($selected).'; PHPUnit exit '.$exit.PHP_EOL;
}
