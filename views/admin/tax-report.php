<?php
$money = static fn($amount): string => '₹' . number_format((float)$amount, 2);
$query = http_build_query(['report'=>$reportType, 'from'=>$from, 'to'=>$to, 'group'=>$group, 'status'=>$status]);
?>
<div class="admin-card">
    <div class="admin-page-head">
        <div><h1>Commerce Reports</h1><p>Sales, product, customer, coupon, stock, and GST views from recorded store data.</p></div>
    </div>

    <form method="get" class="admin-form" style="margin-bottom:var(--space-lg)">
        <div class="admin-form__row">
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
        </div>
        <div style="display:flex; gap:var(--space-sm); flex-wrap:wrap;">
            <button class="btn btn-primary">Apply report</button>
            <?php if ($reportType === 'tax'): ?>
                <a class="btn btn-outline" href="?<?= e($query) ?>&format=csv">Export GST CSV</a>
            <?php endif; ?>
        </div>
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
        ?>
        <div class="admin-detail-grid">
            <div><strong>Paid orders</strong><p><?= e((string)$salesTotals['orders']) ?></p></div>
            <div><strong>Units sold</strong><p><?= e((string)$salesTotals['units']) ?></p></div>
            <div><strong>Gross sales</strong><p><?= e($money($salesTotals['gross'])) ?></p></div>
            <div><strong>Verified refunds</strong><p><?= e($money($salesTotals['refunded'])) ?></p></div>
            <div><strong>Net sales</strong><p><?= e($money($salesTotals['net'])) ?></p></div>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Period</th><th>Paid orders</th><th>Units</th><th>Gross</th><th>Refunded</th><th>Net sales</th></tr></thead>
            <tbody>
            <?php foreach ($reportRows as $row): ?><tr>
                <td><?= e($row['period']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td>
                <td><?= e($money($row['gross'])) ?></td><td><?= e($money($row['refunded'])) ?></td><td><strong><?= e($money($row['net'])) ?></strong></td>
            </tr><?php endforeach; ?>
            <?php if (!$reportRows): ?><tr><td colspan="6">No paid orders in this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    <?php elseif ($reportType === 'products'): ?>
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Orders</th><th>Units</th><th>Gross product sales</th><th>Net after order refunds</th></tr></thead><tbody>
        <?php foreach ($reportRows as $row): ?><tr><td><?= e($row['product']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td><td><?= e($money($row['gross'])) ?></td><td><?= e($money($row['net'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$reportRows): ?><tr><td colspan="5">No purchased products in this period.</td></tr><?php endif; ?>
        </tbody></table></div>
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
</div>
