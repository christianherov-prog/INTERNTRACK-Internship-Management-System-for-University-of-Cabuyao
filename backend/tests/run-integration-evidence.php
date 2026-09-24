<?php
$root=dirname(__DIR__);
$dir=__DIR__.'/integration-evidence';
$cases=json_decode(file_get_contents($dir.'/cases.json'),true,512,JSON_THROW_ON_ERROR);
$filter=implode('|',array_map(fn($c)=>preg_quote($c['class'].'::'.$c['method'],'~').'$', $cases));
foreach(['final.xml','final.log'] as $file) {
    if(is_file($dir.'/'.$file)) rename($dir.'/'.$file,$dir.'/prior-'.date('Ymd-His').'-'.$file);
}
if(is_dir($dir.'/observations')) {
    $archive=$dir.'/observations-before-final-'.date('Ymd-His');
    rename($dir.'/observations',$archive);
}
$args=[PHP_BINARY,'vendor/phpunit/phpunit/phpunit','--configuration','phpunit.evidence.xml','--bootstrap','tests/integration-bootstrap.php','--filter',$filter,'--log-junit','tests/integration-evidence/final.xml','--colors=never'];
file_put_contents($dir.'/command-final.json',json_encode(['cwd'=>$root,'arguments'=>$args],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$log=fopen($dir.'/final.log','w');
$process=proc_open($args,[0=>['pipe','r'],1=>$log,2=>$log],$pipes,$root);
if(!is_resource($process)) throw new RuntimeException('Unable to start PHPUnit.');
fclose($pipes[0]); $exit=proc_close($process); fclose($log);
file_put_contents($dir.'/exit-final.json',json_encode(['exit'=>$exit,'selected'=>count($cases)],JSON_PRETTY_PRINT));
echo 'Selected '.count($cases).' integration cases; PHPUnit exit '.$exit.PHP_EOL;
exit($exit);

