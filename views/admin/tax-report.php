<div class="admin-card">
    <div class="admin-page-head"><div><h1>GST Product Sales Report</h1><p>Paid ecommerce product invoices only. Consultant bookings are excluded.</p></div></div>
    <form method="get" class="admin-form__row" style="align-items:end;margin-bottom:var(--space-lg)">
        <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn btn-primary">Apply</button>
        <a class="btn btn-outline" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&format=csv">Export CSV</a>
    </form>
    <div class="admin-detail-grid">
        <div><strong>Taxable sales</strong><p>₹<?= e(number_format($totals['taxable'],2)) ?></p></div><div><strong>CGST</strong><p>₹<?= e(number_format($totals['cgst'],2)) ?></p></div><div><strong>SGST</strong><p>₹<?= e(number_format($totals['sgst'],2)) ?></p></div><div><strong>IGST</strong><p>₹<?= e(number_format($totals['igst'],2)) ?></p></div><div><strong>Total GST</strong><p>₹<?= e(number_format($totals['tax'],2)) ?></p></div><div><strong>Gross product sales</strong><p>₹<?= e(number_format($totals['gross'],2)) ?></p></div>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Place of supply</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Total</th></tr></thead><tbody><?php foreach($orders as $order): ?><tr><td><?= e($order['invoice_number']??'') ?></td><td><?= e(substr((string)($order['invoice_date']??''),0,10)) ?></td><td><?= e($order['customer_email']??'') ?></td><td><?= e($order['place_of_supply']??'') ?></td><td>₹<?= e(number_format((float)($order['taxable_value']??0),2)) ?></td><td>₹<?= e(number_format((float)($order['cgst_total']??0),2)) ?></td><td>₹<?= e(number_format((float)($order['sgst_total']??0),2)) ?></td><td>₹<?= e(number_format((float)($order['igst_total']??0),2)) ?></td><td>₹<?= e(number_format((float)($order['total']??0),2)) ?></td></tr><?php endforeach; ?><?php if(!$orders): ?><tr><td colspan="9">No paid product invoices in this period.</td></tr><?php endif; ?></tbody></table></div>
</div>
