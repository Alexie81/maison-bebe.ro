<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Services\StripeService;
use Throwable;

final class StripeWebhookController extends Controller
{
    public function handle(Request $request): never
    {
        $payload = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        try {
            $result = (new StripeService())->handleWebhook($payload, $signature);
            Response::json(['received' => true] + $result);
        } catch (Throwable $exception) {
            error_log('Stripe webhook failed: ' . $exception->getMessage());
            Response::json(['received' => false, 'error' => 'stripe_webhook_failed'], 400);
        }
    }
}
