<?php
$money = static fn($amount): string => '₹' . number_format((float)$amount, 2);
$query = http_build_query(['report'=>$reportType, 'from'=>$from, 'to'=>$to, 'group'=>$group, 'status'=>$status]);
?>
<div class="report-workspace">
    <div class="report-head">
        <div><h1>Commerce Reports</h1><p>Sales, product, customer, coupon, stock, and GST views from recorded store data.</p></div>
    </div>

    <form method="get" class="report-filters">
            <label>Report type
                <select name="report">
                    <?php foreach ($reportTypes as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $reportType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
            <label>Order status
                <select name="status">
                    <option value="">All paid statuses</option>
                    <?php foreach ($allowedStatuses as $value): ?>
                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $value))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Group sales by
                <select name="group">
                    <?php foreach ($groups as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $group === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary">Apply report</button>
    </form>

    <?php if ($reportType === 'sales'): ?>
        <?php
        $salesTotals = [
            'orders'=>array_sum(array_column($reportRows, 'orders')),
            'units'=>array_sum(array_column($reportRows, 'units')),
            'gross'=>array_sum(array_column($reportRows, 'gross')),
            'refunded'=>array_sum(array_column($reportRows, 'refunded')),
            'net'=>array_sum(array_column($reportRows, 'net')),
        ];
        $averageOrder = $salesTotals['orders'] > 0 ? $salesTotals['net'] / $salesTotals['orders'] : 0;
        $chartValues = array_map(static fn(array $row): float => (float)$row['net'], $reportRows);
        $chartRefunds = array_map(static fn(array $row): float => (float)$row['refunded'], $reportRows);
        $chartMax = max(1, ...$chartValues, ...$chartRefunds);
        $chartCount = max(1, count($reportRows) - 1);
        $netPoints = [];
        $refundPoints = ['40,230'];
        foreach ($reportRows as $index => $row) {
            $x = 40 + (920 * $index / $chartCount);
            $netPoints[] = round($x, 1) . ',' . round(220 - (180 * (float)$row['net'] / $chartMax), 1);
            $refundPoints[] = round($x, 1) . ',' . round(230 - (45 * (float)$row['refunded'] / $chartMax), 1);
        }
        $refundPoints[] = '960,230';
        ?>
        <section class="report-evidence" aria-labelledby="revenue-chart-title">
            <div class="report-evidence__head">
                <div><h2 id="revenue-chart-title">Is net revenue healthy?</h2><p>Paid order revenue minus verified refunds for the selected period.</p></div>
                <div class="report-legend" aria-label="Chart legend"><span><i></i>Net revenue</span><span><i class="refund"></i>Verified refunds</span></div>
            </div>
            <?php if ($reportRows): ?>
            <svg class="report-chart" role="img" aria-labelledby="revenue-chart-title revenue-chart-desc" viewBox="0 0 1000 260" preserveAspectRatio="none">
                <desc id="revenue-chart-desc"><?= e((string)count($reportRows)) ?> periods from <?= e($from) ?> through <?= e($to) ?>. Net revenue is <?= e($money($salesTotals['net'])) ?> and verified refunds are <?= e($money($salesTotals['refunded'])) ?>.</desc>
                <line class="grid" x1="40" y1="40" x2="960" y2="40"/><line class="grid" x1="40" y1="100" x2="960" y2="100"/><line class="grid" x1="40" y1="160" x2="960" y2="160"/><line class="grid" x1="40" y1="220" x2="960" y2="220"/>
                <polygon class="refund-area" points="<?= e(implode(' ', $refundPoints)) ?>"/>
                <polyline class="net" points="<?= e(implode(' ', $netPoints)) ?>"/>
                <text x="40" y="252"><?= e((string)($reportRows[0]['period'] ?? $from)) ?></text><text x="960" y="252" text-anchor="end"><?= e((string)($reportRows[count($reportRows)-1]['period'] ?? $to)) ?></text>
            </svg>
            <?php else: ?><div class="report-empty">No paid orders in this period.</div><?php endif; ?>
            <div class="report-kpis">
                <div class="report-kpi report-kpi--primary"><span>Net revenue</span><strong><?= e($money($salesTotals['net'])) ?></strong></div>
                <div class="report-kpi"><span>Paid orders</span><strong><?= e((string)$salesTotals['orders']) ?></strong></div>
                <div class="report-kpi"><span>Average order value</span><strong><?= e($money($averageOrder)) ?></strong></div>
                <div class="report-kpi"><span>Verified refunds</span><strong><?= e($money($salesTotals['refunded'])) ?></strong></div>
                <div class="report-kpi"><span>Units sold</span><strong><?= e((string)$salesTotals['units']) ?></strong></div>
            </div>
        </section>
        <div class="report-grid">
            <section class="report-panel"><h2>Top products by net revenue</h2><p>Products contributing most in the selected period.</p>
                <div class="table-wrap"><table><thead><tr><th>Product</th><th>Orders</th><th>Units</th><th>Net revenue</th></tr></thead><tbody>
                <?php foreach (array_slice($productRows, 0, 6) as $row): ?><tr><td><?= e($row['product']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td><td><strong><?= e($money($row['net'])) ?></strong></td></tr><?php endforeach; ?>
                <?php if (!$productRows): ?><tr><td colspan="4">No purchased products in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
            <section class="report-panel"><h2>Order status & refunds</h2><p>Operational outcomes for paid orders.</p>
                <div class="table-wrap"><table><thead><tr><th>Status</th><th>Orders</th></tr></thead><tbody>
                <?php foreach ($orderStatusCounts as $label => $count): ?><tr><td><?= e(ucfirst(str_replace('_',' ',$label))) ?></td><td><?= e((string)$count) ?></td></tr><?php endforeach; ?>
                <?php if (!$orderStatusCounts): ?><tr><td colspan="2">No paid orders in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
                <p><strong><?= e($money($salesTotals['refunded'])) ?></strong> in verified refunds is already deducted from net revenue.</p>
            </section>
        </div>
        <section class="report-panel"><h2>Revenue ledger</h2><p>Accessible tabular fallback for every charted period.</p><div class="table-wrap"><table>
            <thead><tr><th>Period</th><th>Paid orders</th><th>Units</th><th>Gross</th><th>Refunded</th><th>Net sales</th></tr></thead>
            <tbody>
            <?php foreach ($reportRows as $row): ?><tr>
                <td><?= e($row['period']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td>
                <td><?= e($money($row['gross'])) ?></td><td><?= e($money($row['refunded'])) ?></td><td><strong><?= e($money($row['net'])) ?></strong></td>
            </tr><?php endforeach; ?>
            <?php if (!$reportRows): ?><tr><td colspan="6">No paid orders in this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div></section>
    <?php elseif ($reportType === 'products'): ?>
        <section class="report-panel">
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Orders</th><th>Units</th><th>Gross product sales</th><th>Net after order refunds</th></tr></thead><tbody>
        <?php foreach ($reportRows as $row): ?><tr><td><?= e($row['product']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td><td><?= e($money($row['gross'])) ?></td><td><?= e($money($row['net'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$reportRows): ?><tr><td colspan="5">No purchased products in this period.</td></tr><?php endif; ?>
        </tbody></table></div></section>
    <?php elseif ($reportType === 'customers'): ?>
        <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Paid orders</th><th>Gross</th><th>Refunded</th><th>Net sales</th></tr></thead><tbody>
        <?php foreach ($reportRows as $row): ?><tr><td><?= e($row['customer']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e($money($row['gross'])) ?></td><td><?= e($money($row['refunded'])) ?></td><td><?= e($money($row['net'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$reportRows): ?><tr><td colspan="5">No customer orders in this period.</td></tr><?php endif; ?>
        </tbody></table></div>
    <?php elseif ($reportType === 'coupons'): ?>
        <div class="table-wrap"><table><thead><tr><th>Coupon</th><th>Uses</th><th>Discount given</th><th>Net sales</th></tr></thead><tbody>
        <?php foreach ($reportRows as $row): ?><tr><td><?= e($row['coupon']) ?></td><td><?= e((string)$row['uses']) ?></td><td><?= e($money($row['discount'])) ?></td><td><?= e($money($row['net'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$reportRows): ?><tr><td colspan="4">No coupon-backed paid orders in this period.</td></tr><?php endif; ?>
        </tbody></table></div>
    <?php elseif ($reportType === 'stock'): ?>
        <p>Stock is a current catalogue snapshot; date and order-status filters do not apply.</p>
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Stock status</th><th>Price</th><th>Offer price</th></tr></thead><tbody>
        <?php foreach ($reportRows as $row): ?><tr><td><?= e($row['product']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $row['status']))) ?></td><td><?= e($money($row['price'])) ?></td><td><?= $row['offer_price'] === null ? '—' : e($money($row['offer_price'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$reportRows): ?><tr><td colspan="4">No products found.</td></tr><?php endif; ?>
        </tbody></table></div>
    <?php else: ?>
        <?php if(!empty($invoiceReviewReasons)): ?><div class="flash flash--error" role="alert"><strong><?= e((string)count($invoiceReviewReasons)) ?> issued invoice(s) require accountant review.</strong> Their stored tax fields have not been rewritten. Review the delivery state and issue a compliant correction outside this screen if advised.</div><?php endif; ?>
        <div class="admin-detail-grid">
            <div><strong>Taxable sales</strong><p><?= e($money($totals['taxable'])) ?></p></div><div><strong>CGST</strong><p><?= e($money($totals['cgst'])) ?></p></div><div><strong>SGST</strong><p><?= e($money($totals['sgst'])) ?></p></div><div><strong>IGST</strong><p><?= e($money($totals['igst'])) ?></p></div><div><strong>Total GST</strong><p><?= e($money($totals['tax'])) ?></p></div><div><strong>Gross product sales</strong><p><?= e($money($totals['gross'])) ?></p></div>
        </div>
        <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Place of supply</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Total</th><th>Review</th></tr></thead><tbody><?php foreach($orders as $order): ?><?php $reviewKey=(string)($order['id']??$order['invoice_number']??''); ?><tr><td><a href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice"><?= e($order['invoice_number']??'') ?></a> <a download href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice?download=1">Download</a></td><td><?= e(substr((string)($order['invoice_date']??''),0,10)) ?></td><td><?= e($order['customer_email']??'') ?></td><td><?= e($order['place_of_supply']??'') ?></td><td><?= e($money($order['taxable_value']??0)) ?></td><td><?= e($money($order['cgst_total']??0)) ?></td><td><?= e($money($order['sgst_total']??0)) ?></td><td><?= e($money($order['igst_total']??0)) ?></td><td><?= e($money($order['total']??0)) ?></td><td><?= e($invoiceReviewReasons[$reviewKey]??'Clear') ?></td></tr><?php endforeach; ?><?php if(!$orders): ?><tr><td colspan="10">No paid product invoices in this period.</td></tr><?php endif; ?></tbody></table></div>
    <?php endif; ?>
    <?php if ($reportType === 'tax'): ?><a class="btn btn-outline" href="?<?= e($query) ?>&format=csv">Export GST CSV</a><?php endif; ?>
</div>
