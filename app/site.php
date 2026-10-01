<?php
declare(strict_types=1);
function site_settings(): array { return settings(); }
function site_setting(string $key, string $default=''): string { $s=site_settings(); return trim((string)($s[$key] ?? $default)); }
function site_url(string $path=''): string {
    $base=rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/');
    return $base . ($path!=='' ? '/'.ltrim($path,'/') : '');
}

function brand_asset_url(string $key,string $fallback=''): string {
    $value=trim(site_setting($key,''));
    if($value!=='') return public_asset_url($value,$fallback);
    return $fallback;
}
function site_logo_url(): string {
    return brand_asset_url('logo_url',site_url('assets/images/brand-mark.svg'));
}
function site_og_image_url(): string {
    // Canonical social OG image remains wide (1200x630). The uploaded square
    // 'Logo OG Image' is a separate brand overlay asset and is never used here.
    return brand_asset_url('og_image_url',site_url('assets/images/og/home-1200x630.png'));
}
function site_og_logo_url(): string {
    // Square logo used only inside featured-image frames. Falls back to the
    // regular website logo so existing installations remain compatible.
    return brand_asset_url('og_logo_url',site_logo_url());
}
function site_favicon_url(): string {
    $v=site_setting('favicon_url','');
    if($v!=='') return public_asset_url($v,site_url('favicon-48.png'));
    return site_url('favicon-48.png');
}
function store_brand_asset(array $file,string $kind): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('File brand gagal diunggah. Silakan coba lagi.');
    if(!isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('File brand tidak valid.');
    $size=(int)($file['size']??0);
    $limits=['logo'=>2*1024*1024,'favicon'=>1*1024*1024,'og_image'=>4*1024*1024,'og_logo'=>2*1024*1024];
    if($size<=0 || $size>($limits[$kind]??2*1024*1024)) throw new RuntimeException('Ukuran file brand melebihi batas yang diizinkan.');
    $tmp=(string)$file['tmp_name']; $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($tmp);
    $svg=false;
    if(in_array($kind,['logo','favicon'],true) && ($mime==='image/svg+xml' || strtolower((string)($file['type']??''))==='image/svg+xml')){
        $raw=(string)@file_get_contents($tmp);
        if($raw==='' || !preg_match('~<svg\b~i',$raw) || preg_match('~<\s*(script|foreignObject)\b|javascript:|\bon[a-z]+\s*=~i',$raw)) throw new RuntimeException('Logo SVG tidak aman. Gunakan SVG sederhana tanpa script, event handler, atau foreignObject.');
        $svg=true;
    }
    $allowed=[
      'logo'=>['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/svg+xml'=>'svg'],
      'favicon'=>['image/png'=>'png','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','image/svg+xml'=>'svg'],
      'og_image'=>['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'],
      'og_logo'=>['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'],
    ];
    if(!isset(($allowed[$kind])[$mime])) throw new RuntimeException('Format file tidak didukung untuk '.$kind.'.');
    $ext=$allowed[$kind][$mime];
    if(!$svg){
        $info=@getimagesize($tmp);
        if(!is_array($info)) throw new RuntimeException('File bukan gambar yang valid.');
        $w=(int)($info[0]??0); $h=(int)($info[1]??0);
        if($kind==='logo' && ($w<32 || $h<32 || $w>3000 || $h>3000)) throw new RuntimeException('Logo harus berukuran 32–3000 px.');
        if($kind==='favicon' && ($w<16 || $h<16 || $w>2048 || $h>2048)) throw new RuntimeException('Favicon harus berukuran 16–2048 px.');
        if($kind==='og_image' && ($w<600 || $h<315 || $w>3000 || $h>1800)) throw new RuntimeException('OG Image sosial minimal 600×315 dan maksimal 3000×1800 px.');
        if($kind==='og_image' && abs(($w/$h)-(1200/630))>0.12) throw new RuntimeException('OG Image sosial sebaiknya menggunakan rasio sekitar 1200×630 (1.91:1).');
        if($kind==='og_logo' && ($w<64 || $h<64 || $w>2048 || $h>2048)) throw new RuntimeException('Logo OG Image harus berukuran 64–2048 px.');
        if($kind==='og_logo' && abs($w-$h)>1) throw new RuntimeException('Logo OG Image harus berbentuk persegi dengan rasio 1:1.');
    }
    $dir=app_root().'/uploads/brand'; @mkdir($dir,0755,true);
    foreach(glob($dir.'/'.$kind.'-*')?:[] as $old){ if(is_file($old)) @unlink($old); }
    $name=$kind.'-'.bin2hex(random_bytes(5)).'.'.$ext; $dest=$dir.'/'.$name;
    if(!@copy($tmp,$dest)) throw new RuntimeException('File brand tidak dapat disimpan.'); @chmod($dest,0644);
    return '/uploads/brand/'.basename($dest);
}
function delete_brand_asset(string $path): void {
    if(!preg_match('~^/uploads/brand/[A-Za-z0-9._-]+$~',$path)) return;
    $local=app_root().$path; if(is_file($local)) @unlink($local);
}

function public_html_files(): array {
    static $memory=null;
    if(is_array($memory)) return $memory;
    $cache=storage_file('cache/public-html-files.json');
    if(is_file($cache) && (time()-(int)@filemtime($cache))<60){
        $d=json_decode((string)@file_get_contents($cache),true);
        if(is_array($d)){
            $valid=array_values(array_filter($d,static fn($p)=>is_string($p) && is_file($p)));
            if(count($valid)===count($d)) return $memory=$valid;
            @unlink($cache);
        }
    }
    $root=app_root(); $out=[];
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach($it as $f){ if(!$f->isFile() || strtolower($f->getExtension())!=='html') continue; $p=$f->getPathname(); if(in_array(basename($p),['403.html','404.html','maintenance.html'],true)) continue; if(substr(str_replace('\\','/',$p),-strlen('/lokasi-pelayanan/dki-jakarta/index.html'))==='/lokasi-pelayanan/dki-jakarta/index.html') continue; if(str_contains($p, DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR)) continue; if(str_contains($p, DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)) continue; $out[]=$p; }
    sort($out); @mkdir(dirname($cache),0750,true); @file_put_contents($cache,json_encode($out,JSON_UNESCAPED_SLASHES),LOCK_EX); return $memory=$out;
}
function inject_or_replace(string $html,string $pattern,string $replacement): string {
    $n=preg_replace($pattern,$replacement,$html,1,$count); return ($count===1 && $n!==null)?$n:$html;
}

