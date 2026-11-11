<div class="admin-card" style="margin-bottom:var(--space-lg);">
    <h1>Agent Monitoring</h1>
    <h2>Last 24 hours</h2>
    <p>Track response paths and failures for customer support and Enterprise Agent. Model responses still need quality review; these counts do not measure answer accuracy.</p>
    <?php if (($agentMonitoring ?? null) === null): ?>
        <p role="status">Monitoring is temporarily unavailable.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Agent</th><th>Requests</th><th>Model</th><th>Local data</th><th>Fallback</th><th>Sign-in guidance</th><th>Drafts</th><th>Errors</th><th>Average time</th><th>95th percentile</th><th>Error rate</th></tr></thead>
            <tbody><?php foreach ($agentMonitoring as $surface => $metrics): ?>
                <tr><th><?= $surface === 'support' ? 'Customer support' : 'Enterprise Agent' ?></th>
                <?php foreach (['requests', 'model', 'local', 'fallback', 'private_account', 'draft', 'error'] as $metric): ?><td><?= (int)$metrics[$metric] ?></td><?php endforeach; ?>
                <td><?= $metrics['average_ms'] === null ? 'No requests yet' : e(number_format($metrics['average_ms'] / 1000, 2)) . ' s' ?></td>
                <td><?= $metrics['p95_ms'] === null ? '—' : e(number_format($metrics['p95_ms'] / 1000, 2)) . ' s' ?></td>
                <td><?= $metrics['requests'] ? e(number_format(100 * $metrics['error'] / $metrics['requests'], 1)) . '%' : '—' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <p style="font-size:0.8rem;color:var(--color-text-muted);">Monitoring records timing, outcomes, failure categories and provider attempt metadata. It omits questions, answers, provider messages, customer identities and secrets.</p>
        <?php foreach ($agentMonitoring as $surface => $metrics): ?>
            <p><strong><?= $surface === 'support' ? 'Customer support' : 'Enterprise Agent' ?> failure reasons:</strong>
            <?php if (empty($metrics['reasons'])): ?>No categorized failures recorded.<?php else: ?>
                <?php foreach ($metrics['reasons'] as $category => $count): ?><?= e(str_replace('_', ' ', $category)) ?>: <?= (int)$count ?>; <?php endforeach; ?>
            <?php endif; ?></p>
            <?php if ($run = $metrics['latest_provider_run']): ?>
                <details>
                    <summary><?= $surface === 'support' ? 'Customer support' : 'Enterprise Agent' ?> latest provider run · <?= e($run['outcome']) ?></summary>
                    <p><time datetime="<?= e($run['at']) ?>"><?= e($run['at']) ?></time></p>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Attempt</th><th>Model</th><th>HTTP status</th><th>Transport error</th><th>Elapsed</th></tr></thead>
                        <tbody><?php foreach ($run['attempts'] as $index => $attempt): ?>
                            <tr><th><?= $index + 1 ?></th>
                                <td><?= e($attempt['model'] ?? 'Unavailable') ?></td>
                                <td><?= $attempt['status'] ?: 'No response' ?></td>
                                <td><?= $attempt['curl_error'] ?: 'None' ?></td>
                                <td><?= e(number_format($attempt['total_ms'] / 1000, 2)) ?> s</td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php if (($agentMonitoring ?? null) !== null): ?>
<section class="admin-card">
    <h2>Recent runs</h2>
    <p>Up to 20 runs from each agent in the last 24 hours. Expand a run to inspect its provider attempts and tool outcomes.</p>
    <?php foreach ($agentMonitoring as $surface => $metrics): ?>
        <h3><?= $surface === 'support' ? 'Customer support' : 'Enterprise Agent' ?></h3>
        <?php if ($metrics['recent_runs'] === []): ?><p>No runs in this period.</p><?php endif; ?>
        <?php foreach ($metrics['recent_runs'] as $run): ?>
            <details class="agent-monitor-run">
                <summary><time datetime="<?= e($run['at']) ?>"><?= e($run['at']) ?></time> · <?= e(str_replace('_', ' ', $run['outcome'])) ?> · <?= e(number_format($run['duration_ms'] / 1000, 2)) ?> s</summary>
                <?php if ($run['reason']): ?><p>Outcome detail: <?= e(str_replace('_', ' ', $run['reason'])) ?></p><?php endif; ?>
                <?php if ($run['attempts']): ?>
                    <ol aria-label="Provider attempts">
                        <?php foreach ($run['attempts'] as $attempt): ?>
                            <li><?= e($attempt['model'] ?? 'Model unavailable') ?> · <?= $attempt['status'] ? 'HTTP ' . $attempt['status'] : 'No HTTP response' ?><?= $attempt['curl_error'] ? ' · transport ' . $attempt['curl_error'] : '' ?> · <?= (int)$attempt['total_ms'] ?> ms</li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <?php if ($run['tools']): ?>
                    <ul aria-label="Tool outcomes">
                        <?php foreach ($run['tools'] as $tool): ?>
                            <li><?= e(str_replace('_', ' ', $tool['name'])) ?> · <?= e($tool['status']) ?> · <?= (int)$tool['duration_ms'] ?> ms</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$run['attempts'] && !$run['tools']): ?><p>No provider or tool activity recorded.</p><?php endif; ?>
            </details>
        <?php endforeach; ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>
