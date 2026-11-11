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
        <?php require __DIR__ . '/_commerce-overview.php'; ?>
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
        <h2>GST invoice register</h2>
        <p>Compare issued invoice totals with the line register for this invoice-date range. The line register flags missing data and verified refunds for review. These CSVs are for reconciliation; they are not GST portal upload files.</p>
        <div class="report-filters">
            <a class="btn btn-outline" href="?<?= e($query) ?>&amp;format=csv">Download invoice totals CSV</a>
            <a class="btn btn-primary" href="?<?= e($query) ?>&amp;format=lines">Download invoice lines CSV</a>
        </div>
        <?php if(!empty($invoiceReviewReasons)): ?><div class="flash flash--error" role="alert"><strong><?= e((string)count($invoiceReviewReasons)) ?> issued invoice(s) require accountant review.</strong> Their stored tax fields have not been rewritten. Review the delivery state and issue a compliant correction outside this screen if advised.</div><?php endif; ?>
        <div class="admin-detail-grid">
            <div><strong>Taxable sales</strong><p><?= e($money($totals['taxable'])) ?></p></div><div><strong>CGST</strong><p><?= e($money($totals['cgst'])) ?></p></div><div><strong>SGST</strong><p><?= e($money($totals['sgst'])) ?></p></div><div><strong>IGST</strong><p><?= e($money($totals['igst'])) ?></p></div><div><strong>Total GST</strong><p><?= e($money($totals['tax'])) ?></p></div><div><strong>Gross product sales</strong><p><?= e($money($totals['gross'])) ?></p></div>
        </div>
        <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Place of supply</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Total</th><th>Review</th></tr></thead><tbody><?php foreach($orders as $order): ?><?php $reviewKey=(string)($order['id']??$order['invoice_number']??''); ?><tr><td><a href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice"><?= e($order['invoice_number']??'') ?></a> <a download href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice?download=1">Download</a></td><td><?= e(substr((string)($order['invoice_date']??''),0,10)) ?></td><td><?= e($order['customer_email']??'') ?></td><td><?= e($order['place_of_supply']??'') ?></td><td><?= e($money($order['taxable_value']??0)) ?></td><td><?= e($money($order['cgst_total']??0)) ?></td><td><?= e($money($order['sgst_total']??0)) ?></td><td><?= e($money($order['igst_total']??0)) ?></td><td><?= e($money($order['total']??0)) ?></td><td><?= e($invoiceReviewReasons[$reviewKey]??'—') ?></td></tr><?php endforeach; ?><?php if(!$orders): ?><tr><td colspan="10">No paid product invoices in this period.</td></tr><?php endif; ?></tbody></table></div>
    <?php endif; ?>
</div>
