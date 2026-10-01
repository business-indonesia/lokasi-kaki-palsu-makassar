<?php
declare(strict_types=1);

// Public search is intentionally independent from app/config.php.
// The public site must remain searchable even when the private installation
// configuration has not yet been created or is unavailable.
$root = dirname(__DIR__);
$q = trim((string)($_GET['q'] ?? ''));
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$site = 'Pusat Layanan Pembuatan Kaki Palsu';
$base = 'https://lokasi.kakitanganpalsumakassar.com/cari/';
$ga4=''; $settingsFile=$root.'/storage/settings.json'; if(is_file($settingsFile)){ $settingsData=json_decode((string)@file_get_contents($settingsFile),true); if(is_array($settingsData)) $ga4=strtoupper(trim((string)($settingsData['ga4_measurement_id']??''))); }
if(!preg_match('/^G-[A-Z0-9]+$/',$ga4)) $ga4='';

function search_text(string $s): string {
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}
function search_items(string $root): array {
    $cache=$root.'/storage/cache/public-search-index.json';
    if(is_file($cache) && (time()-(int)@filemtime($cache))<300){
        $cached=json_decode((string)@file_get_contents($cache),true);
        if(is_array($cached)) return $cached;
    }
    $items = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') continue;
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/'));
        if ($rel === '' || str_starts_with($rel, 'admin/') || str_starts_with($rel, 'storage/') || str_starts_with($rel, 'tools/')) continue;
        if (in_array($rel, ['403.html','404.html','maintenance.html'], true)) continue;
        $html = (string)@file_get_contents($path);
        if ($html === '') continue;
        $title=''; $desc=''; $h1='';
        if (preg_match('~<title>\s*(.*?)\s*</title>~is', $html, $m)) $title = search_text($m[1]);
        if (preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $m)) $desc = search_text($m[1]);
        if (preg_match('~<h1[^>]*>\s*(.*?)\s*</h1>~is', $html, $m)) $h1 = search_text($m[1]);
        $url = $rel === 'index.html' ? '/' : '/'.preg_replace('~/index\.html$~', '', $rel);
        $url = rtrim($url, '/').'/';
        if ($url === '//') $url = '/';
        $items[] = [
            'title' => $h1 !== '' ? $h1 : ($title !== '' ? $title : 'Halaman'),
            'description' => $desc,
            'url' => $url,
        ];
    }
    usort($items, static fn($a,$b) => strcmp($a['url'], $b['url']));
    $seen=[]; $out=[];
    foreach ($items as $item) {
        if (isset($seen[$item['url']])) continue;
        $seen[$item['url']] = true;
        $out[] = $item;
    }
    @mkdir(dirname($cache),0750,true);
    @file_put_contents($cache,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    return $out;
}

