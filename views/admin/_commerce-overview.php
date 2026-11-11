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
$refundPoints = [];
foreach ($reportRows as $index => $row) {
    $x = count($reportRows) === 1 ? 500 : 40 + (920 * $index / $chartCount);
    $netPoints[] = round($x, 1) . ',' . round(220 - (180 * (float)$row['net'] / $chartMax), 1);
    $refundPoints[] = round($x, 1) . ',' . round(220 - (180 * (float)$row['refunded'] / $chartMax), 1);
}
?>
<section class="report-evidence" aria-labelledby="revenue-chart-title">
    <div class="report-evidence__head">
        <div><h2 id="revenue-chart-title">Net revenue and refunds</h2><p>Paid order revenue minus verified refunds for the selected period.</p></div>
        <div class="report-legend" aria-label="Chart legend"><span><i></i>Net revenue</span><span><i class="refund"></i>Verified refunds</span></div>
    </div>
    <?php if ($reportRows): ?>
    <svg class="report-chart" role="img" aria-labelledby="revenue-chart-title revenue-chart-desc" viewBox="0 0 1000 260" preserveAspectRatio="none">
        <desc id="revenue-chart-desc"><?= e((string)count($reportRows)) ?> periods from <?= e($from) ?> through <?= e($to) ?>. Net revenue is <?= e($money($salesTotals['net'])) ?> and verified refunds are <?= e($money($salesTotals['refunded'])) ?>.</desc>
        <line class="grid" x1="40" y1="40" x2="960" y2="40"/><line class="grid" x1="40" y1="100" x2="960" y2="100"/><line class="grid" x1="40" y1="160" x2="960" y2="160"/><line class="grid" x1="40" y1="220" x2="960" y2="220"/>
        <polyline class="refund-line" points="<?= e(implode(' ', $refundPoints)) ?>"/>
        <polyline class="net" points="<?= e(implode(' ', $netPoints)) ?>"/>
        <?php foreach (['net' => $netPoints, 'refund' => $refundPoints] as $series => $points): ?>
            <?php foreach ($points as $point): [$x, $y] = explode(',', $point); ?>
                <circle class="report-marker report-marker--<?= e($series) ?>" cx="<?= e($x) ?>" cy="<?= e($y) ?>" r="4"/>
            <?php endforeach; ?>
        <?php endforeach; ?>
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
        <?php foreach ($productRows as $row): ?><tr><td><?= e($row['product']) ?></td><td><?= e((string)$row['orders']) ?></td><td><?= e((string)$row['units']) ?></td><td><strong><?= e($money($row['net'])) ?></strong></td></tr><?php endforeach; ?>
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
