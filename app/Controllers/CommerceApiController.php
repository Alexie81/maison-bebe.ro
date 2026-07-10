<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\GiftBoxService;
use MaisonBebe\Services\WishlistService;
use Throwable;

final class CommerceApiController
{
    public function __construct(private readonly CartService $cart = new CartService(), private readonly WishlistService $wishlist = new WishlistService()) {}

    public function cart(Request $request): never
    {
        $totals = $this->cart->totals();
        Response::json(['count'=>$totals['count'],'html'=>view('partials/cart-drawer-content',['totals'=>$totals],'')]);
    }

    public function addCartItem(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload = $request->json() + $request->all();
            $item = $this->cart->add((int)($payload['variant_id']??0),(int)($payload['quantity']??1),(array)($payload['customization']??[]));
            return ['item'=>$item,'cart_count'=>$this->cart->count()];
        });
    }

    public function giftBox(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload = $request->json() + $request->all();
            return (new GiftBoxService())->addConfiguredBox($payload, $this->cart);
        });
    }

    public function updateCartItem(Request $request, string $id): never
    {
        $this->handle(function () use ($request,$id): array {
            $payload=$request->json()+$request->all(); $this->cart->update((int)$id,(int)($payload['quantity']??1));
            return ['totals'=>$this->cart->totals()];
        });
    }

    public function removeCartItem(Request $request, string $id): never
    {
        $this->handle(function () use ($id): array { $this->cart->remove((int)$id); return ['totals'=>$this->cart->totals()]; });
    }

    public function coupon(Request $request): never
    {
        $this->handle(function () use ($request): array { $payload=$request->json()+$request->all(); return ['totals'=>$this->cart->applyCoupon((string)($payload['code']??''))]; });
    }

    public function wishlistToggle(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload=$request->json()+$request->all(); $active=$this->wishlist->toggle((int)($payload['product_id']??0));
            return ['active'=>$active,'count'=>$this->wishlist->count()];
        });
    }

    private function handle(callable $callback): never
    {
        try { Response::json($callback()); }
        catch (HttpException $exception) { Response::json(['message'=>$exception->getMessage()],$exception->status()); }
        catch (Throwable $exception) { error_log($exception->__toString()); Response::json(['message'=>'Operațiunea nu este disponibilă momentan.'],500); }
    }
}

