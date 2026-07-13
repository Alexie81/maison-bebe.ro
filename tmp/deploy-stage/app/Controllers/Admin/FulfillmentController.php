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
use MaisonBebe\Services\ShippingService;
use Throwable;

final class FulfillmentController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice'), 'error' => Session::flash('admin_error')], 'layouts/admin');
    }

    public function awb(Request $request, string $id): string
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare("SELECT o.*,a.snapshot_json shipping_address FROM orders o LEFT JOIN order_addresses a ON a.order_id=o.id AND a.type='shipping' WHERE o.id=? LIMIT 1");
        $statement->execute([(int) $id]);
        $order = $statement->fetch();
        if (!$order) {
            throw new HttpException(404, 'Comanda nu a fost găsită.');
        }
        $order['shipping_address'] = json_decode((string) $order['shipping_address'], true) ?: [];
        $providers = $pdo->query('SELECT * FROM shipping_providers WHERE is_enabled=1 ORDER BY is_default DESC,name')->fetchAll();
        $shipment = $pdo->prepare('SELECT s.*,p.name provider_name FROM shipments s LEFT JOIN shipping_providers p ON p.id=s.provider_id WHERE s.order_id=? ORDER BY s.id DESC LIMIT 1');
        $shipment->execute([(int) $id]);
        return $this->admin('admin/awb', ['order' => $order, 'providers' => $providers, 'shipment' => $shipment->fetch() ?: null]);
    }

    public function createAwb(Request $request, string $id): never
    {
        try {
            $shipmentId = (new ShippingService())->create((int) $id, (int) $request->input('provider_id', 0), max(1, (int) $request->input('weight_grams', 1000)), max(1, (int) $request->input('parcels', 1)));
            Database::connection()->prepare('INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,\'shipment.created\',\'shipment\',?,?)')->execute([Auth::id(), $shipmentId, $_SERVER['REMOTE_ADDR'] ?? null]);
            Session::flash('admin_notice', 'Expediția a fost creată. Pentru curier API, generarea AWB continuă în coadă.');
        } catch (Throwable $exception) {
            Session::flash('admin_error', $exception->getMessage());
        }
        Response::redirect('/admin/comenzi/' . $id . '/awb');
    }
}
