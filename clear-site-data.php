<?php
declare(strict_types=1);
header('Clear-Site-Data: "cache", "storage", "cookies"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
http_response_code(204);
exit;
