<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\RateLimiter;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;

final class AuthController extends Controller
{
    public function login(Request $request): string
    {
        if(Auth::id()){Response::redirect('/cont');}
        return $this->storefront('auth/login',['error'=>Session::flash('auth_error'),'notice'=>Session::flash('auth_notice'),'meta'=>['title'=>'Autentificare | Maison Bébé','robots'=>'noindex,follow','canonical'=>absolute_url('/cont/autentificare')]]);
    }

    public function authenticate(Request $request): never
    {
        $ip=$_SERVER['REMOTE_ADDR']??'unknown';if(!RateLimiter::hit('login:'.$ip,10,900)){throw new HttpException(429,'Prea multe încercări de autentificare.');}
        $email=mb_strtolower(trim((string)$request->input('email','')));$password=(string)$request->input('password','');
        $statement=Database::connection()->prepare('SELECT id,password_hash,status FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1');$statement->execute([$email]);$user=$statement->fetch();
        if(!$user||!$user['password_hash']||!password_verify($password,$user['password_hash'])||$user['status']!=='active'){Session::flash('auth_error','Emailul sau parola nu sunt corecte.');Response::redirect('/cont/autentificare');}
        Auth::login((int)$user['id']);Database::connection()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        $pending=Session::get('google_pending');if(is_array($pending)&&($pending['email']??'')===$email){$this->linkGoogle((int)$user['id'],$pending);Session::forget('google_pending');}
        $intended=(string)Session::get('intended_url','/cont');Session::forget('intended_url');Response::redirect(str_starts_with($intended,'/')?$intended:'/cont');
    }

    public function register(Request $request): string
    {
        if(Auth::id()){Response::redirect('/cont');}
        return $this->storefront('auth/register',['error'=>Session::flash('auth_error'),'meta'=>['title'=>'Creează cont | Maison Bébé','robots'=>'noindex,follow','canonical'=>absolute_url('/cont/inregistrare')]]);
    }

