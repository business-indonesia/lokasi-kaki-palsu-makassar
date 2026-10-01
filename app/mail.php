<?php
declare(strict_types=1);

/* Email System 2.0 - Gmail SMTP (App Password). */
function smtp_mail_key(): string {
    global $config;
    $secret=(string)($config['admin']['session_secret']??'');
    if(strlen($secret)<64) throw new RuntimeException('Secret keamanan aplikasi belum tersedia atau tidak valid.');
    return hash('sha256','admin-control-center:gmail-smtp:credential:v7|'.$secret,true);
}
function smtp_credential_file(): string { return storage_file('email-system.secure.php'); }
function smtp_normalize_app_password(string $password): string {
    $password=preg_replace('/\s+/','',$password)??$password;
    if(!preg_match('/^[A-Za-z0-9]{16}$/',$password)) throw new RuntimeException('App Password Gmail harus 16 karakter. Jika Google menampilkan spasi, sistem akan menghapus spasi otomatis.');
    return $password;
}
function smtp_extract_credential_file(): string {
    $f=smtp_credential_file();
    if(!is_file($f)) return '';
    $raw=(string)@file_get_contents($f);
    if(strpos($raw,'<?php')===0){$d=@include $f; return is_array($d)?(string)($d['credential']??''):'';}
    $d=json_decode($raw,true); return is_array($d)?(string)($d['smtp_password_enc']??''):'';
}
function smtp_extract_config_credential(): string {
    global $config; return (string)($config['mail']['smtp_password_enc']??'');
}
function smtp_password_decrypt(): string {
    $enc=smtp_extract_credential_file();
    if($enc==='') $enc=smtp_extract_config_credential();
    if($enc==='') return '';
    $payload=strpos($enc,'v7:')===0?substr($enc,3):(strpos($enc,'v6:')===0?substr($enc,3):$enc);
    $raw=base64_decode($payload,true); if($raw===false||strlen($raw)<48)return '';
    $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);$key=smtp_mail_key();
    if(!hash_equals($mac,hash_hmac('sha256',$iv.$cipher,$key,true)))return '';
    $plain=openssl_decrypt($cipher,'aes-256-cbc',$key,OPENSSL_RAW_DATA,$iv);
    return is_string($plain)?$plain:'';
}

