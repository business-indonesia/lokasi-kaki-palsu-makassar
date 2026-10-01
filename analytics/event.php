<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/site.php';
require_once __DIR__.'/../app/rate-limit.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['ok'=>false]); exit; }
$rl=rl_check('analytics-event',60,60,2000); if(!$rl['allowed']){ http_response_code(429); header('Retry-After: '.(int)$rl['retry_after']); echo json_encode(['ok'=>false,'error'=>'Rate limit']); exit; }
$raw=(string)file_get_contents('php://input'); $data=json_decode($raw,true);
if(!is_array($data)){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$event=trim((string)($data['event']??'')); $path=(string)($data['path']??'');
if(!in_array($event,analytics_event_allowlist(),true)){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$path=parse_url($path,PHP_URL_PATH) ?: '/';
$params=is_array($data['params']??null)?$data['params']:[];
analytics_log_event($event,$path,$params);
echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);
