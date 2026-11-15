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
                <?php if ($run['trace_id']): ?><p>Run reference <code><?= e($run['trace_id']) ?></code></p><?php endif; ?>
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
                            <li><?= e(str_replace('_', ' ', $tool['name'])) ?> · <?= e($tool['status']) ?><?php if (!empty($tool['reason'])): ?> · <?= e(str_replace('_', ' ', $tool['reason'])) ?><?php endif; ?> · <?= (int)$tool['duration_ms'] ?> ms</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$run['attempts'] && !$run['tools']): ?><p>No provider or tool activity recorded.</p><?php endif; ?>
            </details>
        <?php endforeach; ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="admin-card" aria-labelledby="agent-quality-title" style="margin-top:var(--space-lg)">
    <h2 id="agent-quality-title">Verified support feedback &amp; regression evaluations</h2>
    <p>Customer confirmation is distinct from merely answering a question. These support figures cover the most recent
        <?= (int)($supportFeedbackSummary['sample_size'] ?? 0) ?> sampled support conversations (up to 40 total chats), not all-time traffic.
        Unrated conversations are not counted as resolved.</p>
    <?php $states = ($supportFeedbackSummary['counts'] ?? []); ?>
    <div class="admin-stats" style="margin:var(--space-md) 0;">
        <?php foreach ([
            'confirmed_resolved'=>'Customer confirmed resolved',
            'needs_followup'=>'Customer requested more help',
            'escalated'=>'Escalated to support team',
            'awaiting_feedback'=>'Awaiting feedback',
            'unknown'=>'Legacy/unclassified'
        ] as $key=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value"><?= (int)($states[$key] ?? 0) ?></div>
                <div class="admin-stat__label"><?= e($label) ?></div></div>
        <?php endforeach; ?>
    </div>
    <p>Confirmed resolution rate (sample): <?= ($supportFeedbackSummary['confirmed_percent'] ?? null) === null
        ? 'No support conversations yet' : e(number_format((float)$supportFeedbackSummary['confirmed_percent'],1)).'%' ?>.
        Negative feedback remains unresolved until the customer confirms an answer; silence is not assumed success.</p>
    <h3>Offline PHP golden evaluation set</h3>
    <?php if ($agentGoldenSuite === null): ?>
        <p role="status">Golden regression fixtures unavailable.</p>
    <?php else: ?>
        <p><strong><?= (int)$agentGoldenSuite['passing'] ?>/<?= (int)$agentGoldenSuite['total'] ?> passing</strong>
            · Dataset SHA-256 <code><?= e(substr($agentGoldenSuite['dataset_hash'],0,12)) ?>…</code>.</p>
        <p>These fixtures test known decisions and source scopes; they do not certify live factual accuracy,
            customer satisfaction, or model reasoning quality.</p>
        <?php if ($agentGoldenSuite['failed']): ?>
            <details><summary>Inspect failing fixtures (<?= (int)$agentGoldenSuite['failed'] ?>)</summary>
                <ul><?php foreach ($agentGoldenSuite['results'] as $case): if ($case['passed']) continue; ?>
                    <li><?= e($case['id']) ?></li>
                <?php endforeach; ?></ul>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="admin-card" aria-labelledby="conversation-audit-title" style="margin-top:var(--space-lg)">
    <h2 id="conversation-audit-title">Conversation audit &amp; evaluations</h2>
    <p>Latest 40 persisted admin/customer/guest conversations. Export requires an authenticated admin session. Basic checks are deterministic, not a guarantee of factual correctness.</p>
    <?php if (empty($agentConversations)): ?><p>No persisted conversations available yet.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Chat</th><th>Actor</th><th>Last activity (UTC)</th><th>Turns</th><th>Guardrails passed</th><th>Resolution state</th><th>Audit JSON</th></tr></thead><tbody>
    <?php foreach ($agentConversations as $conversation):
        $turnCount = (int)($conversation['turn_count'] ?? 0);
        $passed = (int)($conversation['passed_turns'] ?? 0);
        $cid = (string)($conversation['id'] ?? '');
    ?>
        <tr>
            <td><?= e((string)($conversation['surface'] ?? '')) ?></td>
            <td><?= e((string)($conversation['actor']['type'] ?? '')) ?> · <?= e(mb_substr((string)($conversation['actor']['id'] ?? ''),0,64)) ?></td>
            <td><?= e((string)($conversation['updated_at'] ?? $conversation['started_at'] ?? '')) ?></td>
            <td><?= $turnCount ?></td>
            <td><?= $passed ?>/<?= $turnCount ?></td>
            <td><?= e(str_replace('_',' ',(string)($conversation['resolution_status']??'unknown'))) ?></td>
            <td><a href="/admin/agent/conversation/<?= e($cid) ?>">Export JSON</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <h3>First-party commerce events (30 days)</h3>
    <p>Counts are successful instrumented actions, not unique visitors or a conversion rate.</p>
    <div class="admin-stats" style="margin:var(--space-md) 0;">
    <?php foreach (($commerceTelemetry ?? []) as $event => $count): ?>
        <div class="admin-stat"><div class="admin-stat__value"><?= (int)$count ?></div><div class="admin-stat__label"><?= e(str_replace('_',' ',ucwords($event,'_'))) ?></div></div>
    <?php endforeach; ?>
    </div>
</section>
