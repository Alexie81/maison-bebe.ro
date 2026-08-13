<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\GiftBoxService;
use MaisonBebe\Services\GoogleAnalyticsService;
use MaisonBebe\Services\WishlistService;
use Throwable;

final class CommerceApiController
{
    public function __construct(private readonly CartService $cart = new CartService(), private readonly WishlistService $wishlist = new WishlistService()) {}

    public function cart(Request $request): never
    {
        $totals = $this->cart->totals();
        Response::json(['count'=>$totals['count'],'html'=>view('partials/cart-drawer-content',['totals'=>$totals],''),'analytics'=>$totals['items'] ? ['event'=>'view_cart','params'=>(new GoogleAnalyticsService())->cartPayload($totals)] : null]);
    }

    public function addCartItem(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload = $request->json() + $request->all();
            $customization = (array) ($payload['customization'] ?? []);
            $customization['optional_variant_ids'] = (array) ($payload['optional_variant_ids'] ?? []);
            $customization['personalization_option_ids'] = (array) ($payload['personalization_option_ids'] ?? []);
            if (!$customization['personalization_option_ids'] && (int) ($payload['personalization_option_id'] ?? 0) > 0) {
                $customization['personalization_option_ids'] = [(int) $payload['personalization_option_id']];
            }
            $customization['personalization_child_name'] = (string) ($payload['personalization_child_name'] ?? '');
            $customization['personalization_birth_date'] = (string) ($payload['personalization_birth_date'] ?? '');
            $payload['customization'] = $customization;
            $result = (int)($payload['gift_box_template_id'] ?? 0) > 0
                ? (new GiftBoxService())->addProductWithBox($payload, $this->cart)
                : ['item'=>$this->cart->add((int)($payload['variant_id']??0),(int)($payload['quantity']??1),$customization),'cart_count'=>$this->cart->count(),'active'=>true];
            return $this->withAddedAnalytics($result);
        });
    }

    public function toggleCartProduct(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload = $request->json() + $request->all();
            $productId = (int) ($payload['product_id'] ?? 0);
            $variantId = (int) ($payload['variant_id'] ?? 0);
            if ($productId > 0 && $this->cart->normalItemIdForProduct($productId)) {
                $removed = $this->matchingCartItems($this->cart->totals(), null, $productId);
                $this->cart->removeProduct($productId);
                return ['active'=>false,'product_id'=>$productId,'cart_count'=>$this->cart->count(),'analytics'=>['event'=>'remove_from_cart','params'=>(new GoogleAnalyticsService())->cartMutationPayload($removed)]];
            }
            $item = $this->cart->add($variantId, 1);
            return $this->withAddedAnalytics(['active'=>true,'product_id'=>(int) ($item['product_id'] ?? $productId),'item'=>$item,'cart_count'=>$this->cart->count()]);
        });
    }

    public function giftBox(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload = $request->json() + $request->all();
            return $this->withAddedAnalytics((new GiftBoxService())->addConfiguredBox($payload, $this->cart));
        });
    }

    public function updateCartItem(Request $request, string $id): never
    {
        $this->handle(function () use ($request,$id): array {
            $payload=$request->json()+$request->all();
            $itemId=(int)$id;
            $beforeTotals=$this->cart->totals();
            $before=$this->matchingCartItems($beforeTotals,$itemId);
            $oldQuantity=(int)($before[0]['quantity']??0);
            $newQuantity=(int)($payload['quantity']??1);
            $this->cart->update($itemId,$newQuantity);
            $totals=$this->cart->totals();
            $delta=$newQuantity-$oldQuantity;
            $event=$delta>=0?'add_to_cart':'remove_from_cart';
            $source=$delta>=0?$this->matchingCartItems($totals,$itemId):$before;
            $overrides=$source ? [(int)$source[0]['id']=>max(1,abs($delta))] : [];
            return ['totals'=>$totals,'analytics'=>$delta!==0&&$source ? ['event'=>$event,'params'=>(new GoogleAnalyticsService())->cartMutationPayload($source,$overrides)] : null];
        });
    }

    public function removeCartItem(Request $request, string $id): never
    {
        $this->handle(function () use ($id): array {
            $itemId=(int)$id;
            $before=$this->matchingCartItems($this->cart->totals(),$itemId);
            $this->cart->remove($itemId);
            return ['totals'=>$this->cart->totals(),'analytics'=>$before ? ['event'=>'remove_from_cart','params'=>(new GoogleAnalyticsService())->cartMutationPayload($before)] : null];
        });
    }

    public function coupon(Request $request): never
    {
        $this->handle(function () use ($request): array { $payload=$request->json()+$request->all(); return ['totals'=>$this->cart->applyCoupon((string)($payload['code']??''))]; });
    }

    public function wishlistToggle(Request $request): never
    {
        $this->handle(function () use ($request): array {
            $payload=$request->json()+$request->all(); $productId=(int)($payload['product_id']??0);$active=$this->wishlist->toggle($productId);
            $item=(new GoogleAnalyticsService())->productItemById($productId);
            return ['active'=>$active,'count'=>$this->wishlist->count(),'analytics'=>$active&&$item ? ['event'=>'add_to_wishlist','params'=>['currency'=>'RON','value'=>$item['price'],'items'=>[$item]]] : null];
        });
    }

    private function withAddedAnalytics(array $result): array
    {
        $totals=$this->cart->totals();
        $group=trim((string)($result['group']??''));
        $itemId=(int)($result['item']['item_id']??0);
        $items=$this->matchingCartItems($totals,$itemId?:null,null,$group?:null);
        $overrides=[];
        foreach (['item','box'] as $key) {
            $id=(int)($result[$key]['item_id']??0);
            if($id>0)$overrides[$id]=max(1,(int)($result[$key]['quantity']??1));
        }
        if($items)$result['analytics']=['event'=>'add_to_cart','params'=>(new GoogleAnalyticsService())->cartMutationPayload($items,$overrides)];
        return $result;
    }

    private function matchingCartItems(array $totals, ?int $itemId = null, ?int $productId = null, ?string $group = null): array
    {
        $items=array_values((array)($totals['items']??[]));
        if($group===null&&$itemId!==null){
            foreach($items as $item){
                if((int)($item['id']??0)!==$itemId)continue;
                $custom=json_decode((string)($item['customization_json']??''),true)?:[];
                if(($custom['type']??'')==='gift_box'&&!empty($custom['group']))$group=(string)$custom['group'];
                break;
            }
        }
        return array_values(array_filter($items,static function(array $item)use($itemId,$productId,$group):bool{
            $custom=json_decode((string)($item['customization_json']??''),true)?:[];
            if($group!==null)return (string)($custom['group']??'')===$group;
            if($itemId!==null)return (int)($item['id']??0)===$itemId;
            if($productId!==null)return (int)($item['product_id']??0)===$productId&&($custom['type']??'')!=='gift_box';
            return false;
        }));
    }

    private function handle(callable $callback): never
    {
        try { Response::json($callback()); }
        catch (HttpException $exception) { Response::json(['message'=>$exception->getMessage()],$exception->status()); }
        catch (Throwable $exception) { error_log($exception->__toString()); Response::json(['message'=>'Operațiunea nu este disponibilă momentan.'],500); }
    }
}
