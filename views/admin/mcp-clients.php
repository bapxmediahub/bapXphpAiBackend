<?php if ($issuedToken !== null): ?>
<section class="admin-card" aria-labelledby="credential-heading">
    <h2 id="credential-heading">Save this credential now</h2>
    <p>This is its only display. Give it to your trusted local client's secret manager as <code>BAPX_MCP_CLIENT_TOKEN</code> when launching <code>bapXphp mcp:stdio</code>.</p>
    <label for="mcp-token">Client credential</label>
    <textarea id="mcp-token" readonly rows="3" spellcheck="false" autocomplete="off" style="width:100%;overflow-wrap:anywhere"><?= e($issuedToken) ?></textarea>
</section>
<?php endif; ?>
<section class="admin-card" aria-labelledby="grant-heading">
    <h2 id="grant-heading">Authorize a local MCP client</h2>
    <p>Allow this client to read the paid order count, gross sales, verified refunds and net sales for the last 7, 30 or 90 days. Credentials expire after 30 days and you can revoke them at any time.</p>
    <form method="post" action="/admin/mcp-clients/issue" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="issue_nonce" value="<?= e($issueNonce) ?>">
        <label for="client-label">Client name</label>
        <input id="client-label" name="client_label" required maxlength="60" placeholder="Local reporting assistant">
        <button class="btn btn-primary">Authorize paid-sales access</button>
    </form>
</section>
<section class="admin-card" aria-labelledby="clients-heading">
    <h2 id="clients-heading">Client access</h2>
    <?php if (!$clients): ?><p>No client credentials have been issued.</p><?php endif; ?>
    <?php foreach ($clients as $client):
        $expired = strtotime($client['expires_at']) <= time(); ?>
    <article style="border-top:1px solid var(--color-border);padding:var(--space-md) 0;overflow-wrap:anywhere">
        <h3><?= e($client['client_label']) ?></h3>
        <p><?= !empty($client['revoked_at']) ? 'Revoked ' . e($client['revoked_at']) : ($expired ? 'Expired ' : 'Expires ') . e($client['expires_at']) ?></p>
        <p>Issued <?= e($client['issued_at']) ?> · Last successful read <?= e($client['last_used'] ?? 'never') ?></p>
        <?php if (empty($client['revoked_at']) && !$expired): ?>
        <form method="post" action="/admin/mcp-clients/revoke">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="client_id" value="<?= e($client['id']) ?>">
            <button class="btn btn-outline" aria-label="<?= e('Revoke access for '.$client['client_label']) ?>">Revoke access</button>
        </form>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
    <p><a href="/admin/integrations">Back to integrations</a></p>
</section>
