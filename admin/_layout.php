<?php
if (basename((string)($_SERVER['SCRIPT_FILENAME']??'')) === '_layout.php') { http_response_code(404); exit; }
?>
<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/site.php';
require_once __DIR__.'/../app/security.php';
function admin_head(string $title): void {
    $GLOBALS['_admin_request_start']=microtime(true);
    $name=site_setting('site_name','Lokasi Pelayanan Kaki Palsu');
    $favicon=site_favicon_url();
    $faviconType=site_favicon_type($favicon);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="robots" content="noindex,nofollow,noarchive"><title>'.e($title.' - '.$name).'</title><link rel="icon" type="'.e($faviconType).'" href="'.e($favicon).'">
<link rel="stylesheet" href="/assets/css/minimal.css">
<link rel="manifest" href="/manifest.webmanifest">
</head><body>';
}

function admin_nav(string $active='publish'): void {
    $items=[
        ['settings','Brand Website','/admin/settings.php'],
        ['publish','Artikel','/admin/publish.php'],
        ['account','Akun Admin','/admin/account.php'],
    ];
    echo '<aside class="admin-sidebar" aria-label="Administrator"><div class="sidebar-heading"><span>Administrator</span></div><nav aria-label="Navigasi admin">';
    foreach($items as [$key,$label,$href]){
        $current=$active===$key?' aria-current="page"':'';
        echo '<a href="'.e($href).'"'.$current.'>'.e($label).'</a>';
    }
    echo '</nav><div class="sidebar-help"><small>Website publik</small><a href="/" target="_blank" rel="noopener">Lihat Website</a></div><form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button type="submit">Keluar</button></form></aside>';
}

function admin_open(string $active,string $title): void {
    global $admin_active;
    $allowed=['settings','publish','account'];
    if(!in_array($active,$allowed,true)){
        header('Location: /admin/settings.php',true,303);
        exit;
    }
    $admin_active=$active;
    admin_head($title);
    $name=site_setting('site_name','Lokasi Pelayanan Kaki Palsu');
    echo '<div class="admin-app"><input class="sidebar-switch" id="admin-sidebar-toggle" type="checkbox"><label class="sidebar-toggle" for="admin-sidebar-toggle" aria-label="Tampilkan atau sembunyikan menu samping" title="Tampilkan / sembunyikan menu"></label><header class="admin-topbar"><a class="topbar-brand" href="/admin/settings.php" aria-label="Administrator"><img data-site-logo src="'.e(site_logo_url()).'" alt="" width="40" height="40"><span>Administrator</span></a><input class="search-switch" id="admin-search-toggle" type="checkbox"><label class="search-toggle" for="admin-search-toggle" aria-label="Tampilkan atau sembunyikan pencarian publik" title="Tampilkan / sembunyikan pencarian"><span aria-hidden="true">⌕</span><span class="search-toggle-text">Cari</span></label><form class="topbar-search" method="get" action="/cari/" role="search"><label class="sr-only" for="admin-search-q">Cari di website publik</label><span class="search-mark" aria-hidden="true">⌕</span><input id="admin-search-q" name="q" type="search" placeholder="Cari di website publik" autocomplete="off"><button class="search-submit" type="submit" aria-label="Cari di website publik">Cari</button></form><details class="admin-mobile-menu"><summary aria-label="Buka menu Administrator">Menu</summary><nav aria-label="Navigasi Administrator">';
    foreach([['settings','Brand Website','/admin/settings.php'],['publish','Artikel','/admin/publish.php'],['account','Akun Admin','/admin/account.php']] as [$key,$label,$href]){ $current=$active===$key?' aria-current="page"':''; echo '<a href="'.e($href).'"'.$current.'>'.e($label).'</a>'; }
    echo '<a href="/" target="_blank" rel="noopener">Lihat Website</a><form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button type="submit">Keluar</button></form></nav></details></header>';
    admin_nav($active);
    echo '<div class="admin-content"><header><p>'.e($name).'</p><h1>'.e($title).'</h1></header><main>';
}

function admin_close(): void {
    $ms=isset($GLOBALS['_admin_request_start'])?round((microtime(true)-(float)$GLOBALS['_admin_request_start'])*1000):0;
    if(!headers_sent()) header('Server-Timing: admin;dur='.$ms);
    echo '</main></div></div></body></html>';
}
