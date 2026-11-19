<div class="admin-card" style="margin-bottom:var(--space-lg);">
    <h1>Agent Monitoring</h1>
    <h2>Last 24 hours</h2>
    <p>Track response paths and failures for customer support and Enterprise Agent. Model responses still need quality review; these counts do not measure answer accuracy.</p>
    <?php if (($agentMonitoring ?? null) === null): ?>
        <p role="status">Monitoring is temporarily unavailable.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Agent</th><th>Requests</th><th>Model</th><th>Local data</th><th>Fallback</th><th>Sign-in guidance</th><th>Drafts</th><th>Errors</th><th>Average time</th><th>Median (p50)</th><th>95th percentile</th><th>Request error rate</th><th>Provider attempt errors</th><th>Tool errors</th></tr></thead>
            <tbody><?php foreach ($agentMonitoring as $surface => $metrics): ?>
                <tr><th><?= $surface === 'support' ? 'Customer support' : 'Enterprise Agent' ?></th>
                <?php foreach (['requests', 'model', 'local', 'fallback', 'private_account', 'draft', 'error'] as $metric): ?><td><?= (int)$metrics[$metric] ?></td><?php endforeach; ?>
                <td><?= $metrics['average_ms'] === null ? 'No requests yet' : e(number_format($metrics['average_ms'] / 1000, 2)) . ' s' ?></td>
                <td><?= $metrics['p50_ms'] === null ? '—' : e(number_format($metrics['p50_ms'] / 1000, 2)) . ' s' ?></td>
                <td><?= $metrics['p95_ms'] === null ? '—' : e(number_format($metrics['p95_ms'] / 1000, 2)) . ' s' ?></td>
                <td><?= $metrics['requests'] ? e(number_format(100 * $metrics['error'] / $metrics['requests'], 1)) . '%' : '—' ?></td>
                <td><?= (int)$metrics['provider_error_count'] ?>/<?= (int)$metrics['provider_attempt_count'] ?> attempts</td>
                <td><?= (int)$metrics['tool_error_count'] ?>/<?= (int)$metrics['tool_call_count'] ?> calls</td></tr>
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
    <?php
        $states = $supportFeedbackSummary['counts'] ?? [];
        $stateLabels = [
            'confirmed_resolved'=>'Customer confirmed resolved',
            'needs_followup'=>'Customer requested more help',
            'escalated'=>'Escalated to support team',
            'awaiting_feedback'=>'Awaiting feedback',
            'unknown'=>'Legacy/unclassified'
        ];
    ?>
    <div class="admin-stats" style="margin:var(--space-md) 0;">
        <?php foreach ($stateLabels as $key=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value"><?= (int)($states[$key] ?? 0) ?></div>
                <div class="admin-stat__label"><?= e($label) ?></div></div>
        <?php endforeach; ?>
    </div>
    <p>Confirmed resolution rate (sample): <?= ($supportFeedbackSummary['confirmed_percent'] ?? null) === null
        ? 'No support conversations yet' : e(number_format((float)$supportFeedbackSummary['confirmed_percent'],1)).'%' ?>.
        Negative feedback remains unresolved until the customer confirms an answer; silence is not assumed success.</p>
    <?php if (!empty($supportFeedbackSummary['topics'])): ?>
        <h3>Support outcomes by topic</h3>
        <p>Each chat appears once under the topic recorded for its latest completed answer. Chats without a recorded topic appear under Unclassified.</p>
        <?php foreach ($supportFeedbackSummary['topics'] as $topic => $counts):
            $chatCount = array_sum($counts);
        ?>
            <details class="agent-monitor-run">
                <summary><?= $topic === 'unknown' ? 'Unclassified' : e(ucfirst(str_replace('_', ' ', $topic))) ?> · <?= $chatCount ?> <?= $chatCount === 1 ? 'chat' : 'chats' ?></summary>
                <ul>
                    <?php foreach ($stateLabels as $key => $label): if ($counts[$key] === 0) continue; ?>
                        <li><?= e($label) ?> (<?= (int)$counts[$key] ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
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

<section class="admin-card" aria-labelledby="answer-review-summary-title" style="margin-top:var(--space-lg)">
    <h2 id="answer-review-summary-title">Answer review outcomes</h2>
    <p>Latest <?= (int)($answerReviewSummary['conversations']??0) ?> conversations, <?= (int)($answerReviewSummary['turns']??0) ?> completed turns. Staff-declared human decisions are separate from customer satisfaction, AI-assisted labels and synthetic tests. No label means unreviewed, not correct. A turn may have separate human, AI-assisted and test assessments; those provenance counts can overlap.</p>
    <div class="admin-stats">
        <?php foreach(['human_reviewed'=>'Staff-declared human reviewed','supported'=>'Evidence supports answer','needs_correction'=>'Correction needed','insufficient_evidence'=>'Insufficient evidence','agent_assisted'=>'AI-assisted assessments','verification_tests'=>'Synthetic verification tests','unreviewed'=>'Unreviewed turns'] as $key=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value" data-review-metric="<?= e($key) ?>"><?= (int)($answerReviewSummary[$key]??0) ?></div><div class="admin-stat__label"><?= e($label) ?></div></div>
        <?php endforeach; ?>
    </div>
    <p>Supported among staff-declared reviewed turns: <?= ($answerReviewSummary['supported_percent']??null)===null?'Not measured':e(number_format((float)$answerReviewSummary['supported_percent'],1)).'%' ?>. This is a selected review sample, not a production accuracy estimate.</p>
    <?php if(!empty($answerReviewSummary['topics'])): ?>
    <div class="table-wrap"><table><thead><tr><th>Latest conversation topic</th><th>Chats</th><th>Human-reviewed turns</th><th>Supported</th><th>Correction needed</th><th>Insufficient evidence</th></tr></thead><tbody>
        <?php foreach($answerReviewSummary['topics'] as $topic=>$metrics): ?><tr><th><?= e(str_replace('_',' ',$topic)) ?></th><?php foreach(['conversations','human_reviewed','supported','needs_correction','insufficient_evidence'] as $field): ?><td><?= (int)$metrics[$field] ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if(!empty($answerReviewSummary['reasons'])): ?><p><strong>Recorded human review reasons:</strong> <?php foreach($answerReviewSummary['reasons'] as $reason=>$count): ?><?= e(\App\Services\AgentConversationService::REVIEW_REASONS[$reason]??$reason) ?>: <?= (int)$count ?>; <?php endforeach; ?></p><?php endif; ?>
</section>

<section class="admin-card" aria-labelledby="agent-review-queue-title" style="margin-top:var(--space-lg)">
    <h2 id="agent-review-queue-title">Recent conversations needing human review</h2>
    <p>Sampled from the latest 40 chats, not an all-time backlog. A guardrail failure, negative customer feedback or unfinished action requires investigation; no case is automatically declared resolved.</p>
    <?php $reviewRows=array_values(array_filter($agentConversations??[],static fn(array $row):bool=>!empty($row['needs_review']))); ?>
    <?php if($reviewRows===[]): ?><p>No flagged conversations in this recent sample.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Topic</th><th>Reason</th><th>State</th><th>Evidence</th></tr></thead><tbody>
    <?php foreach($reviewRows as $row): ?>
        <tr><td><?= e(str_replace('_',' ',(string)($row['intent']??'unknown'))) ?></td>
            <td><?= e(str_replace('_',' ',(string)($row['review_reason']??'unknown'))) ?></td>
            <td><?= e(str_replace('_',' ',(string)($row['resolution_status']??'unknown'))) ?></td>
            <td><a href="/admin/agent/review/<?= e((string)$row['id']) ?>">Review recorded answer</a> · <a href="/admin/agent/conversation/<?= e((string)$row['id']) ?>">Export authorized JSON</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <p><a href="/admin/support-tickets">Open the human support queue</a>. Chat feedback and ticket closure are separate records.</p>
</section>

<section class="admin-card" aria-labelledby="conversation-audit-title" style="margin-top:var(--space-lg)">
    <h2 id="conversation-audit-title">Conversation audit &amp; evaluations</h2>
    <p>Latest 40 persisted admin/customer/guest conversations. Export requires an authenticated admin session. Basic checks are deterministic, not a guarantee of factual correctness.</p>
    <?php if (empty($agentConversations)): ?><p>No persisted conversations available yet.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Chat</th><th>Actor</th><th>Last activity (UTC)</th><th>Turns</th><th>Unfinished requests</th><th>Guardrails passed</th><th>Resolution state</th><th>Audit JSON</th></tr></thead><tbody>
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
            <td><?= (int)($conversation['pending_turn_count'] ?? 0) ?><?php if(!empty($conversation['pending_turn_count'])): ?><br><small>Running or interrupted; inspect JSON before retrying actions.</small><?php endif; ?></td>
            <td><?= $passed ?>/<?= $turnCount ?></td>
            <td><?= e(str_replace('_',' ',(string)($conversation['resolution_status']??'unknown'))) ?></td>
            <td>
                <a href="/admin/agent/review/<?= e($cid) ?>">Review answers</a> · <a href="/admin/agent/conversation/<?= e($cid) ?>">Export JSON</a>
                <?php if (!empty($conversation['failed_tool_runs'])): ?>
                    <details class="agent-monitor-run">
                        <summary>Failed tool calls</summary>
                        <p>Up to five recent runs from this chat had a tool error. Open a run to review its recorded evidence.</p>
                        <ul>
                            <?php foreach ($conversation['failed_tool_runs'] as $failedRun): ?>
                                <li><a href="/admin/agent/review/<?= e($cid) ?>?turn=<?= e($failedRun['turn_id']) ?>">Run <?= e($failedRun['trace_id']) ?></a>
                                    · <?= (int)$failedRun['errors'] ?> failed <?= $failedRun['errors'] === 1 ? 'call' : 'calls' ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <?php if(!empty($conversation['trace_steps'])): ?>
                <details class="agent-trace-inspector" style="max-width:36rem;overflow-wrap:anywhere;margin-top:.5rem">
                    <summary>Execution evidence · <?= count($conversation['trace_steps']) ?> steps</summary>
                    <p><strong><?= e((string)($conversation['trace_state']??'completed')) ?></strong>
                        · <?= e(str_replace('_',' ',(string)($conversation['trace_ordering']??'unavailable'))) ?>.</p>
                    <ol style="padding-left:1.25rem">
                    <?php foreach($conversation['trace_steps'] as $step): ?>
                        <li><strong><?= e((string)($step['type']??'stage')) ?></strong>
                            <?= e((string)($step['name']??'')) ?> — <?= e((string)($step['status']??'unknown')) ?>
                            <?php if(isset($step['duration_ms'])): ?> · <?= e(number_format((float)$step['duration_ms'],2)) ?> ms<?php endif; ?>
                            <?php if(isset($step['http_status'])): ?> · HTTP <?= (int)$step['http_status'] ?><?php endif; ?>
                            <?php if(isset($step['result_count'])): ?> · <?= (int)$step['result_count'] ?> results<?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ol>
                    <small>Execution evidence is not proof of factual accuracy or client delivery. Inspect unfinished actions before retrying.</small>
                </details>
                <?php endif; ?>
            </td>
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
