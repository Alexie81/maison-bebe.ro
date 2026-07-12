<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
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
    public function addressesPage(Request $request):string{return $this->storefront('account/addresses',['addresses'=>$this->addresses(),'meta'=>['title'=>'Adresele mele | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/cont/adrese')]]);}
    public function saveAddress(Request $request):never{$name=trim((string)$request->input('name',''));$line=trim((string)$request->input('line1',''));$city=trim((string)$request->input('city',''));if($name===''||$line===''||$city===''){throw new HttpException(422,'Completează câmpurile obligatorii.');}Database::connection()->prepare('INSERT INTO user_addresses (user_id,type,name,line1,line2,city,county,postal_code,country_code,phone,is_default) VALUES (?,\'both\',?,?,?,?,?,?,\'RO\',?,?)')->execute([Auth::id(),$name,$line,$request->input('line2'),$city,$request->input('county'),$request->input('postal_code'),$request->input('phone'),$request->input('is_default')?1:0]);Response::redirect('/cont/adrese');}
    private function ordersForUser(int $limit):array{$statement=Database::connection()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT ?');$statement->bindValue(1,Auth::id(),\PDO::PARAM_INT);$statement->bindValue(2,$limit,\PDO::PARAM_INT);$statement->execute();return $statement->fetchAll();}
    private function addresses():array{$statement=Database::connection()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$statement->execute([Auth::id()]);return $statement->fetchAll();}
}

