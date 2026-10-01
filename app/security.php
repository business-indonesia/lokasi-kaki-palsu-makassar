<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function security_log(string $action, array $meta=[]): void {
    $dir=storage_file('logs'); if(!is_dir($dir)) @mkdir($dir,0750,true);
    $event=[
        'created_at'=>date('c'),
        'action'=>$action,
        'admin'=>(string)($_SESSION['admin_user']??'system'),
        'ip'=>(string)($_SERVER['REMOTE_ADDR']??''),
        'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,220),
        'meta'=>$meta,
    ];
    @file_put_contents($dir.'/audit.jsonl',json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
}
function deploy_lock(string $action='check'): bool {
    $f=storage_file('deploy.lock');
    if($action==='acquire'){
        @mkdir(dirname($f),0750,true);
        if(is_file($f)){
            $age=time()-(int)@filemtime($f);
            if($age>1800) @unlink($f);
        }
        if(is_file($f)) return false;
        $h=@fopen($f,'x');
        if(!$h) return false;
        @fwrite($h,json_encode(['created_at'=>date('c'),'admin'=>$_SESSION['admin_user']??''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        @fclose($h);
        return true;
    }
    if(!is_file($f)) return false;
    if(time()-(int)@filemtime($f)>1800){ @unlink($f); return false; }
    return true;
}
function release_deploy_lock(): void { @unlink(storage_file('deploy.lock')); }