function ga4_measurement_id(): string {
    $id=trim(site_setting('ga4_measurement_id',''));
    return preg_match('/^G-[A-Z0-9]+$/i',$id)?strtoupper($id):'';
}
function gsc_verification_token(): string {
    $token=trim(site_setting('gsc_verification_token',''));
    return preg_match('/^[A-Za-z0-9._-]{16,200}$/',$token)?$token:'';
}
function analytics_event_allowlist(): array { return ['page_view','article_view','search_submit','search_result_click','consultation_whatsapp_click','phone_click','guide_open','region_open','article_cta_click','article_internal_link_click']; }
function analytics_log_event(string $event,string $path='',array $params=[]): void {
    if(!in_array($event,analytics_event_allowlist(),true)) return;
    $dir=storage_file('analytics'); @mkdir($dir,0750,true);
    $row=['ts'=>date('c'),'event'=>$event,'path'=>substr(trim((string)$path),0,220),'params'=>[]];
    foreach($params as $k=>$v){ $key=preg_replace('/[^a-zA-Z0-9_]/','_',str((string)$k)); if($key==='') continue; $row['params'][$key]=substr(is_scalar($v)?trim((string)$v):'',0,160); }
    @file_put_contents($dir.'/events-'.date('Y-m-d').'.jsonl',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
    foreach(glob($dir.'/events-*.jsonl')?:[] as $old){ $mtime=@filemtime($old); if($mtime!==false && $mtime<time()-90*86400) @unlink($old); }
}
function analytics_dashboard_data(int $days=30): array {
    $days=max(1,min(90,$days)); $since=time()-$days*86400; $events=[]; $topArticles=[];
    foreach(glob(storage_file('analytics/events-*.jsonl'))?:[] as $file){
        $mtime=@filemtime($file); if($mtime!==false && $mtime<$since) continue;
        foreach(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){ $d=json_decode($line,true); if(!is_array($d)) continue; $ts=strtotime((string)($d['ts']??'')); if($ts===false || $ts<$since) continue; $event=(string)($d['event']??''); $events[$event]=($events[$event]??0)+1; if($event==='article_view'){ $path=(string)($d['path']??''); if(preg_match('~^/artikel/([^/]+)/?~',$path,$m)) $topArticles[$m[1]]=($topArticles[$m[1]]??0)+1; } }
    }
    arsort($topArticles); return ['days'=>$days,'events'=>$events,'top_articles'=>array_slice($topArticles,0,5,true)];
}

function article_keyword_tokens(array $article): array {
    $raw=strtolower(trim((string)($article['focus_keywords']??''))); $tokens=[];
    foreach(preg_split('/[,;]+/u',$raw)?:[] as $token){ $token=preg_replace('/[^a-z0-9\- ]+/','',trim($token))??''; if(mb_strlen($token)>=3) $tokens[]=$token; }
    return array_values(array_unique($tokens));
}
function article_related_articles(array $article,array $articles,int $limit=3): array {
    $slug=content_slug((string)($article['slug']??'')); $cluster=trim((string)($article['content_cluster']??'')); $tokens=article_keyword_tokens($article); $geo=array_map('trim',preg_split('/[,;]+/u',strtolower((string)($article['geo_area']??'')))?:[]); $rank=[];
    foreach($articles as $other){ if(($other['status']??'')!=='published') continue; $os=content_slug((string)($other['slug']??'')); if($os==='' || $os===$slug) continue; $score=0; if($cluster!=='' && $cluster===trim((string)($other['content_cluster']??''))) $score+=8; foreach(article_keyword_tokens($other) as $t) if(in_array($t,$tokens,true)) $score+=3; foreach($geo as $g) if($g!=='' && str_contains(strtolower((string)($other['geo_area']??'')),$g)) $score+=1; if($score>0) $rank[]=['score'=>$score,'article'=>$other]; }
    usort($rank,static fn($a,$b)=>$b['score']<=>$a['score']); return array_map(static fn($v)=>$v['article'],array_slice($rank,0,max(1,$limit)));
}
function article_related_markup(array $article,array $articles): string {
    $related=article_related_articles($article,$articles,3); if(!$related) return '';
    $html='<aside class="article-related" aria-labelledby="article-related-title"><p class="eyebrow">Baca juga</p><h2 id="article-related-title">Panduan terkait</h2><div class="article-related-list">';
    foreach($related as $item){ $slug=content_slug((string)($item['slug']??'')); $html.='<a href="/artikel/'.e($slug).'/"><strong>'.e((string)($item['title']??$slug)).'</strong><small>'.e((string)($item['excerpt']??'')).'</small></a>'; }
    return $html.'</div></aside>';
}

function public_asset_url(string $value,string $fallback): string {
    $value=trim($value);
    if($value==='') return $fallback;
    $base=rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/');
    $u=parse_url($value);
    if($u===false) return $fallback;
    $host=(string)($u['host']??'');
    if($host==='' ) return $base.'/'.ltrim($value,'/');
    if(in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)) return $base.(string)($u['path']??'/').(!empty($u['query'])?'?'.$u['query']:'');
    return $value;
}
function sync_brand_overlay_css(string $ogLogo): bool {
    $file=app_root().'/assets/css/minimal.css';
    if(!is_file($file)) return false;
    $css=(string)@file_get_contents($file);
    if($css==='') return false;
    $cssUrl=$ogLogo;
    $parsed=parse_url($ogLogo);
    $siteParsed=parse_url(rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/'));
    if(is_array($parsed) && (string)($parsed['host']??'')!=='' && strtolower((string)($parsed['host']??''))===strtolower((string)($siteParsed['host']??''))){
        $cssUrl=(string)($parsed['path']??'/').(!empty($parsed['query'])?'?'.$parsed['query']:'');
    } elseif(is_array($parsed) && (string)($parsed['host']??'')==='') {
        $cssUrl=(string)($parsed['path']??$ogLogo).(!empty($parsed['query'])?'?'.$parsed['query']:'');
    }
    $url=e($cssUrl);
    $replacement='/* BRAND_OG_LOGO_START */:root{--brand-og-logo:url("'.$url.'")}/* BRAND_OG_LOGO_END */';
    $pattern='~/\* BRAND_OG_LOGO_START \*/.*?/\* BRAND_OG_LOGO_END \*/~s';
    if(preg_match($pattern,$css)) $next=preg_replace($pattern,$replacement,$css,1);
    else $next=$replacement.$css;
    return $next!==null && $next!==$css ? @file_put_contents($file,$next,LOCK_EX)!==false : true;
}

function publish_site_identity(string $scope='full', string $note=''): array {
    $root=app_root(); $files=public_html_files();
    $changed=0;$failed=0;
    $siteName=site_setting('site_name','Lokasi Pelayanan Kaki Palsu');
    $siteDesc=site_setting('site_description','Direktori informasi layanan kaki palsu berdasarkan wilayah Indonesia.');
    $siteUrl=rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/');
    $favicon=site_favicon_url();
    $logo=site_logo_url();
    $ogLogo=site_og_logo_url();
    $ogImage=site_og_image_url();
    $cssBrandSynced=sync_brand_overlay_css($ogLogo);
    $theme='#ffffff';
    $whatsapp=preg_replace('/\D+/','',site_setting('whatsapp',''))??''; if(str_starts_with($whatsapp,'0')) $whatsapp='62'.substr($whatsapp,1);
    $phoneDisplay=site_setting('phone_display','');
    foreach($files as $file){
        $html=(string)@file_get_contents($file); if($html===''){ $failed++; continue; }
        $original=$html;
        $html=inject_or_replace($html,'~<meta name="theme-color"[^>]*>~i','<meta name="theme-color" content="'.e($theme).'">');
        $html=inject_or_replace($html,'~<meta name="robots"[^>]*>~i','<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">');
        if($html===$original && !preg_match('~<meta name="theme-color"~i',$html)) $html=inject_or_replace($html,'~</head>~i','<meta name="theme-color" content="'.e($theme).'">'."\n".'</head>');
        $gsc=gsc_verification_token();
        if($gsc!=='') {
            $html=inject_or_replace($html,'~<meta name="google-site-verification"[^>]*>~i','<meta name="google-site-verification" content="'.e($gsc).'">');
            if(!preg_match('~<meta name="google-site-verification"~i',$html)) $html=inject_or_replace($html,'~</head>~i','<meta name="google-site-verification" content="'.e($gsc).'">'."\n".'</head>');
        } else { $html=preg_replace('~\s*<meta name="google-site-verification"[^>]*>~i','',$html)??$html; }
        $ga4=ga4_measurement_id();
        if($ga4!==''){ $analyticsTag='<script src="/assets/js/analytics.js" data-ga4="'.e($ga4).'" defer></script>'; if(!preg_match('~<script[^>]+src="/assets/js/analytics\.js"~i',$html)) $html=inject_or_replace($html,'~</body>~i',$analyticsTag."\n".'</body>'); }
        else { $html=preg_replace('~\s*<script[^>]+src="/assets/js/analytics\.js"[^>]*></script>~i','',$html)??$html; }
        $faviconType=site_favicon_type($favicon);
        $html=preg_replace('~\s*<link rel="icon"[^>]*>~i','',$html)??$html;
        $html=preg_replace('~\s*<link rel="shortcut icon"[^>]*>~i','',$html)??$html;
        $html=preg_replace('~\s*<link rel="apple-touch-icon"[^>]*>~i','',$html)??$html;
        $html=inject_or_replace($html,'~</head>~i','<link rel="icon" type="'.e($faviconType).'" href="'.e($favicon).'">' . "\n" . '<link rel="apple-touch-icon" href="'.e($favicon).'">' . "\n" . '</head>');
        $html=inject_or_replace($html,'~<meta property="og:site_name"[^>]*>~i','<meta property="og:site_name" content="'.e($siteName).'">');
        // Article pages own their SEO/OG metadata. Brand sync must not overwrite it with homepage values.
        $ogTitle=site_setting('home_title',$siteName); $ogDesc=$siteDesc;
        $isHome=($file===$root.'/index.html');
        $normalizedFile=str_replace('\\','/',$file);
        $isArticle=str_contains($normalizedFile,'/artikel/');
        $isRegional=str_contains($normalizedFile,'/lokasi-pelayanan/');
        if(!$isArticle){
            $html=inject_or_replace($html,'~<meta property="og:title"[^>]*>~i','<meta property="og:title" content="'.e($ogTitle).'">');
            $html=inject_or_replace($html,'~<meta property="og:description"[^>]*>~i','<meta property="og:description" content="'.e($ogDesc).'">');
            $html=inject_or_replace($html,'~<meta property="og:url"[^>]*>~i','<meta property="og:url" content="'.e($siteUrl).'">');
        }
        // Generic public pages use the canonical wide social image. Regional pages keep their own artwork; article pages keep their featured image.
        if(!$isArticle && !$isRegional){
            $html=inject_or_replace($html,'~<meta property="og:image"[^>]*>~i','<meta property="og:image" content="'.e($ogImage).'">');
            $html=inject_or_replace($html,'~<meta name="twitter:image"[^>]*>~i','<meta name="twitter:image" content="'.e($ogImage).'">');
        }
        $html=inject_or_replace($html,'~<meta name="twitter:card"[^>]*>~i','<meta name="twitter:card" content="summary_large_image">');
        if($whatsapp!==''){ $html=preg_replace('~https://wa\.me/[0-9]+~i','https://wa.me/'.e($whatsapp),$html)??$html; }
        if($phoneDisplay!==''){ $html=str_replace(['0853 9484 9766','085394849766'],e($phoneDisplay),$html); }
        $html=preg_replace('~(<img\b(?=[^>]*\bdata-site-logo\b)[^>]*\bsrc=)[\"\'][^\"\']*[\"\']~i','$1"'.e($logo).'"',$html) ?? $html;
        $html=preg_replace('~<span class="article-brand-logo"[^>]*>\s*<img[^>]*data-brand-og-logo[^>]*>\s*</span>~i','<span class="article-brand-logo" data-brand-og-logo aria-hidden="true"></span>',$html) ?? $html;
        $html=preg_replace('~<img class="brand-image-logo-sync"[^>]*data-brand-og-logo[^>]*>~i','<span class="brand-image-logo-sync" data-brand-og-logo aria-hidden="true"></span>',$html) ?? $html;
        $html=preg_replace('~(<a class="topbar-brand"[^>]*>\s*<img[^>]*\bsrc=)[\"\'][^\"\']*[\"\']~i','$1"'.e($logo).'"',$html) ?? $html;
        // Sync the visible brand shell on every public static page.
        $html=inject_or_replace($html,'~(<a class="topbar-brand"[^>]*aria-label=)[\"\'][^\"\']*[\"\']([^>]*>\s*<img[^>]*>\s*<span>).*?(</span>)~is','$1"'.e($siteName).'"$2'.e($siteName).'$3');
        $html=inject_or_replace($html,'~(<footer[^>]*>\s*<div>\s*<strong>).*?(</strong>)~is','$1'.e($siteName).'$2');
        $html=inject_or_replace($html,'~(©\s*\d{4}\s+).*?(\. Informasi ini membantu)~i','$1'.e($siteName).'$2');
        $tagline=trim(site_setting('site_tagline',''));
        if($tagline!=='') {
            if(preg_match('~<small[^>]*data-site-tagline[^>]*>.*?</small>~is',$html)) $html=inject_or_replace($html,'~<small[^>]*data-site-tagline[^>]*>.*?</small>~is','<small data-site-tagline>'.e($tagline).'</small>');
            else $html=inject_or_replace($html,'~(<footer[^>]*>\s*<div>\s*<strong>.*?</strong>)~is','$1<small data-site-tagline>'.e($tagline).'</small>');
        } else {
            $html=preg_replace('~\s*<small[^>]*data-site-tagline[^>]*>.*?</small>~is','',$html)??$html;
        }
        if($isHome){
            $html=inject_or_replace($html,'~(<h1 id=\"hero-title\">.*?</h1>\s*<p>).*?(</p>)~is','$1'.e($siteDesc).'$2');
        }
        $html=inject_or_replace($html,'~(<[^>]+data-site-name[^>]*>).*?(</[^>]+>)~is','$1'.e($siteName).'$2');
        $html=inject_or_replace($html,'~(<[^>]+data-site-tagline[^>]*>).*?(</[^>]+>)~is','$1'.e($tagline).'$2');
        if(!$isArticle){
            $html=inject_or_replace($html,'~<meta name="author"[^>]*>~i','<meta name="author" content="'.e($siteName).'">');
            if(!preg_match('~<meta name="author"~i',$html)) $html=inject_or_replace($html,'~</head>~i','<meta name="author" content="'.e($siteName).'">' . "\n" . '</head>');
        }
        // Keep each page's unique title/description; only use configured values where the page is the homepage.
        if($isHome){
            $title=site_setting('home_title', $siteName.' | Direktori Layanan Kaki Palsu Indonesia');
            $desc=$siteDesc;
            $html=inject_or_replace($html,'~<title>.*?</title>~is','<title>'.e($title).'</title>');
            $html=inject_or_replace($html,'~<meta name="description"[^>]*>~i','<meta name="description" content="'.e($desc).'">');
            $html=inject_or_replace($html,'~<meta property="og:title"[^>]*>~i','<meta property="og:title" content="'.e($title).'">');
            $html=inject_or_replace($html,'~<meta property="og:description"[^>]*>~i','<meta property="og:description" content="'.e($desc).'">');
            $html=inject_or_replace($html,'~<meta name="twitter:title"[^>]*>~i','<meta name="twitter:title" content="'.e($title).'">');
            $html=inject_or_replace($html,'~<meta name="twitter:description"[^>]*>~i','<meta name="twitter:description" content="'.e($desc).'">');
        }
        if($html!==$original){ if(@file_put_contents($file,$html,LOCK_EX)===false){$failed++;} else {$changed++;} }
    }
    $manifestFile=$root.'/manifest.webmanifest';
    if(is_file($manifestFile)){
        $manifest=json_decode((string)@file_get_contents($manifestFile),true);
        if(is_array($manifest)){
            $manifest['name']=$siteName; $manifest['short_name']=substr($siteName,0,48); $manifest['theme_color']=$theme;
            $icons=(array)($manifest['icons']??[]); foreach($icons as &$icon){ $icon['src']=$favicon; $icon['type']=$faviconType; } unset($icon);
            $manifest['icons']=$icons; @file_put_contents($manifestFile,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);
        }
    }
    if(!$cssBrandSynced) $failed++;
    $report=['published_at'=>date('c'),'scope'=>$scope,'note'=>trim($note),'files_total'=>count($files),'files_changed'=>$changed,'files_failed'=>$failed,'brand_overlay_css_synced'=>$cssBrandSynced];
    @file_put_contents(storage_file('logs/publish-'.date('Y-m-d-His').'.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    @unlink(storage_file('cache/public-search-index.json'));
    @unlink(storage_file('cache/public-html-files.json'));
    return $report;
}






function brand_defaults(): array {
    return [
        'site_name'=>'Lokasi Pelayanan Kaki Palsu',
        'site_tagline'=>'',
        'site_description'=>'Informasi layanan kaki palsu, tangan palsu, jari palsu, dan alat bantu ortotik.',
        'home_title'=>'Lokasi Pelayanan Kaki Palsu | Layanan Prostetik dan Ortotik',
        'phone_display'=>'',
        'whatsapp'=>'',
        'site_url'=>'https://lokasi.kakitanganpalsumakassar.com',
        'logo_url'=>'/assets/images/brand-mark.svg',
        'favicon_url'=>'/favicon-48.png',
        'og_image_url'=>'/assets/images/og/home-1200x630.png',
        'og_logo_url'=>'/assets/images/brand-og-logo.webp'
    ];
}

function site_favicon_type(string $url): string {
    $p=parse_url($url,PHP_URL_PATH) ?: '';
    $ext=strtolower(pathinfo($p,PATHINFO_EXTENSION));
    return $ext==='svg'?'image/svg+xml':($ext==='ico'?'image/x-icon':($ext==='webp'?'image/webp':($ext==='jpg'||$ext==='jpeg'?'image/jpeg':'image/png')));
}
function content_slug(string $value): string {
    $value=trim(strtolower($value));
    $value=preg_replace('/[^a-z0-9]+/','-',$value)??'';
    $value=trim($value,'-');
    return substr($value,0,80);
}
function content_articles(): array {
    static $memory=null;
    if(is_array($memory)) return $memory;
    $seedFile=app_root().'/data/content/articles.json';
    $seed=is_file($seedFile)?json_decode((string)@file_get_contents($seedFile),true):[];
    $runtimeFile=storage_file('content/articles.json');
    $runtime=is_file($runtimeFile)?json_decode((string)@file_get_contents($runtimeFile),true):[];
    $seed=is_array($seed)?$seed:[]; $runtime=is_array($runtime)?$runtime:[];
    // Runtime records override bundled editorial content by slug, so Admin edits remain authoritative.
    return $memory=array_replace($seed,$runtime);
}
function save_content_articles(array $articles): void {
    $f=storage_file('content/articles.json'); @mkdir(dirname($f),0750,true);
    $json=json_encode($articles,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if($json===false) throw new RuntimeException('Data artikel tidak dapat dienkode.');
    $tmp=$f.'.tmp-'.bin2hex(random_bytes(4));
    if(@file_put_contents($tmp,$json,LOCK_EX)===false || !@rename($tmp,$f)){ @unlink($tmp); throw new RuntimeException('Data artikel tidak dapat disimpan.'); }
    @chmod($f,0640);
}
function article_plain_text_to_html(string $body): string {
    $parts=preg_split('/\R\s*\R/u',trim($body))?:[];
    $out=[];
    foreach($parts as $part){
        $part=trim($part); if($part==='') continue;
        $out[]='<p>'.nl2br(e($part),false).'</p>';
    }
    return implode("\n",$out);
}
function article_sanitize_html(string $html): string {
    $html=trim($html);
    if($html==='') return '';
    $html=preg_replace('~<!--.*?-->~s','',$html)??$html;
    $html=preg_replace('~<(script|style|iframe|object|embed)[^>]*>.*?</\1>~is','',$html)??$html;
    $allowed=['p','h2','h3','h4','strong','em','u','s','ul','ol','li','blockquote','pre','code','br','a','img'];
    $html=strip_tags($html,'<'.implode('><',$allowed).'>');
    $html=preg_replace_callback('~<\s*(/?)\s*([a-z0-9]+)([^>]*)>~i',function(array $m) use($allowed): string {
        $closing=$m[1]==='/'; $tag=strtolower($m[2]);
        if(!in_array($tag,$allowed,true)) return '';
        if($closing) return '</'.$tag.'>';
        if($tag==='br') return '<br>';
        $raw=$m[3]??''; $attrs=[];
        if($tag==='a' && preg_match('~\bhref\s*=\s*["\']([^"\']+)["\']~i',$raw,$x)){
            $href=trim(html_entity_decode($x[1],ENT_QUOTES,'UTF-8'));
            if(preg_match('~^(https?://|/|#)~i',$href)) $attrs[]='href="'.e($href).'"';
            if(preg_match('~\btarget\s*=\s*["\']([^"\']+)["\']~i',$raw,$t) && strtolower($t[1])==='_blank'){
                $attrs[]='target="_blank"'; $attrs[]='rel="noopener noreferrer"';
            }
        } elseif($tag==='img' && preg_match('~\bsrc\s*=\s*["\']([^"\']+)["\']~i',$raw,$x)){
            $src=trim(html_entity_decode($x[1],ENT_QUOTES,'UTF-8'));
            if(!preg_match('~^/uploads/articles/[A-Za-z0-9._-]+$~',$src)) return '';
            if(!is_file(app_root().$src)) return '';
            $attrs[]='src="'.e($src).'"';
            if(preg_match('~\balt\s*=\s*["\']([^"\']*)["\']~i',$raw,$a)) $attrs[]='alt="'.e(trim($a[1])).'"'; else $attrs[]='alt=""';
            if(preg_match('~\bwidth\s*=\s*["\'](\d{2,4})["\']~i',$raw,$w)) $attrs[]='width="'.(int)$w[1].'"';
            if(preg_match('~\bheight\s*=\s*["\'](\d{2,4})["\']~i',$raw,$h)) $attrs[]='height="'.(int)$h[1].'"';
            $attrs[]='loading="lazy"'; $attrs[]='decoding="async"';
        }
        return '<'.$tag.($attrs?' '.implode(' ',$attrs):'').'>';
    },$html)??'';
    return trim($html);
}
function article_body_html(string $body): string {
    $body=trim($body);
    if($body==='') return '';
    if(strpos($body,'<')===false) return article_plain_text_to_html($body);
    $clean=article_sanitize_html($body);
    return $clean!=='' ? $clean : article_plain_text_to_html(strip_tags($body));
}
function article_has_featured_image(array $article): bool {
    $path=trim((string)($article['featured_image']??''));
    return $path!=='' && preg_match('~^/uploads/articles/[A-Za-z0-9._-]+$~',$path) && is_file(app_root().$path);
}
function article_featured_image_url(array $article): string {
    $path=trim((string)($article['featured_image']??''));
    if($path!=='' && preg_match('~^/uploads/articles/[A-Za-z0-9._-]+$~',$path)){
        $local=app_root().$path;
        if(is_file($local)) return $path;
    }
    $fallback='/assets/images/og/home-1200x630.png';
    return is_file(app_root().$fallback) ? $fallback : '';
}
function article_featured_image_dimensions(string $path): array {
    if($path==='' || !preg_match('~^/uploads/articles/[A-Za-z0-9._-]+$~',$path)) return [0,0];
    $info=@getimagesize(app_root().$path); return is_array($info)?[(int)($info[0]??0),(int)($info[1]??0)]:[0,0];
}
function store_article_featured_image(array $file, string $slug=''): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Media artikel gagal diunggah. Silakan coba lagi.');
    if(!isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('File gambar tidak valid.');
    $size=(int)($file['size']??0);
    if($size<=0 || $size>3*1024*1024) throw new RuntimeException('Gambar maksimal 3 MB.');
    $info=@getimagesize((string)$file['tmp_name']);
    if(!is_array($info)) throw new RuntimeException('File bukan gambar yang valid.');
    $w=(int)($info[0]??0); $h=(int)($info[1]??0);
    if($w<640 || $h<360 || $w>4000 || $h>4000) throw new RuntimeException('Gambar minimal 640×360 dan maksimal 4000×4000 piksel.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file((string)$file['tmp_name']);
    $exts=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($exts[$mime])) throw new RuntimeException('Format gambar harus JPG, PNG, atau WebP.');
    $base=content_slug($slug); if($base==='') $base='artikel';
    $name=$base.'-media-'.bin2hex(random_bytes(5)).'.'.$exts[$mime];
    $dir=app_root().'/uploads/articles'; @mkdir($dir,0755,true);
    $dest=$dir.'/'.$name;
    if(!@move_uploaded_file((string)$file['tmp_name'],$dest)) throw new RuntimeException('Media artikel tidak dapat disimpan.');
    // If Imagick is available, normalize large uploads to a compressed WebP while keeping a safe fallback.
    if(class_exists('Imagick')){
        try{
            $im=new Imagick($dest); $im->setIteratorIndex(0); $im->autoOrient();
            $max=1600; $iw=$im->getImageWidth(); $ih=$im->getImageHeight();
            if($iw>$max || $ih>$max){ $im->thumbnailImage($max,$max,true,true); }
            $im->setImageFormat('webp'); $im->setImageCompressionQuality(82); $im->stripImage();
            $optimized=$dir.'/'.$base.'-media-'.bin2hex(random_bytes(5)).'.webp';
            if($im->writeImage($optimized)){ @chmod($optimized,0644); @unlink($dest); $dest=$optimized; }
            $im->clear(); $im->destroy();
        }catch(Throwable $e){ /* Keep the validated original when server image optimization is unavailable. */ }
    }
    return '/uploads/articles/'.basename($dest);
}
function delete_article_featured_image(string $path): void {
    if(!preg_match('~^/uploads/articles/[A-Za-z0-9._-]+$~',$path)) return;
    $local=app_root().$path; if(is_file($local)) @unlink($local);
}
function article_page_html(array $article): string {
    $title=trim((string)($article['title']??'')); $excerpt=trim((string)($article['excerpt']??'')); $slug=content_slug((string)($article['slug']??''));
    $seoTitle=trim((string)($article['seo_title']??'')); if($seoTitle==='') $seoTitle=$title;
    $metaDesc=trim((string)($article['meta_description']??'')); if($metaDesc==='') $metaDesc=$excerpt; if($metaDesc==='') { $plain=trim(preg_replace('/\s+/u',' ',strip_tags((string)($article['body']??'')))??''); $metaDesc=mb_substr($plain,0,165); }
    $keywords=trim((string)($article['focus_keywords']??''));
    $cluster=trim((string)($article['content_cluster']??'')); $intent=trim((string)($article['search_intent']??'')); $sourceUrls=array_values(array_filter(array_map('trim',preg_split('/[,\n]+/u',(string)($article['source_urls']??''))?:[]),static fn($u)=>preg_match('~^https?://~i',$u))); 
    $geo=trim((string)($article['geo_area']??''));
    $answerQuestion=trim((string)($article['answer_question']??'')); $answer=trim((string)($article['answer']??''));
    $authorInput=trim((string)($article['author']??'')); $author=$authorInput!==''?$authorInput:site_setting('site_name','Lokasi Pelayanan Kaki Palsu');
    $imageAlt=trim((string)($article['featured_image_alt']??'')); if($imageAlt==='') $imageAlt=$title;
    $canonical=site_url('artikel/'.$slug.'/'); $siteName=site_setting('site_name','Pusat Layanan Pembuatan Kaki Palsu');
    $tagline=site_setting('site_tagline',''); $whatsapp=preg_replace('/\D+/','',site_setting('whatsapp',''))??''; if(str_starts_with($whatsapp,'0')) $whatsapp='62'.substr($whatsapp,1); if($whatsapp==='') $whatsapp='6285394849766';
    $wa='https://wa.me/'.$whatsapp.'?text='.rawurlencode('Halo, saya ingin konsultasi mengenai prostesis atau alat bantu.');
    $body=article_body_html((string)($article['body']??''));
    $pubIso=(string)($article['published_at']??date('c')); $updatedIso=(string)($article['updated_at']??$pubIso); $pub=date('d M Y',strtotime($pubIso)); $pubIsoEsc=e($pubIso); $updatedIsoEsc=e($updatedIso);
    $pageTitle=$seoTitle!=='' ? $seoTitle : ($title.' | '.$siteName);
    $titleEsc=e($title); $excerptEsc=e($excerpt); $siteEsc=e($siteName); $tagEsc=e($tagline); $pageTitleEsc=e($pageTitle); $metaDescEsc=e($metaDesc); $seoTitleEsc=e($seoTitle);
    $featured=article_featured_image_url($article); $featuredEsc=e($featured); [$imgW,$imgH]=article_featured_image_dimensions($featured);
    $imageAbs=$featured!=='' ? site_url(ltrim($featured,'/')) : '';
    $imageMeta=$featured!=='' ? '<meta property="og:image" content="'.e($imageAbs).'">'."\n".'<meta property="og:image:width" content="'.($imgW?:1200).'">'."\n".'<meta property="og:image:height" content="'.($imgH?:630).'">'."\n".'<meta name="twitter:image" content="'.e($imageAbs).'">' : '';
    $caption=trim((string)($article['featured_image_caption']??''));
    $captionMarkup=$caption!=='' ? '<figcaption>'.e($caption).'</figcaption>' : '';
    $featuredMarkup=$featured!=='' ? '<figure class="featured-media"><div class="brand-image-frame brand-image-frame--article"><img src="'.$featuredEsc.'" alt="'.e($imageAlt).'" width="'.($imgW?:1200).'" height="'.($imgH?:630).'" loading="eager" fetchpriority="high" decoding="async"><span class="brand-image-logo-sync" data-brand-og-logo aria-hidden="true"></span></div>'.$captionMarkup.'</figure>' : '';
    $answerMarkup=($answerQuestion!=='' && $answer!=='') ? '<section class="article-answer" aria-labelledby="article-answer-title"><p class="eyebrow">Jawaban singkat</p><h2 id="article-answer-title">'.e($answerQuestion).'</h2><p>'.nl2br(e($answer),false).'</p></section>' : '';
    $articleData=[
        '@context'=>'https://schema.org','@type'=>'Article','mainEntityOfPage'=>['@type'=>'WebPage','@id'=>$canonical],
        'headline'=>$title,'description'=>$metaDesc,'datePublished'=>$pubIso,'dateModified'=>$updatedIso,
        'author'=>['@type'=>$authorInput!==''?'Person':'Organization','name'=>$author],'publisher'=>['@type'=>'Organization','name'=>$siteName],
        'image'=>$imageAbs!==''?[$imageAbs]:[], 'keywords'=>$keywords!==''?$keywords:null,
        'areaServed'=>$geo!==''?array_map(static fn($v)=>['@type'=>'Place','name'=>trim($v)],preg_split('/[,;]+/',$geo)?:[]):null,
        'inLanguage'=>'id-ID','url'=>$canonical,'articleSection'=>$cluster!==''?$cluster:null,'citation'=>$sourceUrls?:null
    ];
    $articleData=array_filter($articleData,static fn($v)=>$v!==null && $v!==[] && $v!=='');
    $schema='<script type="application/ld+json">'.json_encode($articleData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP).'</script>';
    $faqSchema='';
    $keywordsMeta=$keywords!==''?'<meta name="keywords" content="'.e($keywords).'">':'';
    $geoMeta='';
    $gscMeta=gsc_verification_token()!==''?'<meta name="google-site-verification" content="'.e(gsc_verification_token()).'">':'';
    $faviconRuntime=site_favicon_url(); $faviconTypeRuntime=site_favicon_type($faviconRuntime); $logoRuntime=site_logo_url();
    $analyticsScript=ga4_measurement_id()!==''?'<script src="/assets/js/analytics.js" data-ga4="'.e(ga4_measurement_id()).'" defer></script>':'';
    $relatedMarkup=article_related_markup($article,content_articles());
    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="build-stamp" content="4.54.17-v45.6">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>{$pageTitleEsc}</title>
<meta name="description" content="{$metaDescEsc}">
<meta name="author" content="{$siteEsc}">
{$keywordsMeta}
{$geoMeta}
{$gscMeta}
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<link rel="canonical" href="{$canonical}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="{$siteEsc}">
<meta property="og:title" content="{$seoTitleEsc}">
<meta property="og:description" content="{$metaDescEsc}">
<meta property="og:url" content="{$canonical}">
<meta property="article:published_time" content="{$pubIsoEsc}">
<meta property="article:modified_time" content="{$updatedIsoEsc}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$seoTitleEsc}">
<meta name="twitter:description" content="{$metaDescEsc}">
{$imageMeta}
{$schema}
{$faqSchema}
<link rel="icon" href="{$faviconRuntime}" type="{$faviconTypeRuntime}">
<link rel="apple-touch-icon" href="{$faviconRuntime}">
<link rel="stylesheet" href="/assets/css/minimal.css">
<link rel="manifest" href="/manifest.webmanifest">
</head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<div class="site-app">
<input class="sidebar-switch" id="site-sidebar-toggle" type="checkbox"><label class="sidebar-toggle" for="site-sidebar-toggle" aria-label="Tampilkan atau sembunyikan menu samping" title="Tampilkan / sembunyikan menu"></label>
<header class="app-topbar">
<a class="topbar-brand" href="/" aria-label="{$siteEsc}"><img data-site-logo src="{$logoRuntime}" alt="" width="40" height="40"><span>{$siteEsc}</span></a>
<input class="search-switch" id="site-search-toggle" type="checkbox"><label class="search-toggle" for="site-search-toggle" aria-label="Tampilkan atau sembunyikan pencarian" title="Tampilkan / sembunyikan pencarian"><span aria-hidden="true">⌕</span><span class="search-toggle-text">Cari</span></label><form class="topbar-search" action="/cari/" method="get" role="search"><label class="sr-only" for="global-q">Cari wilayah atau layanan</label><span class="search-mark" aria-hidden="true">⌕</span><input id="global-q" name="q" type="search" placeholder="Cari wilayah atau layanan" autocomplete="off"><button class="search-submit" type="submit" aria-label="Mulai pencarian">Cari</button></form>
<details class="mobile-nav-toggle"><summary aria-label="Buka menu">Menu</summary><nav aria-label="Navigasi utama"><a href="/"><span class="nav-mark" aria-hidden="true">01</span><span>Beranda</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#layanan"><span class="nav-mark" aria-hidden="true">02</span><span>Layanan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#proses"><span class="nav-mark" aria-hidden="true">03</span><span>Proses</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/panduan/"><span class="nav-mark" aria-hidden="true">04</span><span>Panduan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/artikel/"><span class="nav-mark" aria-hidden="true">05</span><span>Artikel</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/lokasi-pelayanan/sulawesi-selatan/"><span class="nav-mark" aria-hidden="true">06</span><span>Wilayah</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/cari/"><span class="nav-mark" aria-hidden="true">07</span><span>Cari</span><span class="nav-arrow" aria-hidden="true">›</span></a></nav><a class="mobile-cta" href="{$wa}" target="_blank" rel="noopener noreferrer">Konsultasi WhatsApp</a></details>
</header>
<aside class="site-sidebar" aria-label="Navigasi situs"><div class="sidebar-heading"><span>Menu</span><span class="sidebar-count" aria-hidden="true">06</span></div><nav aria-label="Navigasi utama"><a href="/"><span class="nav-mark" aria-hidden="true">01</span><span>Beranda</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#layanan"><span class="nav-mark" aria-hidden="true">02</span><span>Layanan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/#proses"><span class="nav-mark" aria-hidden="true">03</span><span>Proses</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/panduan/"><span class="nav-mark" aria-hidden="true">04</span><span>Panduan</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/artikel/"><span class="nav-mark" aria-hidden="true">05</span><span>Artikel</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/lokasi-pelayanan/sulawesi-selatan/"><span class="nav-mark" aria-hidden="true">06</span><span>Wilayah</span><span class="nav-arrow" aria-hidden="true">›</span></a><a href="/cari/"><span class="nav-mark" aria-hidden="true">07</span><span>Cari</span><span class="nav-arrow" aria-hidden="true">›</span></a></nav><div class="sidebar-help"><small>Butuh arahan?</small><a href="{$wa}" target="_blank" rel="noopener noreferrer">Konsultasi WhatsApp</a></div><small class="sidebar-meta">Prostetik · ortotik</small></aside>
<div class="site-content"><header class="page-header"><p>Artikel</p></header><main id="main-content">
<article class="article-page"><p class="eyebrow">Diterbitkan {$pub}</p><h1>{$titleEsc}</h1><p class="article-excerpt">{$excerptEsc}</p>{$answerMarkup}{$featuredMarkup}<div class="article-body">{$body}</div>{$relatedMarkup}<div class="button-row"><a href="{$wa}" target="_blank" rel="noopener noreferrer">Konsultasi WhatsApp</a><a href="/panduan/">Buka panduan</a></div></article>
</main><footer class="site-footer"><div><strong>{$siteEsc}</strong><small data-site-tagline>{$tagEsc}</small></div><nav aria-label="Tautan footer"><a href="/kebijakan-privasi.html">Kebijakan Privasi</a><a href="/syarat-ketentuan.html">Syarat &amp; Ketentuan</a><a href="/hak-cipta.html">Hak Cipta</a></nav><small>© 2026 {$siteEsc}. Informasi ini membantu persiapan konsultasi dan bukan pengganti pemeriksaan tenaga kesehatan.</small></footer>
</div></div>{$analyticsScript}</body></html>
HTML;
}
function publish_article(array $article): string {
    $slug=content_slug((string)($article['slug']??'')); if($slug==='') throw new RuntimeException('Slug artikel wajib diisi.');
    $dir=app_root().'/artikel/'.$slug; @mkdir($dir,0755,true);
    $file=$dir.'/index.html'; $html=article_page_html($article);
    $tmp=$file.'.tmp-'.bin2hex(random_bytes(4));
    if(@file_put_contents($tmp,$html,LOCK_EX)===false || !@rename($tmp,$file)){ @unlink($tmp); throw new RuntimeException('Artikel tidak dapat diterbitkan.'); }
    return '/artikel/'.$slug.'/';
}
function update_sitemap_for_articles(array $articles): void {
    $file=app_root().'/sitemap.xml'; $xml=is_file($file)?(string)@file_get_contents($file):''; if($xml==='') return;
    $xml=preg_replace('~\s*<url><loc>'.preg_quote(rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/'), '~').'/artikel/[^<]+</loc></url>~i','',$xml)??$xml;
    $base=rtrim(site_setting('site_url','https://lokasi.kakitanganpalsumakassar.com'),'/'); $entries=[];
    foreach($articles as $article){ if(($article['status']??'')!=='published') continue; $slug=content_slug((string)($article['slug']??'')); if($slug==='') continue; $entries[]='  <url><loc>'.e($base.'/artikel/'.$slug.'/').'</loc></url>'; }
    if($entries) $xml=str_replace('</urlset>',implode("\n",$entries)."\n</urlset>",$xml);
    @file_put_contents($file,$xml,LOCK_EX);
}
function article_delete_public(string $slug): void {
    $slug=content_slug($slug); if($slug==='') return; $dir=app_root().'/artikel/'.$slug;
    if(!is_dir($dir)) return;
    $file=$dir.'/index.html'; if(is_file($file)) @unlink($file); @rmdir($dir);
}



