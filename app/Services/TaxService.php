<?php
namespace App\Services;

final class TaxService {
    public function snapshot(array $items, float $discount, string $shippingState, array $settings): array {
        $supplierState = trim((string)($settings['gst_state'] ?? 'Tamil Nadu'));
        $interstate = $this->stateKey($shippingState) !== $this->stateKey($supplierState);
        $grossBeforeDiscount = array_sum(array_map(fn($item) => (float)($item['line_total'] ?? 0), $items));
        $discount = max(0, min($discount, $grossBeforeDiscount));
        $lines = [];
        $taxableTotal = $taxTotal = $cgstTotal = $sgstTotal = $igstTotal = 0.0;

        foreach ($items as $item) {
            $gross = (float)($item['line_total'] ?? 0);
            $lineDiscount = $grossBeforeDiscount > 0 ? $discount * ($gross / $grossBeforeDiscount) : 0;
            $lineGross = max(0, $gross - $lineDiscount);
            $rate = max(0, min(100, (float)($item['gst_rate'] ?? 0)));
            $taxable = $rate > 0 ? $lineGross / (1 + ($rate / 100)) : $lineGross;
            $tax = $lineGross - $taxable;
            $cgst = $interstate ? 0 : $tax / 2;
            $sgst = $interstate ? 0 : $tax / 2;
            $igst = $interstate ? $tax : 0;
            $lines[] = [
                'slug' => (string)($item['slug'] ?? ''),
                'name' => (string)($item['name'] ?? 'Product'),
                'hsn_code' => (string)($item['hsn_code'] ?? ''),
                'qty' => (int)($item['qty'] ?? 1),
                'unit_price' => $this->money((float)($item['unit_price'] ?? 0)),
                'gross_value' => $this->money($lineGross),
                'taxable_value' => $this->money($taxable),
                'gst_rate' => $rate,
                'cgst' => $this->money($cgst),
                'sgst' => $this->money($sgst),
                'igst' => $this->money($igst),
                'tax_total' => $this->money($tax),
            ];
            $taxableTotal += $taxable;
            $taxTotal += $tax;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
        }

        return [
            'supply_type' => $interstate ? 'interstate' : 'intrastate',
            'place_of_supply' => trim($shippingState) ?: $supplierState,
            'taxable_value' => $this->money($taxableTotal),
            'cgst_total' => $this->money($cgstTotal),
            'sgst_total' => $this->money($sgstTotal),
            'igst_total' => $this->money($igstTotal),
            'tax_total' => $this->money($taxTotal),
            'total' => $this->money($grossBeforeDiscount - $discount),
            'tax_inclusive' => true,
            'tax_lines' => $lines,
            'supplier' => [
                'legal_name' => (string)($settings['gst_legal_name'] ?? ''),
                'trade_name' => (string)($settings['gst_trade_name'] ?? ''),
                'gstin' => (string)($settings['gstin'] ?? ''),
                'address' => (string)($settings['gst_address'] ?? ''),
                'state' => $supplierState,
                'state_code' => (string)($settings['gst_state_code'] ?? ''),
            ],
        ];
    }

    public function nextInvoice(array $orders, ?\DateTimeImmutable $at = null): array {
        $at ??= new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $year = (int)$at->format('Y');
        $month = (int)$at->format('n');
        $start = $month >= 4 ? $year : $year - 1;
        $financialYear = substr((string)$start, -2) . substr((string)($start + 1), -2);
        $max = 0;
        foreach ($orders as $order) {
            if (($order['invoice_financial_year'] ?? '') === $financialYear) {
                $max = max($max, (int)($order['invoice_sequence'] ?? 0));
            }
        }
        $sequence = $max + 1;
        return [
            'invoice_sequence' => $sequence,
            'invoice_financial_year' => $financialYear,
            'invoice_number' => sprintf('SPS/%s/%05d', $financialYear, $sequence),
            'invoice_date' => $at->format('c'),
        ];
    }

    private function stateKey(string $state): string { return strtolower(preg_replace('/[^a-z]/i', '', $state)); }
    private function money(float $value): float { return round($value + 0.0000001, 2); }
}
