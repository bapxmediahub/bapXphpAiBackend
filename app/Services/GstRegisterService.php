<?php
namespace App\Services;

final class GstRegisterService {
    private const GSTIN_PATTERN = '/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D';
    public const HEADERS = [
        'Invoice', 'Invoice date', 'Order', 'Supplier GSTIN', 'Recipient GSTIN', 'Recipient classification',
        'Place of supply', 'Supply type', 'Line', 'Item', 'HSN', 'Quantity', 'GST rate (%)',
        'Taxable (INR)', 'CGST (INR)', 'SGST (INR)', 'IGST (INR)', 'Line total (INR)', 'Review',
        'UQC', 'Quantity in UQC', 'Supplier state code', 'Place of supply code',
    ];

    public const SEQUENCE_HEADERS = [
        'Supplier GSTIN', 'Stored financial year', 'Stored sequence', 'Invoice', 'Issued date', 'Order',
        'Order status', 'Gateway refund recorded', 'Unobserved native SPS numbers in selection', 'Review',
    ];

    /** Compare the store's issued SPS invoice series within the already-selected invoice dates. */
    public static function sequenceWorksheet(array $orders): \Generator {
        $rows = $numberCounts = $sequenceCounts = [];
        foreach ($orders as $order) {
            $number = (string)($order['invoice_number'] ?? '');
            if (trim($number) === '') continue;
            $supplier = strtoupper(trim((string)($order['supplier']['gstin'] ?? '')));
            $year = (string)($order['invoice_financial_year'] ?? '');
            $rawSequence = (string)($order['invoice_sequence'] ?? '');
            $sequence = ctype_digit($rawSequence) ? filter_var(ltrim($rawSequence, '0'), FILTER_VALIDATE_INT) : false;
            $issues = [];
            $knownSupplier = preg_match(self::GSTIN_PATTERN, $supplier) === 1;
            if (!$knownSupplier) $issues[] = 'Missing or invalid supplier GSTIN';
            $validYear = preg_match('/^\d{4}$/D', $year) && (int)substr($year, 2) === ((int)substr($year, 0, 2) + 1) % 100;
            if (!$validYear) $issues[] = 'Missing or invalid stored financial year';
            if (!$sequence) $issues[] = 'Missing or invalid stored sequence';
            $native = $validYear && $sequence && $number === sprintf('SPS/%s/%05d', $year, $sequence);
            if ($sequence && $validYear && !$native) $issues[] = 'Invoice number differs from native SPS series';
            $date = substr((string)($order['invoice_date'] ?? ''), 0, 10);
            $issued = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$issued || $issued->format('Y-m-d') !== $date) {
                $issues[] = 'Invalid issue date';
                $native = false;
            } elseif ($validYear && TaxService::invoiceFinancialYear($issued) !== $year) {
                $issues[] = 'Stored financial year differs from issue date';
                $native = false;
            }
            $series = $supplier . '|' . $year;
            if ($knownSupplier) {
                $numberCounts[$supplier][$number] = ($numberCounts[$supplier][$number] ?? 0) + 1;
                if ($validYear && $sequence) $sequenceCounts[$series][$sequence] = ($sequenceCounts[$series][$sequence] ?? 0) + 1;
            }
            $id = (string)($order['id'] ?? '');
            $status = (string)($order['status'] ?? '');
            $refund = !empty($order['verified_refunds']);
            $rows[] = compact('supplier','year','rawSequence','sequence','series','number','id','date','status','refund','issues','native','knownSupplier');
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['series'], $b['series'])
            ?: ($a['sequence'] <=> $b['sequence']) ?: strcmp($a['number'], $b['number'])
            ?: strcmp($a['date'], $b['date']) ?: strcmp($a['id'], $b['id']));
        $previous = [];
        foreach ($rows as $row) {
            $issues = $row['issues'];
            $series = $row['series'];
            $sequence = $row['sequence'];
            if (($numberCounts[$row['supplier']][$row['number']] ?? 0) > 1) $issues[] = 'Duplicate invoice number';
            if (($sequenceCounts[$series][$sequence] ?? 0) > 1) $issues[] = 'Duplicate stored sequence';
            $gap = '';
            if ($row['knownSupplier'] && $row['native']) {
                if (isset($previous[$series]) && $sequence - $previous[$series] > 1) {
                    $first = $previous[$series] + 1;
                    $last = $sequence - 1;
                    $gap = $first === $last ? (string)$first : "$first to $last";
                    $issues[] = 'Check intervening numbers against the document register';
                }
                $previous[$series] = $sequence;
            }
            if (strtolower($row['status']) === 'cancelled') $issues[] = 'Check invoice disposition against cancellation records';
            if ($row['refund']) $issues[] = 'Check refund against separately issued tax documents';
            yield array_map(self::text(...), [$row['supplier'], $row['year'], $row['rawSequence'], $row['number'],
                $row['date'], $row['id'], $row['status'], $row['refund'] ? 'Yes' : 'No', $gap, implode('; ', $issues)]);
        }
    }

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
            $issues = array_merge($reason === null ? [] : [$reason], TaxService::issuedStateCodes($order)['issues']);
            if ($issues) $reasons[(string)($order['id'] ?? $order['invoice_number'])] = implode('; ', $issues);
        }
        return $reasons;
    }

    /** Aggregate all review notes that the line export shows, once per invoice. */
    public static function reviewCount(array $orders, array $reviewReasons): int {
        $count = 0;
        $reviewColumn = array_search('Review', self::HEADERS, true);
        foreach ($orders as $order) {
            foreach (self::lines([$order], $reviewReasons) as $line) {
                if ($line[$reviewColumn] === '') continue;
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

    /** Reconcile quantities and monetary amounts by issued HSN, recipient class, unit and rate. */
    public static function hsnSummary(array $orders, array $reviewReasons): array {
        $groups = [];
        $excluded = $missingUnits = 0;
        foreach (self::lines($orders, $reviewReasons) as $values) {
            $line = array_combine(self::HEADERS, $values);
            $amounts = [$line['Taxable (INR)'], $line['CGST (INR)'], $line['SGST (INR)'], $line['IGST (INR)']];
            if (!preg_match('/^\d{4}(?:\d{2}(?:\d{2})?)?$/D', $line['HSN'])
                || $line['GST rate (%)'] === '' || in_array('', $amounts, true)) {
                $excluded++;
                continue;
            }
            $quantity = TaxService::quantityMillis($line['Quantity in UQC']);
            $uqc = $quantity === null ? '' : $line['UQC'];
            if ($uqc === '') $missingUnits++;
            $key = implode('|', [$line['HSN'], $line['Recipient classification'], $line['GST rate (%)'], $uqc]);
            $groups[$key] ??= ['hsn'=>$line['HSN'], 'recipient'=>$line['Recipient classification'],
                'rate'=>$line['GST rate (%)'], 'uqc'=>$uqc, 'quantityMillis'=>0,
                'lines'=>0, 'review'=>0, 'taxablePaise'=>0, 'taxPaise'=>0];
            if ($quantity !== null) $groups[$key]['quantityMillis'] += $quantity;
            $groups[$key]['lines']++;
            $groups[$key]['review'] += (int)($line['Review'] !== '');
            $paise = array_map(static fn($amount): int => (int)round((float)$amount * 100), $amounts);
            $groups[$key]['taxablePaise'] += $paise[0];
            $groups[$key]['taxPaise'] += $paise[1] + $paise[2] + $paise[3];
        }
        ksort($groups, SORT_STRING);
        return ['groups'=>array_values($groups), 'excluded'=>$excluded, 'missingUnits'=>$missingUnits];
    }

    public static function lines(array $orders, array $reviewReasons): \Generator {
        foreach ($orders as $order) {
            $invoice = (string)$order['invoice_number'];
            $id = (string)($order['id'] ?? $invoice);
            $supplier = strtoupper(trim((string)($order['supplier']['gstin'] ?? '')));
            $recipient = strtoupper(trim((string)($order['customer_gstin'] ?? '')));
            $classification = $recipient === '' ? 'B2C' : (preg_match(self::GSTIN_PATTERN, $recipient) ? 'B2B' : 'Review');
            $codes = TaxService::issuedStateCodes($order);
            $issues = array_filter([
                ...explode('; ', $reviewReasons[$id] ?? ''),
                ...$codes['issues'],
                preg_match(self::GSTIN_PATTERN, $supplier) ? '' : 'Missing or invalid supplier GSTIN',
                $classification === 'Review' ? 'Invalid recipient GSTIN' : '',
                empty($order['verified_refunds']) ? '' : 'Verified refund recorded; review credit note separately',
            ]);
            $issues = array_values(array_unique($issues));
            $prefix = array_map(self::text(...), [
                $invoice, substr((string)($order['invoice_date'] ?? ''), 0, 10), $id, $supplier, $recipient,
                $classification, (string)($order['place_of_supply'] ?? ''), (string)($order['supply_type'] ?? ''),
            ]);
            $lines = array_values(array_filter((array)($order['tax_lines'] ?? []), 'is_array'));
            if (!$lines) {
                yield [...$prefix, ...array_fill(0, 10, ''), implode('; ', [...$issues, 'No issued tax lines']), '', '', $codes['supplier'], $codes['place']];
            }
            foreach ($lines as $index => $line) {
                $hsn = trim((string)($line['hsn_code'] ?? ''));
                $lineIssues = $issues;
                if (!preg_match('/^\d{4}(?:\d{2}(?:\d{2})?)?$/D', $hsn)) $lineIssues[] = 'Missing or invalid HSN format';
                $uqc = TaxService::quantityUnit($line['uqc'] ?? null);
                $quantity = TaxService::quantityMillis($line['uqc_quantity'] ?? null);
                if ($uqc === '' || $quantity === null) $lineIssues[] = 'Missing or invalid issued GST unit or quantity';
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
                    ...$numbers, implode('; ', $lineIssues), $uqc,
                    $uqc === '' || $quantity === null ? '' : TaxService::quantityDecimal($quantity), $codes['supplier'], $codes['place']];
            }
        }
    }
}
