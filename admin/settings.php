<?php
declare(strict_types=1);
require_once __DIR__.'/_layout.php';
require_admin();

$msg=''; $err='';
$s=array_merge(brand_defaults(),site_settings());

if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  check_csrf();
  foreach(['site_name','site_tagline','site_description','home_title','phone_display','whatsapp','gsc_verification_token','ga4_measurement_id'] as $k){
   if(array_key_exists($k,$_POST)) $s[$k]=trim((string)$_POST[$k]);
  }
  if($s['site_name']===''||mb_strlen($s['site_name'])>80) throw new RuntimeException('Nama website wajib diisi dan maksimal 80 karakter.');
  if(mb_strlen($s['site_tagline'])>120) throw new RuntimeException('Tagline maksimal 120 karakter.');
  if($s['site_description']===''||mb_strlen($s['site_description'])>320) throw new RuntimeException('Deskripsi website wajib diisi dan maksimal 320 karakter.');
  if($s['home_title']===''||mb_strlen($s['home_title'])>160) $s['home_title']=$s['site_name'].' | Layanan Prostetik dan Ortotik';
  if($s['phone_display']!==''&&mb_strlen($s['phone_display'])>32) throw new RuntimeException('Nomor telepon maksimal 32 karakter.');
  if($s['whatsapp']!==''){
   $wa=preg_replace('/\D+/','',$s['whatsapp'])??'';
   if(str_starts_with($wa,'0')) $wa='62'.substr($wa,1);
   if(!preg_match('/^62[0-9]{8,13}$/',$wa)) throw new RuntimeException('Nomor WhatsApp harus berupa nomor Indonesia yang valid.');
   $s['whatsapp']=$wa;
  }
  if($s['gsc_verification_token']!==''){ if(!preg_match('/^[A-Za-z0-9._-]{16,200}$/',$s['gsc_verification_token'])) throw new RuntimeException('Token verifikasi GSC tidak valid. Salin nilai content dari tag yang diberikan Search Console.'); }
  if($s['ga4_measurement_id']!==''){ $s['ga4_measurement_id']=strtoupper($s['ga4_measurement_id']); if(!preg_match('/^G-[A-Z0-9]+$/',$s['ga4_measurement_id'])) throw new RuntimeException('GA4 Measurement ID harus berformat G-XXXXXXXXXX.'); }

  $oldBrandAssets=[];
  foreach(['logo'=>'logo_url','favicon'=>'favicon_url','og_logo'=>'og_logo_url'] as $kind=>$key){
   if(isset($_FILES[$kind]) && (int)($_FILES[$kind]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
    $new=store_brand_asset($_FILES[$kind],$kind);
    if($new!==''){ $oldBrandAssets[]=(string)($s[$key]??''); $s[$key]=$new; }
   }
  }

  save_settings($s);
  foreach($oldBrandAssets as $oldAsset) delete_brand_asset($oldAsset);
  $report=publish_site_identity('brand','Update Brand Website');
  if(($report['files_failed']??0)>0) throw new RuntimeException('Brand tersimpan, tetapi '.(int)$report['files_failed'].' halaman gagal diperbarui. Periksa server sebelum melanjutkan.');
  security_log('brand_settings_publish',['files_changed'=>$report['files_changed']??0]);
    $msg='Brand berhasil disinkronkan ke website publik ('.(int)($report['files_changed']??0).' halaman diperbarui).';
 } catch(Throwable $e) { $err=$e->getMessage(); }
}
admin_open('settings','Brand Website');
?>
<section>
  <div class="admin-hero admin-hero-compact">
    <div>
      <p class="eyebrow">Brand Website</p>
      <h2>Satu tempat untuk identitas yang tampil di website.</h2>
      <p>Ubah identitas dan kontak utama. Saat disimpan, sistem langsung menyinkronkannya ke halaman publik dan metadata terkait.</p>
    </div>
  </div>

  <?php if($msg):?><p class="admin-notice" role="status"><?=e($msg)?></p><?php endif;?>
  <?php if($err):?><p class="admin-notice admin-notice-error" role="alert"><?=e($err)?></p><?php endif;?>

  <form method="post" class="admin-form" autocomplete="off" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=e(csrf())?>">

    <section class="admin-panel">
      <header><p class="eyebrow">Identitas</p><h3>Yang tampil di website</h3></header>
      <div class="grid-2">
        <label>Nama website<input name="site_name" value="<?=e($s['site_name'])?>" maxlength="80" required></label>
        <label>Tagline <span class="field-hint">Opsional</span><input name="site_tagline" value="<?=e($s['site_tagline'])?>" maxlength="120"></label>
        <label>Judul SEO homepage <span class="field-hint">Judul tab browser dan hasil pencarian</span><input name="home_title" value="<?=e($s['home_title'])?>" maxlength="160" required></label>
      </div>
      <label>Deskripsi website <span class="field-hint">Dipakai sebagai deskripsi homepage dan metadata sosial</span><textarea name="site_description" rows="4" maxlength="320" required><?=e($s['site_description'])?></textarea></label>
    </section>

    <section class="admin-panel">
      <header><p class="eyebrow">Aset Brand</p><h3>Logo, favicon &amp; Logo OG Image</h3><p class="field-hint">Upload sekali lalu simpan. Logo Website dipakai di header. Favicon dipakai pada tab browser. Logo OG Image adalah <strong>logo persegi 1:1</strong> yang disisipkan kecil di dalam frame gambar unggulan wilayah dan artikel. Ini bukan gambar OG sosial 1200×630 dan tidak dirender sebagai gambar baru di atas judul atau teks.</p></header>
      <div class="brand-asset-grid">
        <div class="brand-asset-card"><div class="brand-asset-preview brand-asset-logo"><img src="<?=e(site_logo_url())?>" alt="Logo website" loading="lazy"></div><strong>Logo Website</strong><small>PNG, JPG, WebP, atau SVG · maksimal 2 MB</small><label>Upload logo<input name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml"></label></div>
        <div class="brand-asset-card"><div class="brand-asset-preview brand-asset-favicon"><img src="<?=e(site_favicon_url())?>" alt="Favicon" loading="lazy"></div><strong>Favicon</strong><small>PNG, ICO, atau SVG · maksimal 1 MB</small><label>Upload favicon<input name="favicon" type="file" accept="image/png,image/x-icon,image/svg+xml"></label></div>
        <div class="brand-asset-card"><div class="brand-asset-preview brand-asset-og-logo"><img src="<?=e(site_og_logo_url())?>" alt="Logo OG Image" loading="lazy"></div><strong>Logo OG Image</strong><small>Logo persegi · rasio 1:1 · PNG, JPG, atau WebP · maksimal 2 MB</small><label>Upload Logo OG Image<input name="og_logo" type="file" accept="image/png,image/jpeg,image/webp"></label></div>
      </div>
      <p class="field-hint">Gambar unggulan wilayah dan artikel tetap memakai gambar sumber masing-masing. Sistem hanya menambahkan <strong>satu Logo OG Image persegi</strong> sebagai overlay kecil di dalam frame. Gambar OG sosial 1200×630 tetap terpisah dan tidak ditampilkan sebagai cover tambahan.</p>
    </section>

    <section class="admin-panel">
      <header><p class="eyebrow">Kontak</p><h3>Kontak yang digunakan pengunjung</h3></header>
      <div class="grid-2">
        <label>WhatsApp<input name="whatsapp" value="<?=e($s['whatsapp'])?>" inputmode="tel" placeholder="0853 9484 9766"></label>
        <label>Telepon <span class="field-hint">Opsional</span><input name="phone_display" value="<?=e($s['phone_display'])?>" inputmode="tel"></label>
      </div>
    </section>

    <section class="admin-panel">
      <header><p class="eyebrow">Pengukuran publik</p><h3>GSC &amp; GA4</h3><p class="field-hint">Konfigurasi ini hanya menyiapkan verifikasi Search Console dan tracking GA4 di website publik. Tidak ada OAuth Google Cloud dan tidak ada sinkronisasi API otomatis di server.</p></header>
      <div class="grid-2">
        <label>Token verifikasi Google Search Console <span class="field-hint">Tempel hanya nilai <code>content</code> dari meta <code>google-site-verification</code>.</span><input name="gsc_verification_token" value="<?=e((string)($s['gsc_verification_token']??''))?>" maxlength="200" autocomplete="off" placeholder="Token dari Search Console"></label>
        <label>GA4 Measurement ID <span class="field-hint">Format: G-XXXXXXXXXX. Jangan masukkan Property ID.</span><input name="ga4_measurement_id" value="<?=e((string)($s['ga4_measurement_id']??''))?>" maxlength="30" autocomplete="off" placeholder="G-XXXXXXXXXX"></label>
      </div>
      <div class="integration-status-grid">
        <div class="integration-status <?=gsc_verification_token()!==''?'is-good':'is-required'?>"><strong>GSC</strong><span><?=gsc_verification_token()!==''?'Tag siap disinkronkan':'Belum dikonfigurasi'?></span></div>
        <div class="integration-status <?=ga4_measurement_id()!==''?'is-good':'is-required'?>"><strong>GA4</strong><span><?=ga4_measurement_id()!==''?'Measurement ID aktif':'Belum dikonfigurasi'?></span></div>
      </div>
      <p class="field-hint">Search Console digunakan untuk verifikasi dan pemantauan langsung. GA4 tetap opsional untuk pengukuran perilaku pengunjung; keduanya tidak diperlukan untuk menerbitkan artikel.</p>
    </section>

    <div class="admin-form-actions"><button type="submit">Simpan &amp; Sinkronkan Brand</button></div>
  </form>

</section>
<?php admin_close(); ?>
