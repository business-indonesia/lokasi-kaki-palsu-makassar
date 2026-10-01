<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/_auth.php';
require_once __DIR__.'/../app/rate-limit.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$msg=''; $error='';
$recoveryRl=['allowed'=>true,'retry_after'=>0];
$locked=false;

$nonce=(string)($_COOKIE['lokasi_recovery_csrf']??'');
if(!preg_match('/^[a-f0-9]{64}\.[a-f0-9]{64}$/',$nonce)){
  $raw=bin2hex(random_bytes(32));
  $nonce=$raw.'.'.hash_hmac('sha256',$raw,(string)$config['admin']['session_secret']);
  setcookie('lokasi_recovery_csrf',$nonce,['expires'=>time()+1800,'path'=>'/admin/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $recoveryRl=rl_check('recovery',3,900,6); $locked=!$recoveryRl['allowed'];
  if($locked){
    $wait=max(1,(int)$recoveryRl['retry_after']);
    $error='Permintaan pemulihan baru saja berhasil diproses. Demi keamanan, tunggu sekitar '.ceil($wait/60).' menit sebelum meminta PIN lagi.';
  } else {
    try{
      $provided=(string)($_POST['recovery_csrf']??''); $parts=explode('.',$provided,2);
      $valid=count($parts)===2 && hash_equals($nonce,$provided) && hash_equals(hash_hmac('sha256',$parts[0],(string)$config['admin']['session_secret']),$parts[1]);
      $email=trim((string)($_POST['email']??'')); $adminEmail=(string)($config['admin']['email']??'');
      $verifiedAt=(string)($config['mail']['smtp_verified_at']??'');
      $verifiedTo=strtolower(trim((string)($config['mail']['smtp_verified_to']??'')));
      $smtpVerified=$verifiedAt!=='' && $verifiedTo!=='' && filter_var($verifiedTo,FILTER_VALIDATE_EMAIL) && hash_equals($verifiedTo,strtolower($adminEmail));
      if(!$valid) $error='Sesi pemulihan kedaluwarsa. Muat ulang halaman lalu coba lagi.';
      elseif(!$smtpVerified) $error='Layanan email pemulihan belum siap. PIN lama tetap aman. Hubungi administrator server untuk memastikan SMTP pemulihan akun telah dikonfigurasi.';
      elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||!hash_equals(strtolower($adminEmail),strtolower($email))) $error='Email tidak cocok dengan email admin yang terdaftar.';
      else {
        $newPin=(string)random_int(100000,999999); $site=(string)($config['site']['name']??'Administrator');
        $systemFrom=(string)($config['mail']['from_email']??'');
        $systemName=(string)($config['mail']['from_name']??'Administrator');
        $subject='PIN Admin Baru - '.$site;
        $message="Halo Admin,\n\nPermintaan Lupa PIN telah diproses oleh sistem.\n\nUsername: ".(string)$config['admin']['username']."\nPIN baru: ".$newPin."\n\nSilakan login menggunakan PIN baru. Jika Anda tidak meminta perubahan ini, segera periksa keamanan akun admin.\n\nEmail pengirim sistem: ".$systemFrom."\n\nSalam,\n".$systemName;
        try { $mailReport=send_system_email($adminEmail,$subject,$message); }
        catch(Throwable $mailEx){
          @file_put_contents(storage_file('logs/recovery.log'),date('c')." | pin_reset_email_failed | sender=".$systemFrom." | recipient=".$adminEmail." | error=".str_replace(["\r","\n"],' ',$mailEx->getMessage())."\n",FILE_APPEND|LOCK_EX);
          $error='Email pemulihan belum terkirim, jadi PIN lama tetap aman. Periksa konfigurasi SMTP server lalu coba lagi.';
        }
        if($error==='') {
          $oldHash=(string)$config['admin']['pin_hash'];
          $config['admin']['pin_hash']=password_hash($newPin,PASSWORD_DEFAULT); $config['admin']['updated_at']=date('c');
          try { save_config($config); }
          catch(Throwable $saveEx){
            $config['admin']['pin_hash']=$oldHash; $config['admin']['updated_at']=date('c');
            @file_put_contents(storage_file('logs/recovery.log'),date('c')." | pin_reset_config_save_failed | sender=".$systemFrom." | recipient=".$adminEmail." | error=".str_replace(["\r","\n"],' ', $saveEx->getMessage())."\n",FILE_APPEND|LOCK_EX);
            $error='Email sudah diterima server, tetapi PIN belum dapat disimpan. Jangan meminta PIN lagi. Periksa permission app/config.php lalu coba login dengan PIN lama.';
          }
          if($error===''){
            @file_put_contents(storage_file('logs/recovery.log'),date('c')." | pin_reset_email_sent | sender=".$systemFrom." | recipient=".$adminEmail."\n",FILE_APPEND|LOCK_EX);
            $msg='PIN baru sudah dikirim ke email admin. Periksa inbox dan folder spam. Pengirim: '.$systemFrom.'.';
          }
        }
      }
    }catch(Throwable $ex){
      $error='Pemulihan PIN gagal diproses. PIN lama tetap aman. Periksa konfigurasi SMTP server dan alamat email admin, lalu coba lagi.';
      @file_put_contents(storage_file('logs/recovery.log'),date('c')." | recovery_exception | ".str_replace(["\r","\n"],' ', $ex->getMessage())."\n",FILE_APPEND|LOCK_EX);
    }
  }
}
?><?php auth_head('Lupa PIN'); auth_open('/admin/login.php','Kembali ke login'); ?><section><div><div>Pemulihan Akun</div><h1>Lupa PIN?</h1><p>Masukkan email admin yang terdaftar. Sistem akan membuat PIN baru dan mengirim <strong>username + PIN</strong> ke email tersebut.</p><div><strong>Pengirim sistem: <?=e((string)($config['mail']['from_email']??'belum diatur'))?></strong><span>Email sistem digunakan untuk pemulihan akun. Lupa PIN hanya aktif setelah Tes Email berhasil.</span></div></div><div><div>Kirim PIN Baru</div><?php if($msg):?><div role="status"><?=e($msg)?></div><a href="/admin/login.php">KEMBALI KE LOGIN</a><?php else:?><?php if($error):?><div role="alert"><?=e($error)?></div><?php endif;?><form method="post" autocomplete="off"><input type="hidden" name="recovery_csrf" value="<?=e($nonce)?>"><label><span>Email Admin</span><input type="email" name="email" autocomplete="email" placeholder="email admin Anda" required></label><button type="submit" <?=($locked?'disabled':'')?>><?=($locked?'TUNGGU SEBENTAR':'KIRIM KE EMAIL')?></button></form><div><a href="/admin/login.php">Kembali ke Login</a></div><?php endif;?></div></section><?php auth_close('© '.date('Y').' Administrator - Pemulihan PIN otomatis via email'); ?>