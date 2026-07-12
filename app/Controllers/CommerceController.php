<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\RateLimiter;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\NewsletterService;
use MaisonBebe\Services\CheckoutService;
use MaisonBebe\Services\StripeService;
use MaisonBebe\Services\WishlistService;

final class CommerceController extends Controller
{
    public function __construct(private readonly CartService $cart = new CartService(), private readonly WishlistService $wishlist = new WishlistService(), private readonly CheckoutService $checkout = new CheckoutService()) {}

    public function cart(Request $request): string
    {
        return $this->storefront('storefront/cart',['totals'=>$this->cart->totals(),'cartCount'=>$this->cart->count(),'wishlistCount'=>$this->wishlist->count(),'meta'=>['title'=>'Coșul tău | Maison Bébé','robots'=>'noindex,follow','canonical'=>absolute_url('/cos')]]);
    }

    public function wishlist(Request $request): string
    {
        return $this->storefront('storefront/wishlist',['products'=>$this->wishlist->items(),'wishlistCount'=>$this->wishlist->count(),'cartCount'=>$this->cart->count(),'meta'=>['title'=>'Favoritele mele | Maison Bébé','robots'=>'noindex,follow','canonical'=>absolute_url('/favorite')]]);
    }

    public function checkout(Request $request): string
    {
        $totals=$this->cart->totals(); if(!$totals['items']){Response::redirect('/cos');}
        $pdo=Database::connection();
        $providers=$pdo->query('SELECT code,name,provider_type FROM payment_providers WHERE is_enabled=1 ORDER BY sort_order')->fetchAll();
        $checkoutCustomer=null;$savedAddresses=[];$checkoutAddress=null;
        if(Auth::id()){
            $customerStatement=$pdo->prepare('SELECT id,email,first_name,last_name,phone FROM users WHERE id=? AND deleted_at IS NULL');$customerStatement->execute([Auth::id()]);$checkoutCustomer=$customerStatement->fetch()?:null;
            $addressStatement=$pdo->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$addressStatement->execute([Auth::id()]);$savedAddresses=$addressStatement->fetchAll();$checkoutAddress=$savedAddresses[0]??null;
        }
        $idempotency=bin2hex(random_bytes(32)); Session::put('checkout_idempotency',$idempotency);
        return $this->storefront('storefront/checkout',['totals'=>$totals,'providers'=>$providers,'idempotency'=>$idempotency,'checkoutCustomer'=>$checkoutCustomer,'savedAddresses'=>$savedAddresses,'checkoutAddress'=>$checkoutAddress,'cartCount'=>$totals['count'],'wishlistCount'=>$this->wishlist->count(),'meta'=>['title'=>'Finalizare comandă | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/checkout')]]);
    }

    public function createOrder(Request $request): never
    {
        $payload=$request->all();
        $checkoutKey=(string)($payload['idempotency_key']??'');if(!preg_match('/^[a-f0-9]{64}$/',$checkoutKey)){throw new HttpException(419,'Sesiunea checkout-ului a expirat.');}
        $order=$this->checkout->create($payload); Session::forget('checkout_idempotency');
        if(($payload['payment_method']??'')==='stripe'){
            try{
                $stripeUrl=(new StripeService())->createCheckoutSession((int)$order['id']);
                Response::redirect($stripeUrl,303);
            }catch(\Throwable $exception){
                error_log('Stripe checkout failed for order '.$order['id'].': '.$exception->getMessage());
                Session::flash('checkout_error','Comanda a fost salvatÃ„Æ’, dar plata online nu a putut porni. Te rugÃ„Æ’m sÃ„Æ’ ne contactezi sau sÃ„Æ’ ÃƒÂ®ncerci din nou.');
            }
        }
        Response::redirect('/comanda-confirmata/'.$order['public_token']);
    }

    public function confirmation(Request $request,string $token): string
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token)){throw new HttpException(404,'Confirmarea nu a fost gÃƒâ€žÃ†â€™sitÃƒâ€žÃ†â€™.');}
        $stripeSessionId=trim((string)$request->input('stripe_session_id',''));
        if($stripeSessionId!==''){
            try{
                (new StripeService())->reconcileCheckoutSession($stripeSessionId,$token);
                Session::flash('payment_notice','Plata cu cardul a fost confirmată în modul Stripe Test.');
            }catch(\Throwable $exception){
                error_log('Stripe return reconciliation failed: '.$exception->getMessage());
                Session::flash('payment_error','Nu am putut confirma încă plata. Verificarea automată va continua prin Stripe.');
            }
        }
        $statement=Database::connection()->prepare('SELECT * FROM orders WHERE public_token=? LIMIT 1');$statement->execute([$token]);$order=$statement->fetch();
        if(!$order){throw new HttpException(404,'Confirmarea nu a fost gÃƒâ€žÃ†â€™sitÃƒâ€žÃ†â€™.');}
        $items=Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');$items->execute([$order['id']]);
        return $this->storefront('storefront/order-confirmation',['order'=>$order,'items'=>$items->fetchAll(),'meta'=>['title'=>'Comandă confirmată | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/comanda-confirmata/'.$token)]]);
    }

    public function tracking(Request $request): string
    {
        $order=null;$history=[];$shipment=null;$error=null;$number='';$email='';
        $token=trim((string)$request->input('token',''));
        if($token!==''&&$request->method==='GET'){
            $statement=Database::connection()->prepare('SELECT id,order_number,email,order_status,grand_total_minor,created_at FROM orders WHERE public_token=? LIMIT 1');$statement->execute([$token]);$order=$statement->fetch();
            if(!$order){$error='Linkul de urmărire nu mai este valid.';}
        }elseif($request->method==='POST'){
            $ip=$_SERVER['REMOTE_ADDR']??'unknown';if(!RateLimiter::hit('tracking:'.$ip,12,3600)){throw new HttpException(429,'Prea multe încercări. Revino mai târziu.');}
            $number=trim((string)$request->input('order_number',''));$email=mb_strtolower(trim((string)$request->input('email','')));
            $statement=Database::connection()->prepare('SELECT id,order_number,email,order_status,grand_total_minor,created_at FROM orders WHERE order_number=? AND email=? LIMIT 1');$statement->execute([$number,$email]);$order=$statement->fetch();
            if(!$order){$error='Nu am găsit o comandă pentru datele introduse.';}
        }
        if($order){$number=(string)$order['order_number'];$email=(string)$order['email'];$h=Database::connection()->prepare('SELECT * FROM order_status_history WHERE order_id=? AND is_public=1 ORDER BY created_at');$h->execute([$order['id']]);$history=$h->fetchAll();$st=Database::connection()->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY id DESC LIMIT 1');$st->execute([$order['id']]);$shipment=$st->fetch()?:null;}
        return $this->storefront('storefront/tracking',compact('order','history','shipment','error','number','email')+['meta'=>['title'=>'Urmărește comanda | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/urmarire-comanda')]]);
    }
    public function subscribeNewsletter(Request $request): never
    {
        $email=mb_strtolower(trim((string)$request->input('email','')));
        try{(new NewsletterService())->subscribe($email,Auth::id());}catch(\InvalidArgumentException $exception){if($request->expectsJson()){Response::json(['ok'=>false,'message'=>$exception->getMessage()],422);}throw new HttpException(422,$exception->getMessage());}
        if($request->expectsJson()){Response::json(['ok'=>true,'message'=>'Te-ai abonat la noutățile Maison Bébé.']);}
        Session::flash('newsletter_notice','Te-ai abonat cu succes.');Response::redirect('/');
    }

    public function unsubscribeNewsletter(Request $request,string $token): string
    {
        $success=(new NewsletterService())->unsubscribe($token);
        return $this->storefront('storefront/newsletter-status',['success'=>$success,'meta'=>['title'=>'Preferințe email | Maison Bébé','robots'=>'noindex,nofollow','canonical'=>absolute_url('/newsletter/dezabonare/'.$token)]]);
    }
    public function contact(Request $request): string
    {
        return $this->storefront('storefront/contact',['sent'=>Session::flash('contact_sent'),'meta'=>['title'=>'Contact | Maison Bébé','description'=>'Scrie-ne pentru ajutor cu o comandÃƒâ€žÃ†â€™ sau alegerea unui dar.','canonical'=>absolute_url('/contact')]]);
    }

    public function sendContact(Request $request): never
    {
        if((string)$request->input('website','')!==''){Response::redirect('/contact');}
        $ip=$_SERVER['REMOTE_ADDR']??'unknown';if(!RateLimiter::hit('contact:'.$ip,5,3600)){throw new HttpException(429,'Ai trimis prea multe mesaje.');}
        $name=trim((string)$request->input('name',''));$email=mb_strtolower(trim((string)$request->input('email','')));$subject=trim((string)$request->input('subject',''));$message=trim((string)$request->input('message',''));
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$subject===''||mb_strlen($message)<10){throw new HttpException(422,'VerificÃƒâ€žÃ†â€™ datele formularului de contact.');}
        $pdo=Database::connection();$pdo->prepare("INSERT INTO contact_messages (name,email,phone,subject,message,ip_hash) VALUES (?,?,?,?,?,?)")->execute([$name,$email,$request->input('phone'),$subject,$message,hash('sha256',$ip.(string)env('APP_KEY'))]);
        $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at) VALUES ('contact_admin',?,?,?,'pending',NOW())")->execute([env('MAIL_FROM_ADDRESS','comenzi@maison-bebe.ro'),'Mesaj nou: '.$subject,json_encode(compact('name','email','subject','message'),JSON_UNESCAPED_UNICODE)]);
        Session::flash('contact_sent',true);Response::redirect('/contact');
    }
}

