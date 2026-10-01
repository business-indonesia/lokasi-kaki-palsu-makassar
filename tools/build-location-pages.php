<?php
declare(strict_types=1);

/**
 * Static regional page builder.
 *
 * Source of truth:
 *   data/locations.json       = administrative hierarchy + slugs
 *   app/build-data/location-pages.json  = editorial content for 514 city/regency pages
 *   app/build-data/province-pages.json  = editorial content for 38 province pages
 *   templates/*.html.php      = shared presentation
 *
 * This intentionally generates static HTML rather than switching public URLs to
 * runtime PHP. That preserves the current fast delivery model while removing the
 * need to hand-maintain 552 content copies.
 *
 * Usage:
 *   php tools/build-location-pages.php --check   # validate without writing
 *   php tools/build-location-pages.php           # generate pages
 */

$root = dirname(__DIR__);
$checkOnly = in_array('--check', $argv, true);
$baseUrl = 'https://lokasi.kakitanganpalsumakassar.com';

function remove_legacy_duplicate_dirs(string $root): void {
    foreach (['cari/cari','artikel/artikel','panduan/panduan','lokasi-pelayanan/lokasi-pelayanan'] as $relative) {
        $dir=$root.'/'.$relative;
        if (!is_dir($dir)) continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $item){ $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($dir);
    }
}
if(!$checkOnly) remove_legacy_duplicate_dirs($root);

function read_json(string $file): array {
    if (!is_file($file)) throw new RuntimeException("Missing JSON: {$file}");
    $d = json_decode((string)file_get_contents($file), true);
    if (!is_array($d)) throw new RuntimeException("Invalid JSON: {$file}");
    return $d;
}
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function token(string $s, array $vars): string {
    return strtr($s, [
        '{{PROVINCE_NAME}}'=>esc((string)($vars['province_name'] ?? $vars['name'] ?? '')),
        '{{PROVINCE_SLUG}}'=>esc((string)($vars['province_slug'] ?? $vars['slug'] ?? '')),
        '{{LOCATION_NAME}}'=>esc((string)($vars['location_name'] ?? '')),
        '{{LOCATION_SLUG}}'=>esc((string)($vars['location_slug'] ?? '')),
        '{{LOCATION_COUNT}}'=>esc((string)($vars['location_count'] ?? '')),
        '{{LOCATION_CODE}}'=>esc((string)($vars['location_code'] ?? '')),
        '{{PROVINCE_CODE}}'=>esc((string)($vars['province_code'] ?? '')),
        '{{OG_IMAGE_FILE}}'=>esc((string)preg_replace('/\.png$/i','.webp',(string)($vars['image_file'] ?? ''))),
    ]);
}
function render_schema_location(array $r, string $baseUrl): string {
    $provinceUrl = $baseUrl.'/lokasi-pelayanan/'.$r['province_slug'].'/';
    $url = $provinceUrl.$r['location_slug'].'/';
    $name = 'Kaki Palsu di '.$r['location_name'];
    $faq=[];
    foreach ((array)($r['faq'] ?? []) as $x) {
        $q=token((string)($x['q']??''),$r); $a=token((string)($x['a']??''),$r);
        if($q!==''&&$a!=='') $faq[]=['@type'=>'Question','name'=>strip_tags($q),'acceptedAnswer'=>['@type'=>'Answer','text'=>strip_tags($a)]];
    }
    $graph=[
      ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
        ['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>$baseUrl.'/'],
        ['@type'=>'ListItem','position'=>2,'name'=>$r['province_name'],'item'=>$provinceUrl],
        ['@type'=>'ListItem','position'=>3,'name'=>$name,'item'=>$url],
      ]],
      ['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$name,'url'=>$url,'image'=>$baseUrl.'/assets/images/og/wilayah/'.preg_replace('/\.png$/i','.webp',(string)$r['image_file']),'isPartOf'=>['@id'=>$baseUrl.'/#website'],'about'=>['@type'=>'Service','name'=>'Layanan pembuatan dan konsultasi prostetik','serviceType'=>'Prosthetic services','provider'=>['@id'=>$baseUrl.'/#organization'],'areaServed'=>['@type'=>'AdministrativeArea','name'=>$r['location_name']]]],
      ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faq],
    ];
    return '<script type="application/ld+json">'.json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP).'</script>
';
}
function render_schema_province(array $r, string $baseUrl): string {
    $url=$baseUrl.'/lokasi-pelayanan/'.$r['slug'].'/';
    $name='Lokasi Pelayanan Kaki Palsu di '.$r['name'];
    $faq=[
      ['@type'=>'Question','name'=>'Apakah semua kabupaten/kota memiliki workshop?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Tidak. Halaman ini memetakan area informasi/pelayanan, bukan daftar cabang fisik.']],
      ['@type'=>'Question','name'=>'Bagaimana mendapatkan informasi terbaru?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Hubungi tim pelayanan melalui WhatsApp sebelum datang atau melakukan perjalanan.']]
    ];
    $graph=[
      ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
        ['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>$baseUrl.'/'],
        ['@type'=>'ListItem','position'=>2,'name'=>$r['location_count'].' Kabupaten/Kota','item'=>$url],
      ]],
      ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faq],
      ['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$name,'url'=>$url,'image'=>$baseUrl.'/assets/images/og/wilayah/'.preg_replace('/\.png$/i','.webp',(string)$r['image_file']),'isPartOf'=>['@id'=>$baseUrl.'/#website'],'about'=>['@type'=>'Service','name'=>'Layanan pembuatan dan konsultasi prostetik','serviceType'=>'Prosthetic services','provider'=>['@id'=>$baseUrl.'/#organization'],'areaServed'=>['@type'=>'AdministrativeArea','name'=>$r['name']]]],
    ];
    return '<script type="application/ld+json">'.json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP).'</script>
