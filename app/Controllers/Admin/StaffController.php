<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use Throwable;

final class StaffController extends Controller
{
    private function admin(string $view,array $data=[]):string{return view($view,$data+['adminUser'=>Auth::user(),'notice'=>Session::flash('admin_notice'),'error'=>Session::flash('admin_error')],'layouts/admin');}

    public function index(Request $request):string
    {
        $pdo=Database::connection();
        $items=$pdo->query("SELECT u.id,u.email,u.first_name,u.last_name,u.status,u.last_login_at,u.created_at,GROUP_CONCAT(DISTINCT r.label ORDER BY r.label SEPARATOR ', ') roles FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name<>'customer' GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();
        return $this->admin('admin/staff-index',compact('items'));
    }

    public function form(Request $request,?string $id=null):string
    {
        $pdo=Database::connection();$user=null;$selected=[];
        if($id!==null){$statement=$pdo->prepare('SELECT * FROM users WHERE id=? AND deleted_at IS NULL');$statement->execute([(int)$id]);$user=$statement->fetch();if(!$user)throw new HttpException(404,'Utilizatorul nu a fost găsit.');$statement=$pdo->prepare('SELECT DISTINCT rp.permission_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id WHERE ur.user_id=?');$statement->execute([(int)$id]);$selected=array_map('intval',array_column($statement->fetchAll(),'permission_id'));}
        $permissions=$pdo->query("SELECT id,name,label FROM permissions WHERE name<>'*' ORDER BY CASE SUBSTRING_INDEX(name,'.',1) WHEN 'dashboard' THEN 1 WHEN 'orders' THEN 2 WHEN 'products' THEN 3 WHEN 'categories' THEN 4 WHEN 'customers' THEN 5 WHEN 'shipping' THEN 6 WHEN 'billing' THEN 7 WHEN 'cms' THEN 8 WHEN 'atelier' THEN 9 WHEN 'seo' THEN 10 WHEN 'reports' THEN 11 WHEN 'settings' THEN 12 ELSE 20 END,label")->fetchAll();
        return $this->admin('admin/staff-form',compact('user','permissions','selected'));
    }

    public function save(Request $request,?string $id=null):never
    {
        $pdo=Database::connection();$userId=(int)($id??0);$email=mb_strtolower(trim((string)$request->input('email','')));$first=trim((string)$request->input('first_name',''));$last=trim((string)$request->input('last_name',''));$password=(string)$request->input('password','');$status=in_array((string)$request->input('status','active'),['active','blocked'],true)?(string)$request->input('status','active'):'active';$permissions=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('permissions',[])))));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$first===''||$last==='')throw new HttpException(422,'Completează numele, prenumele și un email valid.');
        if($userId===0&&mb_strlen($password)<10)throw new HttpException(422,'Parola inițială trebuie să aibă minimum 10 caractere.');
        $pdo->beginTransaction();
        try{
            if($userId>0){$statement=$pdo->prepare('SELECT id FROM users WHERE id=? AND deleted_at IS NULL FOR UPDATE');$statement->execute([$userId]);if(!$statement->fetchColumn())throw new HttpException(404,'Utilizatorul nu a fost găsit.');$sql='UPDATE users SET email=?,first_name=?,last_name=?,status=?'.($password!==''?',password_hash=?':'').' WHERE id=?';$values=[$email,$first,$last,$status];if($password!=='')$values[]=password_hash($password,PASSWORD_DEFAULT);$values[]=$userId;$pdo->prepare($sql)->execute($values);}else{$pdo->prepare('INSERT INTO users (email,password_hash,first_name,last_name,status,email_verified_at) VALUES (?,?,?,?,?,NOW())')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$first,$last,$status]);$userId=(int)$pdo->lastInsertId();}
            $roleName='staff_user_'.$userId;$roleLabel='Acces personalizat — '.$first.' '.$last;$pdo->prepare('INSERT INTO roles (name,label) VALUES (?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)')->execute([$roleName,$roleLabel]);$roleId=(int)$pdo->query('SELECT id FROM roles WHERE name='.$pdo->quote($roleName))->fetchColumn();
            $pdo->prepare("DELETE ur FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name NOT IN ('customer','super_admin')")->execute([$userId]);$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId,$roleId]);$pdo->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$roleId]);$insert=$pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT ?,id FROM permissions WHERE id=? AND name<>\'*\'');foreach($permissions as $permissionId)$insert->execute([$roleId,$permissionId]);
            $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,metadata_json) VALUES (?,'admin.staff_saved','user',?,?,?)")->execute([Auth::id(),$userId,$_SERVER['REMOTE_ADDR']??null,json_encode(['permissions'=>count($permissions),'status'=>$status],JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();Session::flash('admin_notice','Utilizatorul administrativ a fost salvat.');Response::redirect('/admin/utilizatori');
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof \PDOException&&$exception->getCode()==='23000')throw new HttpException(422,'Există deja un cont cu această adresă de email.');throw $exception;}
    }

    public function toggle(Request $request,string $id):never
    {
        $userId=(int)$id;if($userId===Auth::id())throw new HttpException(422,'Nu îți poți bloca propriul cont.');$pdo=Database::connection();$statement=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name='super_admin')");$statement->execute([$userId]);if($statement->fetchColumn())throw new HttpException(422,'Contul super administrator nu poate fi blocat de aici.');$pdo->prepare("UPDATE users SET status=IF(status='active','blocked','active') WHERE id=? AND deleted_at IS NULL")->execute([$userId]);Session::flash('admin_notice','Starea utilizatorului a fost actualizată.');Response::redirect('/admin/utilizatori');
    }
}