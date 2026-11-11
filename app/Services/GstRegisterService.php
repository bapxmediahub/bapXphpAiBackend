<?php
namespace App\Services;

final class GstRegisterService {
    public const HEADERS = [
        'Invoice', 'Invoice date', 'Order', 'Supplier GSTIN', 'Recipient GSTIN', 'Recipient classification',
        'Place of supply', 'Supply type', 'Line', 'Item', 'HSN', 'Quantity', 'GST rate (%)',
        'Taxable (INR)', 'CGST (INR)', 'SGST (INR)', 'IGST (INR)', 'Line total (INR)', 'Review',
    ];

    /** Select the same issued invoices for the report, exports and Enterprise tool. */
    public static function ordersForPeriod(array $orders, string $from, string $to): array {
        return array_values(array_filter($orders, static function (array $order) use ($from, $to): bool {
            $date = substr((string)($order['invoice_date'] ?? ''), 0, 10);
            return !empty($order['invoice_number']) && $date >= $from && $date <= $to;
        }));
    }

    public static function totals(array $orders): array {
        $totals = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
        foreach ($orders as $order) {
            $totals['taxable'] += (float)($order['taxable_value'] ?? 0);
            $totals['cgst'] += (float)($order['cgst_total'] ?? 0);
            $totals['sgst'] += (float)($order['sgst_total'] ?? 0);
            $totals['igst'] += (float)($order['igst_total'] ?? 0);
            $totals['gross'] += (float)($order['total'] ?? 0);
        }
        $totals['tax'] = $totals['cgst'] + $totals['sgst'] + $totals['igst'];
        return array_map(static fn(float $amount): float => round($amount, 2), $totals);
    }

    public static function reviewReasons(array $orders, array $settings): array {
        $reasons = [];
        foreach ($orders as $order) {
            $reason = TaxService::invoiceReviewReason($order, $settings);
            if ($reason !== null) $reasons[(string)($order['id'] ?? $order['invoice_number'])] = $reason;
        }
        return $reasons;
    }

    /** Aggregate all review notes that the line export shows, once per invoice. */
    public static function reviewCount(array $orders, array $reviewReasons): int {
        $count = 0;
        foreach ($orders as $order) {
            foreach (self::lines([$order], $reviewReasons) as $line) {
                if ($line[array_key_last($line)] === '') continue;
                $count++;
                break;
            }
        }
        return $count;
    }

    public static function text(string $value): string {
        // Keep spreadsheet programs from interpreting stored text as a formula.
        return preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $value) ? "'" . $value : $value;
    }

    public static function lines(array $orders, array $reviewReasons): \Generator {
        foreach ($orders as $order) {
            $invoice = (string)$order['invoice_number'];
            $id = (string)($order['id'] ?? $invoice);
            $supplier = strtoupper(trim((string)($order['supplier']['gstin'] ?? '')));
            $recipient = strtoupper(trim((string)($order['customer_gstin'] ?? '')));
            $gstinPattern = '/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D';
            $classification = $recipient === '' ? 'B2C' : (preg_match($gstinPattern, $recipient) ? 'B2B' : 'Review');
            $issues = array_filter([
                $reviewReasons[$id] ?? '',
                preg_match($gstinPattern, $supplier) ? '' : 'Missing or invalid supplier GSTIN',
                $classification === 'Review' ? 'Invalid recipient GSTIN' : '',
                empty($order['verified_refunds']) ? '' : 'Verified refund recorded; review credit note separately',
            ]);
            $prefix = array_map(self::text(...), [
                $invoice, substr((string)($order['invoice_date'] ?? ''), 0, 10), $id, $supplier, $recipient,
                $classification, (string)($order['place_of_supply'] ?? ''), (string)($order['supply_type'] ?? ''),
            ]);
            $lines = array_values(array_filter((array)($order['tax_lines'] ?? []), 'is_array'));
            if (!$lines) {
                yield [...$prefix, ...array_fill(0, 10, ''), implode('; ', [...$issues, 'No issued tax lines'])];
            }
            foreach ($lines as $index => $line) {
                $hsn = trim((string)($line['hsn_code'] ?? ''));
                $lineIssues = $issues;
                if (!preg_match('/^\d{4}(?:\d{2}(?:\d{2})?)?$/D', $hsn)) $lineIssues[] = 'Missing or invalid HSN format';
                $numbers = [];
                foreach (['qty','gst_rate','taxable_value','cgst','sgst','igst','gross_value'] as $key) {
                    $value = $line[$key] ?? null;
                    if (!is_numeric($value) || !is_finite((float)$value)) {
                        $lineIssues[] = 'Missing or invalid ' . str_replace('_', ' ', $key);
                        $numbers[] = '';
                    } else {
                        $numbers[] = $key === 'qty' ? (int)$value : number_format((float)$value, 2, '.', '');
                    }
                }
                yield [...$prefix, $index + 1, self::text((string)($line['name'] ?? '')), self::text($hsn),
                    ...$numbers, implode('; ', $lineIssues)];
            }
        }
    }
}
