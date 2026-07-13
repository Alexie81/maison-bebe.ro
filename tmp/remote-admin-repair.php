<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$tokenFile=dirname(__DIR__).'/.maison-admin-token';
$provided=$_SERVER['HTTP_X_DEPLOY_TOKEN']??'';
$expected=is_file($tokenFile)?trim((string)file_get_contents($tokenFile)):'';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||$expected===''||!hash_equals($expected,$provided)){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
try{
 require __DIR__.'/bootstrap.php';
 $email=mb_strtolower(trim((string)($_POST['email']??'')));
 $password=(string)($_POST['password']??'');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)throw new RuntimeException('Date administrative invalide.');
 $pdo=MaisonBebe\Core\Database::connection();$pdo->beginTransaction();
 $stmt=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1 FOR UPDATE');$stmt->execute([$email]);$userId=(int)$stmt->fetchColumn();
 $hash=password_hash($password,PASSWORD_DEFAULT);
 if($userId){$pdo->prepare("UPDATE users SET password_hash=?,status='active',deleted_at=NULL,email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$hash,$userId]);}
 else{$pdo->prepare("INSERT INTO users (email,password_hash,first_name,last_name,status,email_verified_at) VALUES (?,?,'Admin','Maison Bébé','active',NOW())")->execute([$email,$hash]);$userId=(int)$pdo->lastInsertId();}
 $pdo->prepare("INSERT INTO roles (name,label) VALUES ('super_admin','Super administrator') ON DUPLICATE KEY UPDATE label=VALUES(label)")->execute();
 $roleId=(int)$pdo->query("SELECT id FROM roles WHERE name='super_admin' LIMIT 1")->fetchColumn();
 $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId,$roleId]);
 $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT ?,id FROM permissions')->execute([$roleId]);
 $pdo->prepare("UPDATE company_profiles SET vat_status='plătitor',vat_code='RO26283407' WHERE REPLACE(REPLACE(UPPER(tax_id),'RO',''),' ','')='26283407'")->execute();
 $migration='012_confirm_teraunis_vat_status.sql';
 $exists=$pdo->prepare('SELECT 1 FROM migrations WHERE migration=?');$exists->execute([$migration]);
 if(!$exists->fetchColumn()){$batch=(int)$pdo->query('SELECT COALESCE(MAX(batch),0)+1 FROM migrations')->fetchColumn();$pdo->prepare('INSERT INTO migrations (migration,batch) VALUES (?,?)')->execute([$migration,$batch]);}
 $pdo->commit();
 $check=$pdo->prepare("SELECT u.status,password_hash,EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.name='super_admin') is_admin FROM users u WHERE u.id=?");$check->execute([$userId]);$row=$check->fetch();
 @unlink($tokenFile);@unlink(__FILE__);
 echo json_encode(['ok'=>true,'email'=>$email,'status'=>$row['status']??null,'super_admin'=>(bool)($row['is_admin']??false),'password_verified'=>password_verify($password,(string)($row['password_hash']??''))],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}