<?php
declare(strict_types=1);
require_once __DIR__.'/_layout.php';
require_admin();

$msg=''; $err='';
$articles=content_articles();
$editingSlug=content_slug((string)($_GET['slug']??$_POST['slug']??''));
$current=$editingSlug!=='' && isset($articles[$editingSlug]) ? $articles[$editingSlug] : [
 'title'=>'','slug'=>$editingSlug,'excerpt'=>'','body'=>'','body_mode'=>'visual','status'=>'draft','created_at'=>'','updated_at'=>'','published_at'=>'',
 'seo_title'=>'','meta_description'=>'','focus_keywords'=>'','geo_area'=>'','content_cluster'=>'kaki-palsu','search_intent'=>'informational','answer_question'=>'','answer'=>'','author'=>'','source_urls'=>'','featured_image'=>'','featured_image_alt'=>'','featured_image_caption'=>''
];

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf();
  $action=(string)($_POST['content_action']??'');
  if($action!=='save_draft' && $action!=='publish') throw new RuntimeException('Aksi artikel tidak dikenali.');
  $title=trim((string)($_POST['title']??''));
  $slug=content_slug((string)($_POST['slug']??''));
  $excerpt=trim((string)($_POST['excerpt']??''));
  $body=trim((string)($_POST['body']??''));
  $bodyMode=(string)($_POST['body_mode']??'visual')==='html'?'html':'visual';
  $seoTitle=trim((string)($_POST['seo_title']??''));
  $metaDescription=trim((string)($_POST['meta_description']??''));
  $focusKeywords=trim((string)($_POST['focus_keywords']??''));
  $geoArea=trim((string)($_POST['geo_area']??''));
  $contentCluster=trim((string)($_POST['content_cluster']??''));
  $searchIntent=trim((string)($_POST['search_intent']??''));
  $sourceUrls=trim((string)($_POST['source_urls']??''));
  $answerQuestion=trim((string)($_POST['answer_question']??''));
  $answer=trim((string)($_POST['answer']??''));
  $author=trim((string)($_POST['author']??''));
  $imageAlt=trim((string)($_POST['featured_image_alt']??''));
  $imageCaption=trim((string)($_POST['featured_image_caption']??''));
  if($title==='' || mb_strlen($title)>140) throw new RuntimeException('Judul artikel wajib diisi dan maksimal 140 karakter.');
  if($slug==='') $slug=content_slug($title);
  if($slug==='') throw new RuntimeException('Slug artikel tidak valid.');
  if(mb_strlen($excerpt)>320) throw new RuntimeException('Ringkasan maksimal 320 karakter.');
  if($seoTitle!=='' && mb_strlen($seoTitle)>70) throw new RuntimeException('SEO title maksimal 70 karakter.');
  if($metaDescription!=='' && mb_strlen($metaDescription)>170) throw new RuntimeException('Meta description maksimal 170 karakter.');
  if(mb_strlen($focusKeywords)>240) throw new RuntimeException('Kata kunci maksimal 240 karakter.');
  if(mb_strlen($geoArea)>240) throw new RuntimeException('Wilayah terkait maksimal 240 karakter.');
  if(!in_array($contentCluster,['kaki-palsu','amputasi-dan-rehabilitasi','diabetes-dan-amputasi','keluarga-pasien','wilayah-dan-layanan','tangan-palsu','jari-palsu','ortotik'],true)) throw new RuntimeException('Cluster konten tidak valid.');
  if(!in_array($searchIntent,['informational','problem-solution','commercial','local','navigational'],true)) throw new RuntimeException('Intent pencarian tidak valid.');
  if(mb_strlen($sourceUrls)>1200) throw new RuntimeException('Referensi sumber maksimal 1.200 karakter.');
  foreach(preg_split('/[,\n]+/u',$sourceUrls)?:[] as $sourceUrl){ if(trim($sourceUrl)!=='' && !preg_match('~^https?://~i',trim($sourceUrl))) throw new RuntimeException('Referensi sumber harus berupa URL https:// atau http://.'); }
  if(mb_strlen($answerQuestion)>180 || mb_strlen($answer)>700) throw new RuntimeException('Pertanyaan/jawaban singkat terlalu panjang.');
  if(mb_strlen($author)>100) throw new RuntimeException('Nama penulis maksimal 100 karakter.');
  if(mb_strlen($imageAlt)>160) throw new RuntimeException('Alt text gambar maksimal 160 karakter.');
  if(mb_strlen($imageCaption)>180) throw new RuntimeException('Keterangan gambar maksimal 180 karakter.');
  if($body==='') throw new RuntimeException('Isi artikel wajib diisi.');
  if(mb_strlen($body)>50000) throw new RuntimeException('Isi artikel maksimal 50.000 karakter.');
  $cleanBody=article_body_html($body);
  if($cleanBody==='') throw new RuntimeException('Isi artikel tidak memiliki konten yang dapat diterbitkan.');
  $oldSlug=content_slug((string)($_POST['old_slug']??''));
  if($oldSlug!=='' && $oldSlug!==$slug && isset($articles[$slug])) throw new RuntimeException('Slug sudah digunakan artikel lain.');
  $oldPublicSlug=$oldSlug;
  if($oldSlug!=='' && $oldSlug!==$slug && isset($articles[$oldSlug])) unset($articles[$oldSlug]);
  $now=date('c');
  $current=array_merge($current,[
    'title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'body'=>$cleanBody,'body_mode'=>$bodyMode,'seo_title'=>$seoTitle,'meta_description'=>$metaDescription!==''?$metaDescription:$excerpt,
    'focus_keywords'=>$focusKeywords,'geo_area'=>$geoArea,'content_cluster'=>$contentCluster,'search_intent'=>$searchIntent,'source_urls'=>$sourceUrls,'answer_question'=>$answerQuestion,'answer'=>$answer,'author'=>$author,'featured_image_alt'=>$imageAlt!==''?$imageAlt:$title,'featured_image_caption'=>$imageCaption,'updated_at'=>$now
  ]);
  if(isset($_FILES['featured_image']) && (int)($_FILES['featured_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
      $uploadedImage=store_article_featured_image($_FILES['featured_image'],$slug);
      $oldImage=(string)($current['featured_image']??'');
      $current['featured_image']=$uploadedImage;
      if($oldImage!=='' && $oldImage!==$uploadedImage) delete_article_featured_image($oldImage);
  }
  if(($current['created_at']??'')==='') $current['created_at']=$now;
  if(($current['featured_image']??'')!=='' && trim((string)$current['featured_image_alt'])==='') $current['featured_image_alt']=$title;
  if($action==='publish' && !article_has_featured_image($current)) throw new RuntimeException('Gambar unggulan wajib diunggah sebelum artikel diterbitkan.');
  if($action==='publish' && trim((string)$current['featured_image_alt'])==='') throw new RuntimeException('Alt text gambar wajib diisi sebelum artikel diterbitkan.');
  if($action==='save_draft'){
      $current['status']='draft';
      $articles[$slug]=$current; save_content_articles($articles); update_sitemap_for_articles($articles);
      if($oldPublicSlug!=='' && isset($articles[$oldPublicSlug])===false) article_delete_public($oldPublicSlug);
      article_delete_public($slug);
      @unlink(storage_file('cache/public-search-index.json')); @unlink(storage_file('cache/public-html-files.json'));
      security_log('article_draft',['slug'=>$slug,'title'=>$title]); $msg='Draft artikel tersimpan. Belum tampil di website publik.';
  } elseif($action==='publish'){
      $current['status']='published'; $current['published_at']=($current['published_at']??'')!=='' ? $current['published_at'] : $now;
      $articles[$slug]=$current; save_content_articles($articles); $url=publish_article($current); update_sitemap_for_articles($articles); @unlink(storage_file('cache/public-search-index.json')); @unlink(storage_file('cache/public-html-files.json')); security_log('article_publish',['slug'=>$slug,'title'=>$title,'url'=>$url]); $msg='Artikel berhasil diterbitkan ke '.$url;
      if($oldPublicSlug!=='' && $oldPublicSlug!==$slug) article_delete_public($oldPublicSlug);
  } else { throw new RuntimeException('Aksi konten tidak dikenali.'); }
  $editingSlug=$slug; $current=$articles[$slug];
 }catch(Throwable $e){$err=$e->getMessage();}
}
$articles=content_articles();
$hasTitle=trim((string)($current['title']??''))!=='';
$hasBody=trim((string)($current['body']??''))!=='';
$hasMeta=trim((string)($current['meta_description']??''))!=='' || trim((string)($current['excerpt']??''))!=='';
$hasImage=article_has_featured_image($current);
$hasAlt=$hasImage && trim((string)($current['featured_image_alt']??''))!=='';
$hasGeo=trim((string)($current['geo_area']??''))!=='';
$hasAeo=trim((string)($current['answer_question']??''))!=='' && trim((string)($current['answer']??''))!=='';
$hasCluster=trim((string)($current['content_cluster']??''))!==''; $hasIntent=trim((string)($current['search_intent']??''))!=='';
$hasSource=trim((string)($current['source_urls']??''))!=='';
$seoChecks=[$hasTitle,$hasMeta,$hasBody,$hasImage,$hasAlt,$hasCluster,$hasIntent];
$seoReady=!in_array(false,$seoChecks,true);
$geoReady=$hasGeo;
$aeoReady=$hasAeo;
$gscReady=is_file(app_root().'/sitemap.xml') && is_file(app_root().'/robots.txt') && gsc_verification_token()!=='';
$ga4Ready=ga4_measurement_id()!=='';
$score=array_sum(array_map(static fn($v)=>(bool)$v,$seoChecks));
admin_open('publish','Artikel');
?>
<section>
  <div class="admin-hero admin-hero-compact">
    <div>
      <p class="eyebrow">Artikel</p>
      <h2>Terbitkan artikel dengan cara yang Anda pilih.</h2>
      <p>Gunakan <strong>Editor Visual</strong> untuk menulis seperti CMS, atau <strong>HTML</strong> jika Anda membutuhkan kontrol kode langsung.</p>
    </div>
  </div>
  <?php if($msg):?><p class="admin-notice" role="status"><?=e($msg)?></p><?php endif;?>
  <?php if($err):?><p class="admin-notice admin-notice-error" role="alert"><?=e($err)?></p><?php endif;?>

  <section class="article-readiness-panel" aria-labelledby="article-readiness-title">
    <div class="article-readiness-head"><div><p class="eyebrow">Quality Gate</p><h3 id="article-readiness-title">Kesiapan artikel</h3><p>Hijau berarti pemeriksaan teknis lokal terpenuhi. Ini <strong>bukan jaminan peringkat Google</strong> atau hasil analitik.</p></div><strong class="article-readiness-score"><?=e((string)$score)?>/7 SEO teknis</strong></div>
    <div class="article-quality-grid">
      <div class="quality-item <?= $seoReady?'is-good':'is-required'?>"><span class="quality-dot" aria-hidden="true"></span><div><strong>SEO teknis</strong><small><?= $seoReady?'Lengkap secara teknis':'Wajib dilengkapi sebelum publish'?></small></div></div>
      <div class="quality-item <?= $geoReady?'is-good':'is-required'?>"><span class="quality-dot" aria-hidden="true"></span><div><strong>GEO</strong><small><?= $geoReady?'Area layanan terdefinisi':'Tambahkan wilayah terkait bila artikel bersifat lokal'?></small></div></div>
      <div class="quality-item <?= $aeoReady?'is-good':'is-required'?>"><span class="quality-dot" aria-hidden="true"></span><div><strong>AEO</strong><small><?= $aeoReady?'Pertanyaan + jawaban tersedia':'Tambahkan pertanyaan dan jawaban langsung'?></small></div></div>
      <div class="quality-item <?= $gscReady?'is-good':'is-required'?>"><span class="quality-dot" aria-hidden="true"></span><div><strong>GSC</strong><small><?= $gscReady?'Sitemap + robots + token siap':'Konfigurasi GSC belum lengkap'?></small></div></div>
      <div class="quality-item <?= $ga4Ready?'is-good':'is-required'?>"><span class="quality-dot" aria-hidden="true"></span><div><strong>GA4</strong><small><?= $ga4Ready?'Measurement ID valid':'Tambahkan Measurement ID di Brand Website'?></small></div></div>
    </div>
    <div class="quality-note"><strong>Prioritas:</strong> isi judul, ringkasan, isi, gambar + alt, cluster + intent, lalu GEO/AEO. Referensi sumber sangat dianjurkan untuk artikel kesehatan. GSC dan GA4 adalah alat pengukuran/validasi eksternal, bukan skor kualitas isi artikel.</div>
  </section>

  <section class="admin-panel">
    <header><p class="eyebrow">Editor Artikel</p><h3><?=($editingSlug!==''?'Edit artikel':'Artikel baru')?></h3></header>
    <form method="post" class="admin-form article-editor-form" enctype="multipart/form-data" data-article-editor>
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="old_slug" value="<?=e($editingSlug)?>">
      <input type="hidden" name="body_mode" value="<?=e((string)($current['body_mode']??'visual'))?>" data-editor-mode>
      <label>Judul artikel<input name="title" maxlength="140" required value="<?=e((string)$current['title'])?>" placeholder="Contoh: Persiapan konsultasi kaki palsu sebelum datang"></label>
      <label>Slug URL <span class="field-hint">huruf kecil, angka, dan tanda hubung</span><input name="slug" maxlength="80" value="<?=e((string)$current['slug'])?>" placeholder="persiapan-konsultasi-kaki-palsu"></label>
      <label>Ringkasan <span class="field-hint">maksimal 320 karakter; dipakai sebagai deskripsi artikel</span><textarea name="excerpt" rows="3" maxlength="320" placeholder="Ringkasan yang langsung menjawab apa yang pembaca akan dapatkan."><?=e((string)$current['excerpt'])?></textarea></label>

      <div class="article-editor-section">
        <div class="article-editor-heading"><div><p class="eyebrow">Isi artikel</p><h4>Editor</h4><small class="field-hint">Tulis visual seperti CMS atau pindah ke HTML untuk kontrol struktur.</small></div><div class="article-editor-tabs" role="tablist" aria-label="Pilih mode editor"><button type="button" class="article-editor-tab" data-editor-tab="visual" aria-selected="false">Editor Visual</button><button type="button" class="article-editor-tab" data-editor-tab="html" aria-selected="false">HTML Source</button><button type="button" class="article-editor-focus" data-editor-focus aria-pressed="false">Fokus</button></div></div>
        <div class="article-editor-toolbar" aria-label="Format teks"><button type="button" data-editor-command="bold" title="Tebal" aria-label="Tebal"><strong>B</strong></button><button type="button" data-editor-command="italic" title="Miring" aria-label="Miring"><em>I</em></button><button type="button" data-editor-command="formatBlock" data-format="h2" title="Judul bagian" aria-label="Judul bagian">H2</button><button type="button" data-editor-command="insertUnorderedList" title="Daftar" aria-label="Daftar">Daftar</button><button type="button" data-editor-command="insertOrderedList" title="Daftar bernomor" aria-label="Daftar bernomor">1.</button><button type="button" data-editor-command="blockquote" title="Kutipan" aria-label="Kutipan">Kutipan</button><button type="button" data-editor-command="createLink" title="Tautan" aria-label="Tautan">Tautan</button></div>
        <div class="article-editor-visual" contenteditable="true" role="textbox" aria-multiline="true" data-editor-visual></div>
        <textarea class="article-editor-source" name="body" rows="20" maxlength="50000" required data-editor-source placeholder="Tulis HTML artikel di sini. Contoh: <h2>Persiapan sebelum konsultasi</h2><p>...</p>"><?=e((string)$current['body'])?></textarea>
        <div class="article-editor-status"><span data-editor-count>0 kata · 0 karakter</span><span data-editor-state>Editor Visual aktif</span></div><p class="field-hint">Editor Visual menyimpan HTML bersih secara otomatis. Mode HTML memberi kontrol langsung atas struktur artikel. Tag berbahaya akan dibersihkan server saat disimpan.</p>
      </div>

      <div class="article-editor-section">
        <div class="article-editor-heading"><div><p class="eyebrow">Media</p><h4>Gambar artikel</h4></div><small class="field-hint">Gunakan gambar yang relevan, tajam, dan tidak berlebihan.</small></div>
        <label>File gambar <span class="field-hint">JPG, PNG, atau WebP · maksimal 3 MB · minimal 640×360 px</span><input name="featured_image" type="file" accept="image/jpeg,image/png,image/webp"></label>
        <?php $featuredPreview=article_featured_image_url($current); if($featuredPreview!==''):?><div class="article-admin-preview"><img src="<?=e($featuredPreview)?>" alt="<?=e((string)($current['featured_image_alt']??$current['title']))?>" loading="lazy"><div><strong><?=article_has_featured_image($current)?'Gambar unggulan tersimpan':'Gambar default website'?></strong><small><?=article_has_featured_image($current)?'Upload file baru untuk menggantinya.':'Upload gambar sendiri agar SEO gambar dapat diperiksa penuh.'?></small></div></div><?php endif;?>
        <label>Alt text gambar <span class="field-hint">Jelaskan isi gambar secara singkat; dipakai untuk aksesibilitas dan SEO gambar.</span><input name="featured_image_alt" maxlength="160" value="<?=e((string)($current['featured_image_alt']??''))?>" placeholder="Contoh: Proses konsultasi kaki palsu di ruang layanan"></label>
        <label>Keterangan gambar <span class="field-hint">Opsional; hanya tampil jika memang membantu konteks.</span><input name="featured_image_caption" maxlength="180" value="<?=e((string)($current['featured_image_caption']??''))?>" placeholder="Contoh: Konsultasi dimulai dari kebutuhan dan aktivitas pengguna."></label>
      </div>

      <div class="article-editor-section">
        <div class="article-editor-heading"><div><p class="eyebrow">SEO · GEO · AEO</p><h4>Optimasi pencarian</h4></div><small class="field-hint">Field ini diterapkan otomatis ke HTML, metadata, dan structured data.</small></div>
        <label>SEO title <span class="field-hint">maksimal 70 karakter; kosongkan untuk memakai judul artikel</span><input name="seo_title" maxlength="70" value="<?=e((string)($current['seo_title']??''))?>" placeholder="Judul yang paling jelas untuk hasil pencarian"></label>
        <label>Meta description <span class="field-hint">maksimal 170 karakter; kosongkan untuk memakai ringkasan</span><textarea name="meta_description" rows="3" maxlength="170" placeholder="Jawaban singkat yang menjelaskan isi artikel dan manfaatnya."><?=e((string)($current['meta_description']??''))?></textarea></label>
        <label>Topik utama <span class="field-hint">pisahkan dengan koma; dipakai sebagai konteks artikel dan structured data, bukan jaminan ranking</span><input name="focus_keywords" maxlength="240" value="<?=e((string)($current['focus_keywords']??''))?>" placeholder="kaki palsu, konsultasi kaki palsu, Makassar"></label>
        <label>Wilayah terkait <span class="field-hint">GEO: pisahkan dengan koma; digunakan sebagai konteks area layanan pada structured data</span><input name="geo_area" maxlength="240" value="<?=e((string)($current['geo_area']??''))?>" placeholder="Makassar, Sulawesi Selatan, Indonesia"></label>
        <label>Nama penulis <span class="field-hint">opsional; kosongkan untuk memakai nama website</span><input name="author" maxlength="100" value="<?=e((string)($current['author']??''))?>" placeholder="Nama penulis atau tim layanan"></label>
        <div class="grid-2"><label>Cluster konten <span class="field-hint">Kelompok topik utama untuk internal linking dan arsitektur konten.</span><select name="content_cluster"><option value="kaki-palsu" <?=((string)($current['content_cluster']??'')==='kaki-palsu'?'selected':'')?>>Kaki palsu</option><option value="amputasi-dan-rehabilitasi" <?=((string)($current['content_cluster']??'')==='amputasi-dan-rehabilitasi'?'selected':'')?>>Amputasi & rehabilitasi</option><option value="diabetes-dan-amputasi" <?=((string)($current['content_cluster']??'')==='diabetes-dan-amputasi'?'selected':'')?>>Diabetes & amputasi</option><option value="keluarga-pasien" <?=((string)($current['content_cluster']??'')==='keluarga-pasien'?'selected':'')?>>Keluarga pasien</option><option value="wilayah-dan-layanan" <?=((string)($current['content_cluster']??'')==='wilayah-dan-layanan'?'selected':'')?>>Wilayah & layanan</option><option value="tangan-palsu" <?=((string)($current['content_cluster']??'')==='tangan-palsu'?'selected':'')?>>Tangan palsu</option><option value="jari-palsu" <?=((string)($current['content_cluster']??'')==='jari-palsu'?'selected':'')?>>Jari palsu</option><option value="ortotik" <?=((string)($current['content_cluster']??'')==='ortotik'?'selected':'')?>>Ortotik</option></select></label><label>Search intent <span class="field-hint">Tujuan pencarian yang dilayani artikel.</span><select name="search_intent"><option value="informational" <?=((string)($current['search_intent']??'')==='informational'?'selected':'')?>>Informational</option><option value="problem-solution" <?=((string)($current['search_intent']??'')==='problem-solution'?'selected':'')?>>Problem / solusi</option><option value="commercial" <?=((string)($current['search_intent']??'')==='commercial'?'selected':'')?>>Commercial</option><option value="local" <?=((string)($current['search_intent']??'')==='local'?'selected':'')?>>Local</option><option value="navigational" <?=((string)($current['search_intent']??'')==='navigational'?'selected':'')?>>Navigational</option></select></label></div>
        <label>Referensi sumber <span class="field-hint">Opsional tetapi sangat dianjurkan untuk artikel kesehatan. Pisahkan URL dengan koma/baris baru.</span><textarea name="source_urls" rows="3" maxlength="1200" placeholder="https://www.who.int/...