$items = search_items($root);
$results = [];
if ($q !== '') {
    $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($items as $item) {
        $hay = strtolower($item['title'].' '.$item['description'].' '.$item['url']);
        $score = 0; $ok = true;
        foreach ($terms as $term) {
            $needle = strtolower($term);
            if (stripos($hay, $needle) === false) { $ok=false; break; }
            $score += stripos(strtolower($item['title']), $needle) !== false ? 5 : 1;
        }
        if ($ok) { $item['_score']=$score; $results[]=$item; }
    }
    usort($results, static fn($a,$b) => ($b['_score']??0) <=> ($a['_score']??0));
    foreach($results as &$item) unset($item['_score']);
    unset($item);
    $results = array_slice($results, 0, 50);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Cari Wilayah dan Layanan | <?=$e($site)?></title>
<meta name="description" content="Cari wilayah, layanan, dan panduan di direktori Pusat Layanan Pembuatan Kaki Palsu.">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<link rel="canonical" href="<?=$e($base)?>">
<link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="any">
<link rel="icon" href="/favicon-48.png" type="image/png" sizes="48x48">
<link rel="apple-touch-icon" href="/favicon-180.png" sizes="180x180">
<link rel="stylesheet" href="/assets/css/minimal.css">
<link rel="manifest" href="/manifest.webmanifest">
</head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<div class="site-app">
<header class="app-topbar">
  <a class="topbar-brand" href="/" aria-label="Pusat Layanan Pembuatan Kaki Palsu"><img src="/assets/images/brand-mark.svg" alt="" width="40" height="40"><span>Pusat Layanan</span></a>
  <input class="search-switch" id="site-search-toggle" type="checkbox"><label class="search-toggle" for="site-search-toggle" aria-label="Tampilkan atau sembunyikan pencarian" title="Tampilkan / sembunyikan pencarian"><span aria-hidden="true">⌕</span><span class="search-toggle-text">Cari</span></label><form class="topbar-search" action="/cari/" method="get" role="search"><label class="sr-only" for="global-q">Cari wilayah atau layanan</label><span class="search-mark" aria-hidden="true">⌕</span><input id="global-q" name="q" type="search" value="<?=$e($q)?>" placeholder="Cari wilayah atau layanan" autocomplete="on" list="public-search-suggestions"><button class="search-submit" type="submit" aria-label="Mulai pencarian">Cari</button></form>
  <details class="mobile-nav-toggle"><summary aria-label="Buka menu">Menu</summary><nav aria-label="Navigasi utama"><a href="/"><span class="nav-mark" aria-hidden="true">01</span><span>Beranda</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#layanan"><span class="nav-mark" aria-hidden="true">02</span><span>Layanan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#proses"><span class="nav-mark" aria-hidden="true">03</span><span>Proses</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/panduan/"><span class="nav-mark" aria-hidden="true">04</span><span>Panduan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/lokasi-pelayanan/sulawesi-selatan/"><span class="nav-mark" aria-hidden="true">05</span><span>Wilayah</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/cari/" aria-current="page"><span class="nav-mark" aria-hidden="true">06</span><span>Cari</span><span class="nav-arrow" aria-hidden="true">›</span></a></nav><a class="mobile-cta" href="https://wa.me/6285394849766?text=Halo%2C%20saya%20ingin%20konsultasi%20mengenai%20prostesis%20atau%20alat%20bantu." target="_blank" rel="noopener noreferrer">Konsultasi WhatsApp</a></details>
</header>
<aside class="site-sidebar" aria-label="Navigasi situs">
  <div class="sidebar-heading"><span>Menu</span><span class="sidebar-count" aria-hidden="true">06</span></div>
  <nav aria-label="Navigasi utama"><a href="/"><span class="nav-mark" aria-hidden="true">01</span><span>Beranda</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#layanan"><span class="nav-mark" aria-hidden="true">02</span><span>Layanan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#proses"><span class="nav-mark" aria-hidden="true">03</span><span>Proses</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/panduan/"><span class="nav-mark" aria-hidden="true">04</span><span>Panduan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/lokasi-pelayanan/sulawesi-selatan/"><span class="nav-mark" aria-hidden="true">05</span><span>Wilayah</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/cari/" aria-current="page"><span class="nav-mark" aria-hidden="true">06</span><span>Cari</span><span class="nav-arrow" aria-hidden="true">›</span></a></nav>
  <div class="sidebar-help"><small>Butuh arahan?</small><a href="https://wa.me/6285394849766?text=Halo%2C%20saya%20ingin%20konsultasi%20mengenai%20prostesis%20atau%20alat%20bantu." target="_blank" rel="noopener noreferrer">Konsultasi WhatsApp</a></div>
  <small class="sidebar-meta">Prostetik · ortotik</small>
</aside>
<div class="site-content">
<header class="page-header"><p>Cari wilayah dan layanan</p></header>
<main id="main-content">
<nav aria-label="Breadcrumb"><a href="/">Beranda</a> <span aria-hidden="true">/</span> <span aria-current="page">Pencarian</span></nav>
<h1 id="search-title">Cari wilayah dan layanan</h1>
<section class="search-shell" aria-labelledby="search-title">
<form action="/cari/" method="get" role="search">
<label class="sr-only" for="search-q">Cari wilayah atau layanan</label>
<input id="search-q" name="q" type="search" value="<?=$e($q)?>" placeholder="Cari wilayah, layanan, atau panduan…" autocomplete="on" list="public-search-suggestions" autofocus>
<button class="search-submit" type="submit">Cari</button>
</form>
<p class="search-meta">Ketik kata kunci lalu tekan Enter. Saran pencarian muncul otomatis dari halaman publik.</p>
</section>
<datalist id="public-search-suggestions">
<?php foreach(array_slice($items,0,80) as $suggestion): ?>
<option value="<?=$e((string)$suggestion['title'])?>"></option>
<?php endforeach; ?>
</datalist>
<?php if($q===''): ?>
<p>Masukkan nama wilayah, layanan, atau panduan.</p>
<?php elseif(!$results): ?>
<p>Tidak ada hasil untuk <strong><?=$e($q)?></strong>.</p>
<p><a href="/lokasi-pelayanan/sulawesi-selatan/">Lihat direktori wilayah</a></p>
<?php else: ?>
<p class="search-meta">Ditemukan <?=count($results)?> hasil untuk <strong><?=$e($q)?></strong>.</p>
<ol class="search-results">
<?php foreach($results as $item): ?>
<li><a class="search-result" href="<?=$e((string)$item['url'])?>"><strong><?=$e((string)$item['title'])?></strong><small><?=$e((string)$item['description'])?></small></a></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
</main>
<footer><div><strong><?=$e($site)?></strong><small>Kaki palsu · tangan palsu · jari palsu · alat bantu ortotik</small></div><nav aria-label="Informasi"><a href="/kebijakan-privasi.html">Privasi</a><a href="/syarat-ketentuan.html">Syarat &amp; Ketentuan</a><a href="/hak-cipta.html">Hak Cipta</a></nav><small>© 2026 Pusat Layanan Pembuatan Kaki Palsu. Informasi ini membantu persiapan konsultasi dan bukan pengganti pemeriksaan tenaga kesehatan.</small></footer>
</div>
</div>
<?php if($ga4!==''): ?><script src="/assets/js/analytics.js" data-ga4="<?=$e($ga4)?>" defer></script><?php endif; ?>
</body>
</html>
