<?php
namespace App\Services;

final class GstRegisterService {
    public const HEADERS = [
        'Invoice', 'Invoice date', 'Order', 'Supplier GSTIN', 'Recipient GSTIN', 'Recipient classification',
        'Place of supply', 'Supply type', 'Line', 'Item', 'HSN', 'Quantity', 'GST rate (%)',
        'Taxable (INR)', 'CGST (INR)', 'SGST (INR)', 'IGST (INR)', 'Line total (INR)', 'Review',
    ];

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
