<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$pages=$root.'/resources/js/pages';$allowed=['Command','Accounts','Alliance','Operations','Intelligence','Kingdom','Citadel','Public'];$forbidden=['ReadModels','GameWorld','Platform','Auth','Events','KingPerks','Contributions'];$v=[];
foreach(new DirectoryIterator($pages) as $e){if($e->isDot())continue;$n=$e->getFilename();if($e->isDir()&&!in_array($n,$allowed,true))$v[]="Unexpected page root: $n";if($e->isFile()&&$e->getExtension()==='vue')$v[]="Root-level page forbidden: $n";}
foreach($forbidden as $n)if(is_dir($pages.'/'.$n))$v[]="Forbidden page root exists: $n";
if($v){fwrite(STDERR,"FRONTEND-V3 architecture violations:\n - ".implode("\n - ",$v)."\n");exit(1);}fwrite(STDOUT,"FRONTEND-V3 architecture gate: PASS\n");
