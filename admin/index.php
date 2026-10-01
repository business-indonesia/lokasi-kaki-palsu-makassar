<?php
declare(strict_types=1);
require_once __DIR__.'/_layout.php';
require_admin();
header('Location: /admin/settings.php',true,303);
exit;