function smtp_read_response($fp,string $stage,int $timeout=20): array {
    $raw='';$code=null;$started=microtime(true);
    while(!feof($fp)){
        $line=fgets($fp,8192);if($line===false)break;$raw.=$line;
        if(preg_match('/^(\d{3})([ -])/',rtrim($line,"\r\n"),$m)){ $code=(int)$m[1]; if($m[2]===' ')break; }
        if(microtime(true)-$started>$timeout)break;
    }
    if($code===null)throw new RuntimeException('SMTP '.$stage.' tidak memberikan respons lengkap.');
    $text=trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',$raw))??'');
    return ['code'=>$code,'text'=>$text,'raw'=>$raw];
}
function smtp_command($fp,string $command,string $stage,array $expected): array { if(@fwrite($fp,$command."\r\n")===false)throw new RuntimeException('SMTP '.$stage.' gagal dikirim.');$r=smtp_read_response($fp,$stage);if(!in_array($r['code'],$expected,true)){ $detail=$r['text'];if(strlen($detail)>280)$detail=substr($detail,0,280).'...';throw new RuntimeException('SMTP '.$stage.' ditolak ('.$r['code'].'). '.($detail?:'Server menolak perintah.'));}return $r; }
function smtp_auth_methods(string $raw): array { $out=[];foreach(preg_split('/\r?\n/',$raw)?:[] as $line){if(preg_match('/^250[- ]AUTH(?:=|\s+)(.*)$/i',trim($line),$m)){foreach(preg_split('/\s+/',strtoupper(trim($m[1])))?:[] as $x)if($x!=='')$out[]=$x;}}return array_values(array_unique($out)); }
function smtp_open(array $cfg): array {
    if(!extension_loaded('openssl'))throw new RuntimeException('OpenSSL PHP tidak aktif.');
    $host=trim((string)($cfg['smtp_host']??'smtp.gmail.com'));$port=(int)($cfg['smtp_port']??465);$security=strtolower((string)($cfg['smtp_security']??'ssl'));
    if($host!=='smtp.gmail.com')throw new RuntimeException('Email System 2.0 menggunakan Gmail SMTP. Host harus smtp.gmail.com.');
    if(($port===465&&$security!=='ssl')||($port===587&&$security!=='tls'))throw new RuntimeException('Gunakan smtp.gmail.com dengan 465 + SSL atau 587 + STARTTLS.');
    $transport=$security==='ssl'?'ssl://'.$host:$host;$ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'SNI_enabled'=>true,'peer_name'=>$host,'crypto_method'=>STREAM_CRYPTO_METHOD_TLS_CLIENT]]);
    $errno=0;$errstr='';$fp=@stream_socket_client($transport.':'.$port,$errno,$errstr,20,STREAM_CLIENT_CONNECT,$ctx);if(!$fp)throw new RuntimeException('Tidak dapat terhubung ke Gmail SMTP '.$host.':'.$port.'. '.trim($errstr));stream_set_timeout($fp,20);
    try{$g=smtp_read_response($fp,'greeting');if($g['code']!==220)throw new RuntimeException('Gmail SMTP greeting ditolak ('.$g['code'].').');$domain=parse_url((string)($GLOBALS['config']['site']['url']??''),PHP_URL_HOST)?:'localhost';$eh=smtp_command($fp,'EHLO '.$domain,'EHLO',[250]);if($security==='tls'){smtp_command($fp,'STARTTLS','STARTTLS',[220]);if(@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)!==true)throw new RuntimeException('STARTTLS Gmail gagal. Coba 465 + SSL.');$eh=smtp_command($fp,'EHLO '.$domain,'EHLO setelah TLS',[250]);}return ['fp'=>$fp,'auth_methods'=>smtp_auth_methods($eh['raw']),'host'=>$host,'port'=>$port,'security'=>$security];}catch(Throwable $e){@fclose($fp);throw $e;}
}
function smtp_authenticate($fp,array $methods,string $user,string $pass): void {
    if(in_array('LOGIN',$methods,true)){smtp_command($fp,'AUTH LOGIN','AUTH LOGIN',[334]);smtp_command($fp,base64_encode($user),'username Gmail',[334]);smtp_command($fp,base64_encode($pass),'App Password Gmail',[235]);return;}
    if(in_array('PLAIN',$methods,true)){smtp_command($fp,'AUTH PLAIN '.base64_encode("\0".$user."\0".$pass),'AUTH PLAIN',[235]);return;}
    throw new RuntimeException('Gmail tidak menawarkan AUTH LOGIN/PLAIN. Periksa App Password dan keamanan akun Google.');
}
function smtp_send(array $cfg,string $to,string $subject,string $body): array {
    $user=trim((string)($cfg['smtp_username']??''));$pass=smtp_password_decrypt();if($pass==='')throw new RuntimeException('App Password Gmail belum tersimpan. Konfigurasikan App Password SMTP pada konfigurasi server.');$from=trim((string)($cfg['from_email']??$user));
    if($user===''||!filter_var($user,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Akun Gmail SMTP belum dikonfigurasi dengan benar.');
    if(strtolower($from)!==strtolower($user))throw new RuntimeException('Email pengirim harus sama dengan akun Gmail SMTP: '.$user.'.');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Email tujuan tidak valid.');
    $c=smtp_open($cfg);$fp=$c['fp'];try{
        smtp_authenticate($fp,$c['auth_methods'],$user,$pass);smtp_command($fp,'MAIL FROM:<'.$from.'>','MAIL FROM',[250]);smtp_command($fp,'RCPT TO:<'.$to.'>','RCPT TO',[250,251]);smtp_command($fp,'DATA','DATA',[354]);
        $headers=['From: Administrator <'.$from.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(12)).'@gmail.com>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit'];
        $payload=implode("\r\n",$headers)."\r\n\r\n".preg_replace('/(?<!\r)\n/','\r\n',$body)."\r\n.\r\n";if(@fwrite($fp,$payload)===false)throw new RuntimeException('SMTP DATA gagal dikirim.');$r=smtp_read_response($fp,'isi email');if($r['code']!==250)throw new RuntimeException('Gmail menolak pesan ('.$r['code'].'). '.$r['text']);@fwrite($fp,"QUIT\r\n");return ['ok'=>true,'host'=>$c['host'],'port'=>$c['port'],'security'=>$c['security']];
    }finally{@fclose($fp);}
}
function send_system_email(string $to,string $subject,string $body): array {
    global $config;
    $base=(array)($config['mail']??[]);
    $currentPort=(int)($base['smtp_port']??465);
    $candidates=[$currentPort===587?587:465, $currentPort===587?465:587];
    $last=null;
    foreach(array_values(array_unique($candidates)) as $port){
        $cfg=$base; $cfg['smtp_port']=$port; $cfg['smtp_security']=$port===587?'tls':'ssl';
        try{
            $result=smtp_send($cfg,$to,$subject,$body);
            if($port!==$currentPort){
                $config['mail']['smtp_port']=$port; $config['mail']['smtp_security']=$cfg['smtp_security'];
                if(isset($GLOBALS['configFile'])) save_config($config);
            }
            return $result+['auto_fallback'=>($port!==$currentPort)];
        }catch(Throwable $e){$last=$e;if(!smtp_is_connection_failure($e) || $port===end($candidates)) throw $e;}
    }
    if($last) throw $last;
    throw new RuntimeException('Gmail SMTP tidak dapat digunakan.');
}
