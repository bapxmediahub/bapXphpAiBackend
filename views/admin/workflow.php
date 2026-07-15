<div class="admin-card">
    <h2>Agent Workflow</h2>
    <p style="margin:0;">Orchestration chain: <strong>Issue → CTO → Worker → Reviewer → CTO</strong>. All agents use sequential handoff, never parallel dispatch.</p>
</div>

<div class="admin-card">
    <h2>Handoff Chain</h2>
    <div style="display:flex; gap:var(--space-sm); align-items:center; flex-wrap:wrap; padding:var(--space-md) 0;">
        <span class="badge badge--info">Issue</span>
        <span style="color:var(--color-muted);">→</span>
        <span class="badge badge--warning">CTO</span>
        <span style="color:var(--color-muted);">→</span>
        <span class="badge badge--default">Worker</span>
        <span style="color:var(--color-muted);">→</span>
        <span class="badge badge--default">Reviewer</span>
        <span style="color:var(--color-muted);">→</span>
        <span class="badge badge--success">CTO (close)</span>
        <span style="color:var(--color-muted);">→</span>
        <span class="badge badge--success">Merge</span>
    </div>
    <div style="font-size:0.85rem; color:var(--color-muted);">
        <p>Event triggers: <code>issues: opened</code> → handoff JSON → <code>/handoff worker</code> comment routes to next role.</p>
        <p>Active handoff: <code><?= e($agentPath) ?>/handoffs/active/current.json</code></p>
        <p>Telemetry: <code><?= e($agentPath) ?>/ops/telemetry.json</code></p>
    </div>
</div>

<div class="admin-detail-grid" style="grid-template-columns:1fr 1fr; gap:var(--space-md);">
    <div class="admin-card">
        <h3>Workflow Files</h3>
        <div class="admin-chip-list">
            <?php foreach($workflows as $wf): ?>
                <a href="/<?= e($wf['path']) ?>" target="_blank" class="badge badge--info" style="text-decoration:none;"><?= e($wf['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <p style="margin:var(--space-md) 0 0; font-size:0.85rem; color:var(--color-muted);">
            Schema: <code><?= e($agentPath) ?>/workflows/handoff.schema.json</code>
        </p>
    </div>
    <div class="admin-card">
        <h3>Recent Handoffs</h3>
        <?php if($handoffs): ?>
            <table class="admin-table" style="width:100%; font-size:0.85rem;">
                <thead><tr><th>File</th><th>Issue</th><th>Role</th><th>Next</th></tr></thead>
                <tbody>
                    <?php foreach($handoffs as $h): ?>
                        <tr><td><code><?= e($h['file']) ?></code></td><td>#<?= e((string)$h['issue']) ?></td><td><?= e($h['role']) ?></td><td><?= e($h['next_role']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--color-muted);">No handoff events yet.</p>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <h2>Skills (<?= count($skills) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Skill</th><th>Description</th></tr></thead>
            <tbody>
                <?php foreach($skills as $s): ?>
                    <tr><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['description']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h2>CLI Workflow Commands</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Command</th><th>Purpose</th></tr></thead>
            <tbody>
                <tr><td><code>bapXphp handoff next &lt;issue&gt;</code></td><td>Read JSON with next role + objective</td></tr>
                <tr><td><code>bapXphp handoff template &lt;issue&gt;</code></td><td>Generate empty Worker handoff</td></tr>
                <tr><td><code>bapXphp handoff validate &lt;file&gt;</code></td><td>Validate handoff JSON structure</td></tr>
                <tr><td><code>bapXphp handoff comment &lt;file&gt; &lt;pr&gt;</code></td><td>Upsert PR handoff comment</td></tr>
                <tr><td><code>bapXphp handoff execute &lt;issue&gt;</code></td><td>Emit handoff context JSON</td></tr>
                <tr><td><code>bapXphp handoff score &lt;issue&gt;</code></td><td>Score the work cycle</td></tr>
                <tr><td><code>bapXphp update</code></td><td>Regenerate both project maps</td></tr>
                <tr><td><code>bapXphp ci</code></td><td>Full non-mutating validation</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h2>Wiring Diagram (Mermaid)</h2>
    <pre style="background:var(--color-canvas); padding:var(--space-md); border-radius:var(--radius-sm); overflow-x:auto; font-size:0.8rem; line-height:1.6;">
flowchart LR
    ISSUE["GitHub Issue"] --> CTO["CTO"]
    CTO --> HANDOFF["bapXphp handoff next"]
    HANDOFF --> WORKER["Worker"]
    WORKER --> EVIDENCE["Handoff Evidence"]
    EVIDENCE --> REVIEWER["Reviewer"]
    REVIEWER --> FINDINGS["Findings"]
    FINDINGS --> CTO_CLOSE["CTO"]
    CTO_CLOSE --> MERGE["PR Merge → main"]
    MERGE --> SYNC["Schedule Sync (fork)"]
    CTO --> SKILLS["Skills (.agents/skills/)"]
    WORKER --> SKILLS
    REVIEWER --> WORKFLOWS["Workflows (.agents/workflows/)"]
    CTO --> TELEMETRY["Telemetry (.agents/ops/)"]
    </pre>
</div>