https://... "><?=e((string)($current['source_urls']??''))?></textarea></label>
        <div class="article-answer-fields"><label>Pertanyaan utama <span class="field-hint">AEO: pertanyaan yang dijawab artikel</span><input name="answer_question" maxlength="180" value="<?=e((string)($current['answer_question']??''))?>" placeholder="Apa yang perlu disiapkan sebelum konsultasi?"></label><label>Jawaban singkat <span class="field-hint">AEO: jawaban langsung 1–4 kalimat; ditampilkan di artikel</span><textarea name="answer" rows="4" maxlength="700" placeholder="Jawab langsung sebelum penjelasan panjang."><?=e((string)($current['answer']??''))?></textarea></label></div>
      </div>

      <div class="admin-form-actions"><button type="submit" name="content_action" value="save_draft">Simpan Draft</button><button type="submit" name="content_action" value="publish">Terbitkan Artikel</button><?php if($editingSlug!==''):?><a class="secondary-action" href="/admin/publish.php">Artikel Baru</a><?php endif;?></div>
    </form>
  </section>

  <section class="admin-panel"><header><p class="eyebrow">Tersimpan</p><h3>Artikel yang sudah dibuat</h3></header>
    <?php if(!$articles):?><p>Belum ada artikel. Buat artikel pertama dari editor di atas.</p><?php else:?>
    <div class="admin-table-wrap"><table><thead><tr><th>Artikel</th><th>Status</th><th>Update</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($articles as $article): $slug=content_slug((string)($article['slug']??''));?>
      <tr><td><?php $listImage=article_featured_image_url($article); if($listImage!==''):?><img class="article-list-thumb" src="<?=e($listImage)?>" alt="<?=e((string)($article['featured_image_alt']??$article['title']))?>" loading="lazy"><?php endif;?><strong><?=e((string)($article['title']??'-'))?></strong><br><small>/artikel/<?=e($slug)?>/ · <?=e((string)($article['content_cluster']??'kaki-palsu'))?> · <?=e((string)($article['search_intent']??'informational'))?></small></td><td><?=($article['status']??'draft')==='published'?'Terbit':'Draft'?></td><td><?=e((string)($article['updated_at']??'-'))?></td><td><a href="/admin/publish.php?slug=<?=e(rawurlencode($slug))?>">Edit</a><?php if(($article['status']??'')==='published'):?> · <a href="/artikel/<?=e($slug)?>/" target="_blank" rel="noopener">Lihat</a><?php endif;?></td></tr>
    <?php endforeach;?>
    </tbody></table></div>
    <?php endif;?>
  </section>

</section>
<script src="/assets/js/article-editor.js" defer></script>
<?php admin_close(); ?>
