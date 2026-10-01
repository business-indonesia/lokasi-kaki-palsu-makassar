<?php
declare(strict_types=1);

// PHP 7.4 compatibility for helpers introduced in PHP 8.0.
if (!function_exists('str_contains')) { function str_contains(string $haystack, string $needle): bool { return $needle === '' || strpos($haystack, $needle) !== false; } }
if (!function_exists('str_starts_with')) { function str_starts_with(string $haystack, string $needle): bool { return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0; } }
if (!function_exists('str_ends_with')) { function str_ends_with(string $haystack, string $needle): bool { if ($needle === '') return true; return substr($haystack, -strlen($needle)) === $needle; } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s, ?string $encoding = null): int { preg_match_all('/./us', $s, $m); return count($m[0]); } }

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('Admin belum dikonfigurasi. Jalankan instalasi aplikasi terlebih dahulu.'); }
$config = require $configFile;
if (!is_array($config)) { $config = []; }
$config['mail'] = array_merge(['transport'=>'smtp','provider'=>'gmail','from_email'=>'indonesiad@gmail.com','from_name'=>'Administrator','smtp_host'=>'smtp.gmail.com','smtp_port'=>465,'smtp_security'=>'ssl','smtp_username'=>'indonesiad@gmail.com','smtp_password_enc'=>'','smtp_verified_at'=>'','smtp_verified_to'=>'','last_test_error'=>''], (array)($config['mail'] ?? []));
// Email System: configuration is authoritative. No hard-coded credential or sender is forced here.
require_once __DIR__.'/mail.php';
if (!is_array($config) || !isset($config['admin']['username'], $config['admin']['pin_hash'], $config['admin']['session_secret'])) { http_response_code(500); exit('Konfigurasi aplikasi tidak valid.'); }
if (strlen((string)$config['admin']['session_secret']) < 64) { http_response_code(500); exit('Session secret belum dikonfigurasi dengan aman.'); }
if (PHP_SAPI !== 'cli') { header('X-Robots-Tag: noindex, nofollow', true); }

// Admin safety net: buffer the response so an unexpected fatal error never leaves a blank page.
// JSON requests receive JSON; admin HTML receives a branded recovery screen.
if (PHP_SAPI !== 'cli') {
    ob_start();
    register_shutdown_function(function (): void {
        $e = error_get_last();
        if (!$e || !in_array((int)$e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) return;
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $isJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
        while (ob_get_level() > 0) @ob_end_clean();
        $msg = 'Terjadi kesalahan internal. Tidak ada perubahan yang dijalankan. Silakan muat ulang halaman dan coba lagi.';
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
        @file_put_contents($logDir.'/php-errors.log', date('c').' | '.$e['type'].' | '.($e['message'] ?? '').' | '.($e['file'] ?? '').':'.($e['line'] ?? '').PHP_EOL, FILE_APPEND|LOCK_EX);
        http_response_code(500);
        if ($isJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); return; }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="robots" content="noindex,nofollow"><title>Admin - Terjadi Kesalahan</title></head><body><main><h1>Admin tetap aman</h1><p>'.$msg.'</p><div><a href="/admin/settings.php">Kembali ke Admin</a> <a href="/admin/">Kembali</a></div></main></body></html>';
    });
}

// Public HTML/API requests remain stateless. The secure session is created only for Admin and Install routes.
$requestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$needsSession = (bool)preg_match('~^/admin(?:/|$)|^/install\.php$~i', $requestPath);
if ($needsSession) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('lokasi_admin');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Lax',
            'path' => '/admin/',
        ]);
        session_start();
    }
    if (!empty($_SESSION['admin_ok']) && !empty($_SESSION['admin_last_activity']) && time() - (int)$_SESSION['admin_last_activity'] > 7200) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']); }
        session_destroy();
        session_start();
    }
    if (!empty($_SESSION['admin_ok'])) $_SESSION['admin_last_activity'] = time();
}

function app_root(): string { return dirname(__DIR__); }
function storage_file(string $name): string { return app_root() . '/storage/' . ltrim($name, '/'); }
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void {
    $provided = (string)($_POST['csrf'] ?? '');
    if ($provided === '') {
        $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    if ($provided === '') {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string)file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) $provided = (string)($json['csrf'] ?? '');
        }
    }
    $expected = (string)($_SESSION['csrf'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'CSRF validation failed. Refresh halaman admin lalu coba lagi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
function require_admin(): void { if (empty($_SESSION['admin_ok']) || empty($_SESSION['admin_user'])) { header('Location: /admin/login.php', true, 303); exit; } $_SESSION['admin_last_activity'] = time(); }
function save_config(array $d): void { global $configFile; $php="<?php\nreturn ".var_export($d,true).";\n"; $tmp=$configFile.'.tmp-'.bin2hex(random_bytes(4)); if(@file_put_contents($tmp,$php,LOCK_EX)===false) throw new RuntimeException('Konfigurasi tidak dapat disimpan. Periksa permission app/config.php.'); @chmod($tmp,0600); if(!@rename($tmp,$configFile)){ @unlink($tmp); throw new RuntimeException('Konfigurasi tidak dapat disimpan. Periksa permission folder app.'); } }
function current_admin(): array { global $config; return (array)($config['admin'] ?? []); }
function settings(): array {
    static $cache=null;
    static $cacheVersion='';
    $f=storage_file('settings.json');
    $version=is_file($f)?((string)@filemtime($f).':'.(string)@filesize($f)):'missing';
    $bust=(string)($GLOBALS['_settings_cache_bust']??'');
    if(is_array($cache) && $bust==='' && $cacheVersion===$version) return $cache;
    $d=is_file($f)?json_decode((string)@file_get_contents($f),true):[];
    $cache=is_array($d)?$d:[];
    $cacheVersion=$version;
    unset($GLOBALS['_settings_cache_bust']);
    return $cache;
}
function save_settings(array $d): void { $f = storage_file('settings.json'); $json=json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if($json===false) throw new RuntimeException('Pengaturan tidak dapat dienkode.'); $tmp=$f.'.tmp-'.bin2hex(random_bytes(4)); if(@file_put_contents($tmp,$json,LOCK_EX)===false) throw new RuntimeException('Pengaturan tidak dapat disimpan. Periksa permission storage.'); @chmod($tmp,0640); if(!@rename($tmp,$f)){@unlink($tmp); throw new RuntimeException('Pengaturan tidak dapat dipublikasikan. Periksa permission storage.');} $GLOBALS['_settings_cache_bust']=microtime(true); $check=json_decode((string)@file_get_contents($f),true); if(!is_array($check)) throw new RuntimeException('Pengaturan tersimpan tetapi gagal diverifikasi.'); }