';
}
function clean_generated_html(string $html): string {
    $html=str_replace(['—','–','•'], '-', $html);
    $html=preg_replace('~</a><a\b~i','</a> <a',$html)??$html;
    $html=preg_replace('~</button><button\b~i','</button> <button',$html)??$html;
    $html=preg_replace('/\sdata-track=[\"\'][^\"\']*[\"\']/i','',$html)??$html;
    return $html;
}

function render_template(string $template, array $vars, string $schema): string {
    $html=token($template,$vars);
    $html=str_replace('{{SCHEMA}}',$schema,$html);
    $html=str_replace('<body>','<body>',$html);
    foreach ((array)($vars['sections']??[]) as $id=>$block) {
        $html=str_replace('{{SECTION_'.strtoupper($id).'}}',token((string)$block,$vars),$html);
    }
    return clean_generated_html($html);
}

$locations = read_json($root.'/data/locations.json');
$locationPages = read_json($root.'/app/build-data/location-pages.json');
$provincePages = read_json($root.'/app/build-data/province-pages.json');
$locTemplate=(string)file_get_contents($root.'/templates/location.html.php');
$provTemplate=(string)file_get_contents($root.'/templates/province.html.php');

$errors=[];$expected=[];$generated=[];
$provinceMap=[]; foreach((array)$locations['provinces'] as $p)$provinceMap[$p['slug']]=$p;

foreach((array)$provincePages['records'] as $r){
    $expected[]='/lokasi-pelayanan/'.$r['slug'].'/';
    $vars=$r; $vars['image_file']=$r['image_file']; $vars['sections']=$r['sections']??[];
    $vars['province_code']=$r['code'] ?? '';
    // The directory list is generated from the administrative source of truth,
    // not editorial placeholder text, so every province links to its real pages.
    $provinceSource=$provinceMap[$r['slug']] ?? null;
    if(is_array($provinceSource)){
        $items=[];
        foreach((array)($provinceSource['locations'] ?? []) as $loc){
            $href='/lokasi-pelayanan/'.$r['slug'].'/'.($loc['slug'] ?? '').'/';
            $items[]='<li><a href="'.esc($href).'">'.esc((string)($loc['name'] ?? '')).'</a></li>';
        }
        $vars['sections']['wilayah']='<section id="wilayah"><details><summary>Daftar Kabupaten/Kota ('.count($items).')</summary><p>Gunakan pencarian wilayah di atas untuk menemukan provinsi, kabupaten, atau kota lainnya.</p><ul>'.implode('', $items).'</ul></details></section>';
    }
    $html=render_template($provTemplate,$vars,render_schema_province($r,$baseUrl));
    $path=$root.'/lokasi-pelayanan/'.$r['slug'].'/index.html';
    if($checkOnly){ if(!is_file($path))$errors[]='Missing province page: '.$path; continue; }
    if(!$checkOnly){ if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true); file_put_contents($path,$html,LOCK_EX); }
    $generated[]=$path;
}
foreach((array)$locationPages['records'] as $r){
    $expected[]='/lokasi-pelayanan/'.$r['province_slug'].'/'.$r['location_slug'].'/';
    $vars=$r; $vars['sections']=$r['sections']??[]; $vars['province_code']=$r['province_code'] ?? ($provinceMap[$r['province_slug']]['code'] ?? '');
    $html=render_template($locTemplate,$vars,render_schema_location($r,$baseUrl));
    $path=$root.'/lokasi-pelayanan/'.$r['province_slug'].'/'.$r['location_slug'].'/index.html';
    if($checkOnly){ if(!is_file($path))$errors[]='Missing location page: '.$path; continue; }
    if(!$checkOnly){ if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true); file_put_contents($path,$html,LOCK_EX); }
    $generated[]=$path;
}

// Required public alias must remain present and unchanged in route shape.
$alias=$root.'/lokasi-pelayanan/dki-jakarta/index.html';
$expected[]='/lokasi-pelayanan/dki-jakarta/';
if(!is_file($alias))$errors[]='Missing DKI public alias: '.$alias;

// Validate uniqueness and URL shape.
$expected=array_values(array_unique($expected));
if(count($expected)!==553)$errors[]='Expected 553 regional URLs, got '.count($expected);
if(count($locationPages['records']??[])!==514)$errors[]='Expected 514 location records.';
if(count($provincePages['records']??[])!==38)$errors[]='Expected 38 province records.';
if($checkOnly){ foreach($expected as $u){ $g=$root.rtrim($u,'/').'/index.html'; if(!is_file($g)) continue; $probe=(string)@file_get_contents($g); if(strpos($probe,'name="build-stamp" content="4.54.17-v45.7"')===false)$errors[]='Build stamp missing: '.$g; } }

if(!$checkOnly){
    @unlink($root.'/data/location-build-manifest.json');
    @unlink($root.'/storage/cache/public-html-files.json');
    @unlink($root.'/storage/cache/public-search-index.json');
}
if($errors){fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL);exit(1);}
echo ($checkOnly?'CHECK OK':'BUILD OK')." - ".count($expected)." regional URLs (38 provinces + 514 kab/kota + 1 DKI alias).\n";
