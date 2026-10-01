<?php
declare(strict_types=1);
require_once __DIR__.'/../app/site.php';
function auth_head(string $title): void { ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="robots" content="noindex,nofollow"><title><?=e($title)?> - Administrator</title>
<link rel="stylesheet" href="/assets/css/minimal.css">
<link rel="manifest" href="/manifest.webmanifest">
</head><body>

<?php }
function auth_open(string $backHref,string $backLabel='Kembali'): void { ?>
<main class="auth-shell"><header class="auth-header"><a class="auth-brand" href="/"><img data-site-logo src="<?=e(site_logo_url())?>" alt="" width="40" height="40"><span>Administrator</span></a><a class="auth-back" href="<?=e($backHref)?>"><?=e($backLabel)?></a></header><div class="auth-card">
<?php }
function auth_close(string $foot): void { ?></div><p class="auth-footer"><?=e($foot)?></p></main></body></html><?php }
