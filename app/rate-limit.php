<?php
declare(strict_types=1);

function rl_client_key(string $scope): string {
    global $config;
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,180);
    $secret=(string)($config['admin']['session_secret']??'');
    return hash('sha256',$scope.'|'.$ip.'|'.$ua.'|'.$secret);
}

function rl_check(string $scope,int $limit,int $window,int $dailyLimit=0): array {
    $limit=max(1,$limit); $window=max(1,$window); $dailyLimit=max(0,$dailyLimit);
    $file=storage_file('logs/rate-'.$scope.'.json'); $dir=dirname($file);
    if(!is_dir($dir)) @mkdir($dir,0750,true);
    $now=time(); $day=date('Y-m-d'); $key=rl_client_key($scope);
    $fp=@fopen($file,'c+');
    if(!$fp) return ['allowed'=>true,'retry_after'=>0];
    @flock($fp,LOCK_EX); rewind($fp);
    $raw=stream_get_contents($fp); $data=json_decode($raw?:'',true); if(!is_array($data))$data=[];
    // Keep the file bounded: remove stale entries and cap active keys.
    foreach($data as $k=>$v){
        if(!is_array($v) || ((int)($v['window_until']??0)<$now && (string)($v['day']??'')!==$day)){unset($data[$k]);}
    }
    if(count($data)>2000){uasort($data,function($a,$b){return (int)($a['last']??0)<=> (int)($b['last']??0);});$data=array_slice($data,-1000,true);}
    $e=(array)($data[$key]??['count'=>0,'window_until'=>0,'day'=>$day,'daily'=>0,'last'=>$now]);
    if((string)($e['day']??'')!==$day){$e['day']=$day;$e['daily']=0;}
    $until=(int)($e['window_until']??0);
    if($until<$now){$e['count']=0;$e['window_until']=$now+$window;}
    $e['count']=(int)($e['count']??0);
    $e['daily']=(int)($e['daily']??0);
    if($e['count'] >= $limit || ($dailyLimit>0 && $e['daily'] >= $dailyLimit)){
        $retry=max(1,$until-$now);
        $data[$key]=$e+['last'=>$now];
        rewind($fp); ftruncate($fp,0); fwrite($fp,json_encode($data,JSON_UNESCAPED_SLASHES)); fflush($fp); @flock($fp,LOCK_UN); fclose($fp);
        return ['allowed'=>false,'retry_after'=>$retry];
    }
    $e['count']++; $e['daily']++; $e['last']=$now; $data[$key]=$e;
    rewind($fp); ftruncate($fp,0); fwrite($fp,json_encode($data,JSON_UNESCAPED_SLASHES)); fflush($fp); @flock($fp,LOCK_UN); fclose($fp);
    return ['allowed'=>true,'retry_after'=>0];
}
