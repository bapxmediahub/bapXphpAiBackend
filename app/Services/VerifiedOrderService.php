<?php
declare(strict_types=1);
namespace App\Services;

/** Persist an already gateway-verified payment; never changes a browser session. */
final class VerifiedOrderService
{
    private const TAX_SETTINGS = ['gst_state','default_gst_rate','default_hsn_code',
        'gst_legal_name','gst_trade_name','gstin','gst_address','gst_state_code'];

    public function __construct(private ?DatabaseService $store = null) {}

    /** Callers verify the payment before entering this idempotent order boundary. */
    public function persist(array $pendingOrder, string $paymentId, array $gatewayFields = [], ?array $testPorts = null): string
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') throw new \InvalidArgumentException('Verified payment id is required.');
        $db = $testPorts === null ? ($this->store ??= new DatabaseService()) : null;
        $existingOrders = $testPorts === null ? $db->read('orders') : ($testPorts['readOrders'])();
        // Provider references cannot overwrite verified totals, delivery, status or tax fields.
        $gatewayFields = array_intersect_key($gatewayFields,
            array_flip(['razorpay_order_id','razorpay_payment_link_id','stripe_session_id']));
        foreach ($existingOrders as $existing) {
            if (($existing['payment_id'] ?? '') === $paymentId) return (string)$existing['id'];
            if (!empty($gatewayFields['stripe_session_id'])
                && ($existing['stripe_session_id'] ?? '') === $gatewayFields['stripe_session_id']) return (string)$existing['id'];
        }

        $orderItems = $pendingOrder['items'] ?? [];
        $products = $testPorts === null ? (new ProductService($db))->bySlug() : ($testPorts['products'])();
        $hasFrozenSettings = array_key_exists('tax_settings', $pendingOrder);
        $frozenSettings = $hasFrozenSettings ? self::frozenTaxSettings($pendingOrder['tax_settings']) : null;
        $settings = $hasFrozenSettings ? ($frozenSettings ?? [])
            : ($testPorts === null ? (new SettingsService($db))->public() : ($testPorts['settings'])());
        $unavailable = [];
        $missingProduct = false;
        $allFrozenItems = $orderItems !== [];
        $itemsWithRates = [];
        foreach ($orderItems as $item) {
            $product = $products[$item['slug'] ?? ''] ?? null;
            if ($product === null) $missingProduct = true;
            if ($product === null || !empty($product['is_hidden'])
                || !in_array($product['stock_status'] ?? '', ['in_stock','active'], true)) {
                $unavailable[] = (string)($item['name'] ?? 'A product');
            }
            $frozen = self::hasFrozenTaxItem($item);
            $allFrozenItems = $allFrozenItems && $frozen;
            if (!$frozen) {
                $item['gst_rate'] = TaxService::rateFor($product ?? [], $settings);
                $item['hsn_code'] = TaxService::hsnFor($product ?? [], $settings);
                $item['unit_price'] = (float)($item['line_total'] ?? 0) / max(1, (int)($item['qty'] ?? 1));
            }
            $itemsWithRates[] = $item;
        }
        // A complete confirmed quote preserves its tax evidence even if a product
        // disappears later. Fulfilment still requires owner review in that case.
        $taxInputsComplete = $hasFrozenSettings
            ? ($frozenSettings !== null && $allFrozenItems)
            : !$missingProduct;
        $tax = $taxInputsComplete ? (new TaxService())->snapshotForVerifiedPayment(
            $itemsWithRates, (float)($pendingOrder['discount'] ?? 0),
            (string)($pendingOrder['shipping_state'] ?? ''), $settings
        ) : null;
        $localId = substr(hash('sha256', 'verified-payment:' . $paymentId), 0, 16);
        $order = array_merge([
            'id'=>$localId,
            'status'=>$unavailable ? 'on_hold' : 'confirmed',
            'fulfillment_review_required'=>$unavailable !== [],
            'fulfillment_review_reason'=>$unavailable ? 'Product availability changed after checkout' : '',
            'total'=>$pendingOrder['total'],
            'payment_id'=>$paymentId,
            'payment_email_status'=>'pending',
            'customer_email'=>$pendingOrder['customer_email'],
            'customer_name'=>$pendingOrder['customer_name'],
            'customer_phone'=>$pendingOrder['customer_phone'],
            'shipping_address'=>$pendingOrder['shipping_address'],
            'shipping_city'=>$pendingOrder['shipping_city'],
            'shipping_pincode'=>$pendingOrder['shipping_pincode'],
            'shipping_state'=>$pendingOrder['shipping_state'] ?? '',
            'items'=>$itemsWithRates,
            'coupon_code'=>$pendingOrder['coupon_code'] ?? '',
            'discount'=>$pendingOrder['discount'] ?? 0,
            'customer_gstin'=>$pendingOrder['customer_gstin'] ?? '',
            'tax_review_required'=>$tax === null,
            'tax_review_reason'=>$tax === null ? (!$taxInputsComplete
                ? 'Product tax details unavailable at payment confirmation'
                : 'Missing or invalid delivery or supplier state at payment confirmation') : '',
            'created_at'=>date('c'),
        ], $gatewayFields);
        if ($tax !== null) {
            unset($tax['total']); // The gateway-verified payable amount is authoritative.
            $order = array_merge($order, $tax);
        }
        if ($testPorts !== null && $tax !== null) {
            $order = array_merge($order, (new TaxService())->nextInvoice($existingOrders));
        }
        if ($testPorts === null) {
            $saved = $db->saveVerifiedOrder($order, $tax !== null);
            $insert = ['inserted'=>$saved['created'], 'record'=>$saved['record']];
        } elseif (isset($testPorts['insertOrder'])) {
            $insert = ($testPorts['insertOrder'])($order);
        } else {
            ($testPorts['saveOrder'])($order);
            $insert = ['inserted'=>true, 'record'=>$order];
        }
        $stored = $insert['record'] ?? $order;
        $localId = (string)($stored['id'] ?? $localId);
        try {
            if ($testPorts === null && !empty($insert['inserted'])) {
                (new MailQueueService($db))->enqueuePaymentConfirmation($stored);
            }
        } catch (\Throwable) { error_log('Payment confirmation mail could not be queued.'); }
        return $localId;
    }

    private static function hasFrozenTaxItem(array $item): bool
    {
        foreach (['gst_rate','unit_price','line_total'] as $key) {
            if ((!is_int($item[$key] ?? null) && !is_float($item[$key] ?? null))
                || !is_finite((float)$item[$key]) || $item[$key] < 0) return false;
        }
        return $item['gst_rate'] <= 100 && is_string($item['hsn_code'] ?? null)
            && strlen($item['hsn_code']) <= 32 && is_int($item['qty'] ?? null) && $item['qty'] > 0
            && abs(round($item['unit_price'] * $item['qty'], 2) - $item['line_total']) < 0.005;
    }

    private static function frozenTaxSettings(mixed $value): ?array
    {
        if (!is_array($value) || array_diff(self::TAX_SETTINGS, array_keys($value)) !== []) return null;
        foreach (self::TAX_SETTINGS as $key) {
            if ($key === 'default_gst_rate') {
                if ((!is_int($value[$key]) && !is_float($value[$key])) || !is_finite((float)$value[$key])
                    || $value[$key] < 0 || $value[$key] > 100) return null;
            } elseif (!is_string($value[$key]) || strlen($value[$key]) > 4096) return null;
        }
        if (TaxService::normaliseState($value['gst_state']) === null) return null;
        return array_intersect_key($value, array_flip(self::TAX_SETTINGS));
    }
}
