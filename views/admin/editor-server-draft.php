<section class="admin-card" data-server-draft aria-label="Server draft recovery">
    <h3>Server draft recovery</h3>
    <p role="status" data-server-status>Server recovery is available with JavaScript. Save explicitly to update the website.</p>
    <div data-server-recovery hidden>
        <label>Recovery version <select data-server-versions aria-label="Recovery version"></select></label>
        <p>Restore only the matching saved revision. If the content changed, download the recovery copy before discarding it.</p>
        <div class="admin-card__actions">
            <button type="button" class="btn btn-primary" data-server-restore>Restore server draft</button>
            <button type="button" class="btn btn-outline" data-server-download>Download server recovery</button>
            <button type="button" class="btn btn-outline" data-server-download-current>Download current edit</button>
            <button type="button" class="btn btn-outline" data-server-discard>Discard server recovery</button>
        </div>
    </div>
    <input type="hidden" name="server_draft_token" value="">
</section>