    public function store(Request $request): never
    {
        $ip=$_SERVER['REMOTE_ADDR']??'unknown';if(!RateLimiter::hit('register:'.$ip,5,3600)){throw new HttpException(429,'Prea multe încercări.');}
        $email=mb_strtolower(trim((string)$request->input('email','')));$password=(string)$request->input('password','');$first=trim((string)$request->input('first_name',''));$last=trim((string)$request->input('last_name',''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||$first===''||$last===''){Session::flash('auth_error','Completează datele și folosește o parolă de cel puțin 12 caractere.');Response::redirect('/cont/inregistrare');}
        $pdo=Database::connection();
        try{$pdo->beginTransaction();$pdo->prepare("INSERT INTO users (email,password_hash,first_name,last_name,status) VALUES (?,?,?,?,'active')")->execute([$email,password_hash($password,PASSWORD_DEFAULT),$first,$last]);$userId=(int)$pdo->lastInsertId();$roleId=(int)$pdo->query("SELECT id FROM roles WHERE name='customer'")->fetchColumn();$pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId,$roleId]);$pdo->commit();Auth::login($userId);Response::redirect('/cont');}catch(\PDOException $exception){if($pdo->inTransaction())$pdo->rollBack();Session::flash('auth_error','Există deja un cont pentru această adresă de email.');Response::redirect('/cont/inregistrare');}
    }

    public function logout(Request $request): never{Auth::logout();Response::redirect('/');}

    public function reset(Request $request): string
    {
        return $this->storefront('auth/reset',['sent'=>Session::flash('reset_sent'),'meta'=>['title'=>'Resetare parolă | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/resetare-parola')]]);
    }

    public function sendReset(Request $request): never
    {
        $ip=$_SERVER['REMOTE_ADDR']??'unknown';if(!RateLimiter::hit('reset:'.$ip,5,3600)){throw new HttpException(429,'Prea multe încercări.');}
        $email=mb_strtolower(trim((string)$request->input('email','')));$statement=Database::connection()->prepare('SELECT id FROM users WHERE email=? AND status=\'active\' AND deleted_at IS NULL');$statement->execute([$email]);$id=$statement->fetchColumn();
        if($id){$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$pdo=Database::connection();$pdo->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))')->execute([$id,$hash]);$pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at) VALUES ('password_reset',?,'Resetează parola Maison Bébé',?,'pending',NOW())")->execute([$email,json_encode(['reset_url'=>absolute_url('/cont/parola-noua/'.$token)],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);}
        Session::flash('reset_sent',true);Response::redirect('/cont/resetare-parola');
    }

    public function newPassword(Request $request,string $token): string
    {
        $valid=$this->resetRow($token);if(!$valid){throw new HttpException(404,'Linkul de resetare este invalid sau a expirat.');}
        return $this->storefront('auth/new-password',['token'=>$token,'error'=>Session::flash('auth_error'),'meta'=>['title'=>'Alege o parolă nouă | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/parola-noua/'.$token)]]);
    }

    public function updatePassword(Request $request,string $token): never
    {
        $row=$this->resetRow($token);$password=(string)$request->input('password','');if(!$row||strlen($password)<12){Session::flash('auth_error','Link invalid sau parolă prea scurtă.');Response::redirect('/cont/parola-noua/'.$token);}
        $pdo=Database::connection();$pdo->beginTransaction();$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$row['user_id']]);$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$row['id']]);$pdo->commit();Session::flash('auth_notice','Parola a fost actualizată. Te poți autentifica.');Response::redirect('/cont/autentificare');
    }

    public function google(Request $request): never
    {
        $config=$this->googleConfig();$clientId=$config['client_id'];$redirect=$config['redirect_uri'];if(!$config['enabled']||$clientId===''||$redirect===''){Session::flash('auth_error','Autentificarea Google nu este configurată încă.');Response::redirect('/cont/autentificare');}
        $state=bin2hex(random_bytes(24));Session::put('google_oauth_state',$state);$query=http_build_query(['client_id'=>$clientId,'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'prompt'=>'select_account','access_type'=>'online']);Response::redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function googleCallback(Request $request): never
    {
        $state=(string)$request->input('state','');if($state===''||!hash_equals((string)Session::get('google_oauth_state',''),$state)){throw new HttpException(419,'Starea OAuth nu este validă.');}Session::forget('google_oauth_state');$code=(string)$request->input('code','');if($code===''){Session::flash('auth_error','Autentificarea Google a fost anulată.');Response::redirect('/cont/autentificare');}
        $config=$this->googleConfig();if(!$config['enabled']){throw new HttpException(503,'Autentificarea Google nu este configurată.');}$token=$this->httpPost('https://oauth2.googleapis.com/token',['code'=>$code,'client_id'=>$config['client_id'],'client_secret'=>$config['client_secret'],'redirect_uri'=>$config['redirect_uri'],'grant_type'=>'authorization_code']);$idToken=$token['id_token']??'';if(!$idToken){throw new HttpException(502,'Google nu a returnat identitatea.');}
        $claims=$this->httpGet('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($idToken));if(($claims['aud']??'')!==$config['client_id']||!in_array($claims['iss']??'',['accounts.google.com','https://accounts.google.com'],true)||!filter_var($claims['email_verified']??false,FILTER_VALIDATE_BOOLEAN)||empty($claims['sub'])){throw new HttpException(403,'Identitatea Google nu a putut fi verificată.');}
        $pdo=Database::connection();$statement=$pdo->prepare("SELECT user_id FROM oauth_accounts WHERE provider='google' AND provider_user_id=?");$statement->execute([$claims['sub']]);$userId=$statement->fetchColumn();if($userId){Auth::login((int)$userId);Response::redirect('/cont');}
        $existing=$pdo->prepare('SELECT id FROM users WHERE email=? AND deleted_at IS NULL');$existing->execute([mb_strtolower($claims['email'])]);if($existing->fetchColumn()){Session::put('google_pending',$claims);Session::flash('auth_notice','Autentifică-te cu parola existentă pentru a lega în siguranță contul Google.');Response::redirect('/cont/autentificare');}
        $pdo->beginTransaction();$pdo->prepare("INSERT INTO users (email,first_name,last_name,status,email_verified_at) VALUES (?,?,?,'active',NOW())")->execute([mb_strtolower($claims['email']),$claims['given_name']??'Client',$claims['family_name']??'Maison Bébé']);$userId=(int)$pdo->lastInsertId();$this->linkGoogle($userId,$claims);$roleId=(int)$pdo->query("SELECT id FROM roles WHERE name='customer'")->fetchColumn();$pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId,$roleId]);$pdo->commit();Auth::login($userId);Response::redirect('/cont');
    }

    private function googleConfig():array{$statement=Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=\'google_auth\'');$statement->execute();$stored=json_decode((string)$statement->fetchColumn(),true)?:[];if(!empty($stored['enabled'])&&!empty($stored['client_id'])&&!empty($stored['encrypted_client_secret'])){return ['enabled'=>true,'client_id'=>(string)$stored['client_id'],'client_secret'=>Encryptor::decrypt((string)$stored['encrypted_client_secret']),'redirect_uri'=>(string)($stored['redirect_uri']??absolute_url('/auth/google/callback'))];}return ['enabled'=>(string)env('GOOGLE_CLIENT_ID','')!=='','client_id'=>(string)env('GOOGLE_CLIENT_ID',''),'client_secret'=>(string)env('GOOGLE_CLIENT_SECRET',''),'redirect_uri'=>(string)env('GOOGLE_REDIRECT_URI',absolute_url('/auth/google/callback'))];}
    private function linkGoogle(int $userId,array $claims):void{Database::connection()->prepare("INSERT IGNORE INTO oauth_accounts (user_id,provider,provider_user_id,provider_email,metadata_json) VALUES (?,'google',?,?,?)")->execute([$userId,$claims['sub'],$claims['email']??null,json_encode(['name'=>$claims['name']??null],JSON_UNESCAPED_UNICODE)]);}
    private function resetRow(string $token):array|false{$statement=Database::connection()->prepare('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$statement->execute([hash('sha256',$token)]);return $statement->fetch();}
    private function httpPost(string $url,array $data):array{$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Accept: application/json']]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$decoded=json_decode((string)$body,true);if($status<200||$status>=300||!is_array($decoded)){throw new HttpException(502,'Serviciul Google nu este disponibil.');}return $decoded;}
    private function httpGet(string $url):array{$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Accept: application/json']]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$decoded=json_decode((string)$body,true);if($status!==200||!is_array($decoded)){throw new HttpException(502,'Identitatea Google nu poate fi verificată.');}return $decoded;}
}

