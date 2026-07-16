<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\NewsletterService;
use MaisonBebe\Services\WishlistService;

final class AccountController extends Controller
{
    public function dashboard(Request $request): string
    {
        $orders=$this->ordersForUser(5);$addresses=$this->addresses();return $this->storefront('account/dashboard',compact('orders','addresses')+['wishlistCount'=>(new WishlistService())->count(),'meta'=>['title'=>'Contul meu | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont')]]);
    }
    public function orders(Request $request): string{$orders=$this->ordersForUser(50);return $this->storefront('account/orders',compact('orders')+['meta'=>['title'=>'Comenzile mele | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/comenzi')]]);}
    public function order(Request $request,string $number): string
    {
        $statement=Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND user_id=? LIMIT 1');$statement->execute([$number,Auth::id()]);$order=$statement->fetch();if(!$order){throw new HttpException(404,'Comanda nu a fost găsită.');}
        $items=Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=?');$items->execute([$order['id']]);$history=Database::connection()->prepare('SELECT * FROM order_status_history WHERE order_id=? AND is_public=1 ORDER BY created_at');$history->execute([$order['id']]);$shipment=Database::connection()->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY id DESC LIMIT 1');$shipment->execute([$order['id']]);
        return $this->storefront('account/order',['order'=>$order,'items'=>$items->fetchAll(),'history'=>$history->fetchAll(),'shipment'=>$shipment->fetch()?:null,'meta'=>['title'=>'Comanda '.$number.' | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/comenzi/'.$number)]]);
    }
    public function addressesPage(Request $request): string
    {
        $statement=Database::connection()->prepare('SELECT id,email,first_name,last_name,phone FROM users WHERE id=?');$statement->execute([Auth::id()]);$profile=$statement->fetch()?:[];
        return $this->storefront('account/addresses',['addresses'=>$this->addresses(),'profile'=>$profile,'meta'=>['title'=>'Adresele mele | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/adrese')]]);
    }
    public function personalPage(Request $request): string
    {
        $statement=Database::connection()->prepare('SELECT id,email,first_name,last_name,phone FROM users WHERE id=? AND deleted_at IS NULL');$statement->execute([Auth::id()]);$profile=$statement->fetch();
        $newsletter=(new NewsletterService())->preferencesForUser((int)Auth::id());
        return $this->storefront('account/profile',compact('profile','newsletter')+['notice'=>Session::flash('account_notice'),'meta'=>['title'=>'Date personale | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/date-personale')]]);
    }

    public function savePersonal(Request $request): never
    {
        $first=trim((string)$request->input('first_name',''));$last=trim((string)$request->input('last_name',''));$phone=trim((string)$request->input('phone',''));$email=mb_strtolower(trim((string)$request->input('email','')));
        if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){throw new HttpException(422,'Completează corect numele, prenumele și adresa de email.');}
        $pdo=Database::connection();$duplicate=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL');$duplicate->execute([$email,Auth::id()]);if($duplicate->fetchColumn()){throw new HttpException(422,'Adresa de email este deja folosită de alt cont.');}
        $pdo->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,email=?,updated_at=NOW() WHERE id=?')->execute([$first,$last,$phone?:null,$email,Auth::id()]);
        (new NewsletterService())->syncUserEmail((int)Auth::id(),$email);Session::flash('account_notice','Datele personale au fost actualizate.');Response::redirect('/cont/date-personale');
    }

    public function saveEmailPreferences(Request $request): never
    {
        $statement=Database::connection()->prepare('SELECT email FROM users WHERE id=?');$statement->execute([Auth::id()]);$email=(string)$statement->fetchColumn();
        (new NewsletterService())->updatePreferences((int)Auth::id(),$email,(bool)$request->input('product_updates'),(bool)$request->input('article_updates'));
        Session::flash('account_notice','Preferințele de email au fost salvate.');Response::redirect('/cont/date-personale');
    }

    public function couponsPage(Request $request): string
    {
        $pdo=Database::connection();$userId=(int)Auth::id();
        $statement=$pdo->prepare("SELECT c.*,(SELECT COUNT(*) FROM coupon_usages cu WHERE cu.coupon_id=c.id) used_total,(SELECT COUNT(*) FROM coupon_usages cu WHERE cu.coupon_id=c.id AND cu.user_id=?) used_by_user,(SELECT GROUP_CONCAT(CONCAT(IF(cp.mode='exclude','Exceptat: ','Eligibil: '),p.name) ORDER BY cp.mode,p.name SEPARATOR ' · ') FROM coupon_products cp JOIN products p ON p.id=cp.product_id WHERE cp.coupon_id=c.id) product_names,(SELECT GROUP_CONCAT(CONCAT(IF(cc.mode='exclude','Exceptată: ','Eligibilă: '),cat.name) ORDER BY cc.mode,cat.name SEPARATOR ' · ') FROM coupon_categories cc JOIN categories cat ON cat.id=cc.category_id WHERE cc.coupon_id=c.id) category_names,(SELECT GROUP_CONCAT(CONCAT(IF(cco.mode='exclude','Exceptată: ','Eligibilă: '),col.name) ORDER BY cco.mode,col.name SEPARATOR ' · ') FROM coupon_collections cco JOIN collections col ON col.id=cco.collection_id WHERE cco.coupon_id=c.id) collection_names FROM coupons c ORDER BY COALESCE(c.ends_at,'9999-12-31'),c.created_at DESC");
        $statement->execute([$userId]);$coupons=$statement->fetchAll();
        return $this->storefront('account/coupons',compact('coupons')+['meta'=>['title'=>'Cupoanele mele | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/cupoane')]]);
    }
    public function saveAddress(Request $request, ?string $id = null): never
    {
        $name = trim((string) $request->input('name', ''));
        $contactFirstName = trim((string) $request->input('contact_first_name', ''));
        $contactLastName = trim((string) $request->input('contact_last_name', ''));
        $line1 = trim((string) $request->input('line1', ''));
        $line2 = trim((string) $request->input('line2', ''));
        $city = trim((string) $request->input('city', ''));
        $county = trim((string) $request->input('county', ''));
        $postalCode = trim((string) $request->input('postal_code', ''));
        $phone = trim((string) $request->input('phone', ''));

        if ($name === '' || $contactFirstName === '' || $contactLastName === '' || $line1 === '' || $city === '') {
            throw new HttpException(422, 'Completează eticheta adresei, prenumele și numele persoanei de contact, adresa și localitatea.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $isDefault = $request->input('is_default') ? 1 : 0;
            if (!$id) {
                $count = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id=?');
                $count->execute([Auth::id()]);
                if ((int) $count->fetchColumn() === 0) {
                    $isDefault = 1;
                }
            }
            if ($isDefault) {
                $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([Auth::id()]);
            }

            if ($id) {
                $owned = $pdo->prepare('SELECT id FROM user_addresses WHERE id=? AND user_id=?');
                $owned->execute([(int)$id,Auth::id()]);
                if (!$owned->fetchColumn()) {
                    throw new HttpException(404, 'Adresa nu a fost găsită.');
                }
                $pdo->prepare('UPDATE user_addresses SET name=?,contact_first_name=?,contact_last_name=?,line1=?,line2=?,city=?,county=?,postal_code=?,phone=?,is_default=?,updated_at=NOW() WHERE id=? AND user_id=?')
                    ->execute([$name,$contactFirstName,$contactLastName,$line1,$line2 ?: null,$city,$county ?: null,$postalCode ?: null,$phone ?: null,$isDefault,(int)$id,Auth::id()]);
            } else {
                $pdo->prepare("INSERT INTO user_addresses (user_id,type,name,contact_first_name,contact_last_name,line1,line2,city,county,postal_code,country_code,phone,is_default) VALUES (?,'both',?,?,?,?,?,?,?,?,'RO',?,?)")
                    ->execute([Auth::id(),$name,$contactFirstName,$contactLastName,$line1,$line2 ?: null,$city,$county ?: null,$postalCode ?: null,$phone ?: null,$isDefault]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        Response::redirect('/cont/adrese');
    }
    private function ordersForUser(int $limit):array{$statement=Database::connection()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT ?');$statement->bindValue(1,Auth::id(),\PDO::PARAM_INT);$statement->bindValue(2,$limit,\PDO::PARAM_INT);$statement->execute();return $statement->fetchAll();}
    private function addresses():array{$statement=Database::connection()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$statement->execute([Auth::id()]);return $statement->fetchAll();}
}
