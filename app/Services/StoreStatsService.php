<?php
namespace App\Services;

/** Deterministic commerce reporting shared by admin screens and future dashboard cards. */
final class StoreStatsService
{
    public const REPORT_TYPES = [
        'sales' => 'Sales',
        'products' => 'Products purchased',
        'stock' => 'Product stock',
        'customers' => 'Customer orders',
        'coupons' => 'Coupons',
        'tax' => 'GST tax',
    ];
    public const GROUPS = ['day' => 'Days', 'week' => 'Weeks', 'month' => 'Months'];

    /** @return array{from:string,to:string} */
    public static function dateRange(string $from, string $to, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $fallbackFrom = $today->modify('-29 days')->format('Y-m-d');
        $fallbackTo = $today->format('Y-m-d');
        $from = self::validDate($from) ? $from : $fallbackFrom;
        $to = self::validDate($to) ? $to : $fallbackTo;
        if ($from > $to) [$from, $to] = [$to, $from];
        return compact('from', 'to');
    }

    /** @return array<int,array<string,mixed>> */
    public static function sales(array $orders, string $from, string $to, string $group = 'day', string $status = ''): array
    {
        $group = isset(self::GROUPS[$group]) ? $group : 'day';
        $buckets = [];
        foreach (self::paidOrders($orders, $from, $to, $status) as $order) {
            $date = new \DateTimeImmutable(substr(self::orderDate($order), 0, 10));
            $key = match ($group) {
                'month' => $date->format('Y-m'),
                'week' => $date->modify('monday this week')->format('Y-m-d'),
                default => $date->format('Y-m-d'),
            };
            $buckets[$key] ??= ['period'=>$key, 'orders'=>0, 'units'=>0, 'gross'=>0.0, 'refunded'=>0.0, 'net'=>0.0];
            $buckets[$key]['orders']++;
            $buckets[$key]['units'] += self::units($order);
            $buckets[$key]['gross'] += (float)($order['total'] ?? 0);
            $buckets[$key]['refunded'] += OrderService::refundedPaise($order) / 100;
            $buckets[$key]['net'] += self::net($order);
        }
        ksort($buckets);
        return array_values(array_map(self::roundMoney(...), $buckets));
    }

    /** @return array<int,array<string,mixed>> */
    public static function productsPurchased(array $orders, string $from, string $to, string $status = ''): array
    {
        $rows = [];
        foreach (self::paidOrders($orders, $from, $to, $status) as $order) {
            $grossOrder = max(0.0, (float)($order['total'] ?? 0));
            $ratio = $grossOrder > 0 ? self::net($order) / $grossOrder : 0.0;
            foreach (($order['items'] ?? []) as $item) {
                $name = trim((string)($item['name'] ?? $item['slug'] ?? ''));
                if ($name === '') continue;
                $rows[$name] ??= ['product'=>$name, 'orders'=>0, 'units'=>0, 'gross'=>0.0, 'net'=>0.0];
                $rows[$name]['orders']++;
                $rows[$name]['units'] += max(0, (int)($item['qty'] ?? 1));
                $line = max(0.0, (float)($item['line_total'] ?? 0));
                $rows[$name]['gross'] += $line;
                $rows[$name]['net'] += $line * $ratio;
            }
        }
        usort($rows, static fn(array $a, array $b): int => ($b['units'] <=> $a['units']) ?: strcmp($a['product'], $b['product']));
        return array_values(array_map(self::roundMoney(...), $rows));
    }

    /** @return array<int,array<string,mixed>> */
    public static function customers(array $orders, string $from, string $to, string $status = ''): array
    {
        $rows = [];
        foreach (self::paidOrders($orders, $from, $to, $status) as $order) {
            $email = trim((string)($order['customer_email'] ?? 'Unknown customer')) ?: 'Unknown customer';
            $rows[$email] ??= ['customer'=>$email, 'orders'=>0, 'gross'=>0.0, 'refunded'=>0.0, 'net'=>0.0];
            $rows[$email]['orders']++;
            $rows[$email]['gross'] += (float)($order['total'] ?? 0);
            $rows[$email]['refunded'] += OrderService::refundedPaise($order) / 100;
            $rows[$email]['net'] += self::net($order);
        }
        usort($rows, static fn(array $a, array $b): int => ($b['net'] <=> $a['net']) ?: strcmp($a['customer'], $b['customer']));
        return array_values(array_map(self::roundMoney(...), $rows));
    }

    /** @return array<int,array<string,mixed>> */
    public static function coupons(array $orders, string $from, string $to, string $status = ''): array
    {
        $rows = [];
        foreach (self::paidOrders($orders, $from, $to, $status) as $order) {
            $code = strtoupper(trim((string)($order['coupon_code'] ?? '')));
            if ($code === '') continue;
            $rows[$code] ??= ['coupon'=>$code, 'uses'=>0, 'discount'=>0.0, 'net'=>0.0];
            $rows[$code]['uses']++;
            $rows[$code]['discount'] += max(0.0, (float)($order['discount'] ?? 0));
            $rows[$code]['net'] += self::net($order);
        }
        usort($rows, static fn(array $a, array $b): int => ($b['uses'] <=> $a['uses']) ?: strcmp($a['coupon'], $b['coupon']));
        return array_values(array_map(self::roundMoney(...), $rows));
    }

    /** @return array<int,array<string,mixed>> */
    public static function stock(array $products): array
    {
        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                'product' => (string)($product['name'] ?? $product['slug'] ?? 'Product'),
                'status' => (string)($product['stock_status'] ?? 'Not recorded'),
                'price' => round((float)($product['price'] ?? 0), 2),
                'offer_price' => is_numeric($product['offer_price'] ?? null) ? round((float)$product['offer_price'], 2) : null,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['product'], $b['product']));
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private static function paidOrders(array $orders, string $from, string $to, string $status): array
    {
        return array_values(array_filter($orders, static function (array $order) use ($from, $to, $status): bool {
            $date = substr(self::orderDate($order), 0, 10);
            if (!self::validDate($date) || $date < $from || $date > $to) return false;
            $orderStatus = strtolower((string)($order['status'] ?? ''));
            if ($status !== '' && $orderStatus !== $status) return false;
            if (in_array($orderStatus, ['cancelled', 'failed'], true)) return false;
            return trim((string)($order['payment_id'] ?? '')) !== '';
        }));
    }

    private static function orderDate(array $order): string
    {
        return trim((string)($order['created_at'] ?? $order['invoice_date'] ?? ''));
    }

    private static function net(array $order): float
    {
        return max(0.0, (float)($order['total'] ?? 0) - OrderService::refundedPaise($order) / 100);
    }

    private static function units(array $order): int
    {
        return array_sum(array_map(static fn(array $item): int => max(0, (int)($item['qty'] ?? 1)), $order['items'] ?? []));
    }

    private static function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function roundMoney(array $row): array
    {
        foreach (['gross', 'refunded', 'net', 'discount'] as $key) {
            if (array_key_exists($key, $row)) $row[$key] = round((float)$row[$key], 2);
        }
        return $row;
    }
}
