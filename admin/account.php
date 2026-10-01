<?php
require_once __DIR__.'/_layout.php'; require_admin(); global $config;
$msg=''; $error=''; $a=current_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf();
  $newuser=trim((string)($_POST['username']??'')); $newemail=trim((string)($_POST['email']??''));
  $oldpin=(string)($_POST['old_pin']??''); $newpin=(string)($_POST['new_pin']??''); $newpin2=(string)($_POST['new_pin2']??'');
  if($newuser===''||mb_strlen($newuser)<3||mb_strlen($newuser)>64) throw new RuntimeException('Username harus 3-64 karakter.');
  if(!preg_match('/^[\p{L}\p{N}._+@ -]+$/u',$newuser)) throw new RuntimeException('Username mengandung karakter yang tidak didukung.');
  if(!filter_var($newemail,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email admin tidak valid.');
  if(!password_verify($oldpin,(string)$a['pin_hash'])) throw new RuntimeException('PIN saat ini salah.');
  if(!preg_match('/^\d{6}$/',$newpin)) throw new RuntimeException('PIN baru harus tepat 6 digit angka.');
  if($newpin!==$newpin2) throw new RuntimeException('Konfirmasi PIN baru tidak sama.');
  $config['admin']['username']=$newuser; $config['admin']['email']=$newemail; $config['admin']['pin_hash']=password_hash($newpin,PASSWORD_DEFAULT); $config['admin']['updated_at']=date('c');
  save_config($config); security_log('account_update',['username_changed'=>$newuser!==($a['username']??'')]); $_SESSION['admin_user']=$newuser; $a=$config['admin'];
  $msg='Akun berhasil diperbarui.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
admin_open('account','Akun Admin');
?>
<section class="account-page">
<div class="account-intro"><p class="account-eyebrow">KEAMANAN AKUN</p><h2>Kelola akun Administrator</h2><p>Perbarui identitas login dan PIN dari satu tempat. Perubahan diterapkan langsung ke akun yang sedang digunakan.</p></div>
<?php if($msg):?><p class="account-notice account-success" role="status"><?=e($msg)?></p><?php endif;?><?php if($error):?><p class="account-notice account-error" role="alert"><?=e($error)?></p><?php endif;?>
<form class="account-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>">
<fieldset class="account-panel"><legend>Identitas login</legend><p class="account-help">Gunakan username dan email yang masih dapat Anda akses untuk pemulihan PIN.</p><div class="grid-2">
<label for="username">Username<input id="username" name="username" value="<?=e((string)$a['username'])?>" maxlength="64" autocomplete="username" required></label>
<label for="email">Email pemulihan<input id="email" name="email" type="email" value="<?=e((string)($a['email']??''))?>" autocomplete="email" required></label>
</div></fieldset>
<fieldset class="account-panel"><legend>Ganti PIN</legend><p class="account-help">PIN harus tepat 6 digit angka. PIN saat ini diperlukan sebelum perubahan disimpan.</p><div class="grid-2">
<label for="old_pin">PIN saat ini<input id="old_pin" name="old_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password" required></label>
<label for="new_pin">PIN baru<input id="new_pin" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>
<label for="new_pin2">Ulangi PIN baru<input id="new_pin2" name="new_pin2" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required></label>
</div></fieldset>
<div class="account-actions"><button type="submit">Simpan Perubahan</button><a href="/admin/logout.php" class="account-secondary">Keluar dari Administrator</a></div>
</form></section>
<?php admin_close(); ?>
