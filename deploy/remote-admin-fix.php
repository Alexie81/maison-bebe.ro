<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$config=[];$path=dirname(__DIR__).'/.maison-db-env';foreach(is_file($path)?(file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[]):[] as $line){if(!str_starts_with(trim($line),'#')&&str_contains($line,'=')){[$key,$value]=array_map('trim',explode('=',$line,2));$config[$key]=$value;}}
$authorization=$_SERVER['HTTP_AUTHORIZATION']??'';$token=preg_match('/^Bearer\s+(.+)$/i',$authorization,$matches)?$matches[1]:'';
if($_SERVER['REQUEST_METHOD']!=='POST'||empty($config['REMOTE_HEALTH_TOKEN'])||!hash_equals($config['REMOTE_HEALTH_TOKEN'],$token)){http_response_code(404);echo '{}';exit;}
try{require __DIR__.'/bootstrap.php';$pdo=MaisonBebe\Core\Database::connection();$pdo->beginTransaction();$user=$pdo->prepare('SELECT id FROM users WHERE email=? FOR UPDATE');$user->execute(['admin@maison-bebe.ro']);$userId=(int)$user->fetchColumn();$roleId=(int)$pdo->query("SELECT id FROM roles WHERE name='super_admin'")->fetchColumn();if(!$userId||!$roleId){throw new RuntimeException('Contul sau rolul lipsește.');}$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId,$roleId]);$pdo->commit();@unlink(__FILE__);echo json_encode(['ok'=>true,'user_id'=>$userId,'role'=>'super_admin']);}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
