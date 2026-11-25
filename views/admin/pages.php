<?php
$editing = ($form['original_slug'] ?? '') !== '';
$headerImages ??= [];
$selectedHeader = $form['header_image_url'] ?? '';
$knownHeader = in_array($selectedHeader, array_column($headerImages, 'url'), true) ? $selectedHeader : '';
$savedCampaignFields = is_array($form['campaign_form'] ?? null) ? $form['campaign_form'] : null;
$campaignEnabled = array_key_exists('campaign_enabled', $form)
    ? in_array($form['campaign_enabled'], ['1', 1, true], true)
    : $savedCampaignFields !== null;
$campaignFields = is_array($form['campaign_fields'] ?? null)
    ? $form['campaign_fields']
    : array_column($savedCampaignFields ?? [], null, 'id');
$campaignTypes = ['' => 'Unused'] + \App\Services\CampaignLeadService::OPTIONAL_TYPES;
$downloadManifest = \App\Services\CampaignDownloadService::manifest($form);
$downloadEnabled = array_key_exists('download_enabled', $form)
    ? $form['download_enabled'] === '1' : ($downloadManifest['enabled'] ?? false);
$downloadPositions = is_array($form['download_existing'] ?? null) ? $form['download_existing'] : null;
?>
<section class="admin-card" aria-labelledby="pages-title">
    <h1 id="pages-title">Pages</h1>
    <?php if ($pages): ?>
        <ul><?php foreach ($pages as $item): ?>
            <li><a href="/admin/pages?edit=<?= e(rawurlencode($item['slug'])) ?>"><?= e($item['title']) ?></a> · <?= e(ucfirst($item['status'])) ?></li>
        <?php endforeach; ?></ul>
    <?php else: ?><p>No authored pages yet.</p><?php endif; ?>
    <?php if ($editing): ?><a class="btn btn-outline" href="/admin/pages#page-editor">New page</a><?php endif; ?>
</section>
<section id="page-editor" class="admin-card" aria-labelledby="page-editor-title">
    <h2 id="page-editor-title"><?= $editing ? 'Edit page' : 'Create a page' ?></h2>
    <?php if (!empty($error)): ?><p role="alert"><?= e($error) ?></p>
        <?php if (!empty($form['slug'])): ?><p><a href="/admin/pages?edit=<?= e(rawurlencode($form['slug'])) ?>">Reopen the saved page</a></p><?php endif; ?>
    <?php endif; ?>
    <form class="admin-form" action="/admin/pages/save" method="post" enctype="multipart/form-data" id="page-draft-form"
          data-draft-owner="<?= e($pageDraftOwner ?? '') ?>" data-saved-draft="<?= e($savedPageDraftToken ?? '') ?>"
          data-submitted="<?= isset($preview) || !empty($error) ? '1' : '0' ?>">
        <section id="page-draft-recovery" hidden aria-labelledby="page-draft-heading">
            <h3 id="page-draft-heading">Unsaved browser draft</h3>
            <p id="page-draft-message"></p>
            <div class="admin-card__actions">
                <button type="button" class="btn btn-primary" id="page-draft-restore">Restore draft</button>
                <button type="button" class="btn btn-outline" id="page-draft-download">Download browser draft</button>
                <button type="button" class="btn btn-outline" id="page-draft-discard">Discard browser copy</button>
            </div>
        </section>
        <p role="status" id="page-draft-status"></p>
        <input type="hidden" name="browser_draft_token" value="">
        <div id="page-draft-fields">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="original_slug" value="<?= e($form['original_slug'] ?? '') ?>">
        <input type="hidden" name="expected_revision" value="<?= e($form['expected_revision'] ?? '0') ?>">
        <label>Title <input name="title" maxlength="160" value="<?= e($form['title'] ?? '') ?>" required></label>
        <label>Page address under /pages/ <input name="slug" maxlength="32" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="visiting-guide" value="<?= e($form['slug'] ?? '') ?>" <?= $editing ? 'readonly' : '' ?> required>
        <small><?= $editing ? 'The address is fixed for this page.' : 'Use lowercase letters, digits and hyphens. The address stays fixed after saving.' ?></small></label>
        <fieldset class="page-header-media">
            <legend>Header image</legend>
            <label for="page-header-image">Choose uploaded image</label>
            <select id="page-header-image" name="header_image_url" aria-describedby="page-header-image-help">
                <option value="">No header image</option>
                <?php foreach ($headerImages as $image): ?>
                    <option value="<?= e($image['url']) ?>" <?= $knownHeader === $image['url'] ? 'selected' : '' ?>><?= e($image['original_name'] ?? $image['filename'] ?? basename($image['url'])) ?></option>
                <?php endforeach; ?>
            </select>
            <small id="page-header-image-help">Use an uploaded JPG, PNG, WebP or GIF from Media Library.</small>
            <?php if ($selectedHeader !== '' && $knownHeader === ''): ?>
                <p role="status">The previous selection is unavailable. Choose another image or save without a header.</p>
            <?php endif; ?>
            <img class="page-header-media__selection" id="page-header-thumbnail"
                <?php if ($knownHeader !== ''): ?>src="<?= e($knownHeader) ?>"<?php else: ?>hidden<?php endif; ?>
                alt="" decoding="async">
            <label for="page-header-alt">Alternative text</label>
            <input id="page-header-alt" name="header_image_alt" maxlength="255" value="<?= e($form['header_image_alt'] ?? '') ?>">
            <small>Describe what the image conveys. Leave blank when it is decorative.</small>
            <div class="page-header-media__upload" id="page-header-upload-controls" hidden>
                <label for="page-header-file">Image file</label>
                <input id="page-header-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                <button type="button" class="btn btn-outline" id="page-header-upload">Upload to Media Library</button>
                <p id="page-header-upload-status" role="status"></p>
            </div>
            <noscript><a href="/admin/media" target="_blank" rel="noopener">Open Media Library to upload in a new tab</a></noscript>
        </fieldset>
        <label>Page content <textarea name="content" rows="13" required><?= e($form['content'] ?? '') ?></textarea>
        <small>Write in Markdown. HTML tags appear as text.</small></label>
        <fieldset class="campaign-form-settings" aria-describedby="campaign-form-settings-help">
            <legend>Enquiry form</legend>
            <label class="campaign-form-settings__checkbox">
                <input type="checkbox" name="campaign_enabled" value="1" <?= $campaignEnabled ? 'checked' : '' ?>>
                Show an enquiry form on this page
            </label>
            <p id="campaign-form-settings-help">Name and email are required. You can add up to three fields.</p>
            <?php foreach (\App\Services\CampaignLeadService::SLOT_IDS as $index => $fieldId): ?>
                <?php
                $field = is_array($campaignFields[$fieldId] ?? null) ? $campaignFields[$fieldId] : [];
                $type = $field['type'] ?? '';
                $label = is_string($field['label'] ?? null) ? $field['label'] : '';
                $required = in_array($field['required'] ?? null, ['1', 1, true], true);
                ?>
                <fieldset class="campaign-form-settings__field">
                    <legend>Additional field <?= $index + 1 ?></legend>
                    <label for="campaign-<?= e($fieldId) ?>-type">Field type</label>
                    <select id="campaign-<?= e($fieldId) ?>-type" name="campaign_fields[<?= e($fieldId) ?>][type]">
                        <?php foreach ($campaignTypes as $typeId => $typeName): ?>
                            <option value="<?= e($typeId) ?>" <?= $type === $typeId ? 'selected' : '' ?>><?= e($typeName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="campaign-<?= e($fieldId) ?>-label">Field label</label>
                    <input id="campaign-<?= e($fieldId) ?>-label" type="text" name="campaign_fields[<?= e($fieldId) ?>][label]" maxlength="72" value="<?= e($label) ?>">
                    <label class="campaign-form-settings__checkbox">
                        <input type="checkbox" name="campaign_fields[<?= e($fieldId) ?>][required]" value="1" <?= $required ? 'checked' : '' ?>>
                        Require an answer
                    </label>
                </fieldset>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="campaign-form-settings campaign-download-settings"
            data-max-files="<?= \App\Services\CampaignDownloadService::MAX_FILES ?>"
            data-max-bytes="<?= \App\Services\CampaignDownloadService::MAX_BYTES ?>" aria-describedby="campaign-download-settings-help">
            <legend>Downloads</legend>
            <input type="hidden" name="download_editor" value="1">
            <label class="campaign-form-settings__checkbox">
                <input type="checkbox" name="download_enabled" value="1" <?= $downloadEnabled ? 'checked' : '' ?>>
                Enable downloads
            </label>
            <p id="campaign-download-settings-help">Choose up to <?= \App\Services\CampaignDownloadService::MAX_FILES ?> files totalling 5 MB (5,000,000 bytes). A published enquiry form asks visitors to submit before they can download.</p>
            <?php if ($downloadManifest['files'] ?? []): ?>
                <ul class="campaign-download-settings__files" aria-label="Saved download files">
                    <?php foreach ($downloadManifest['files'] as $index => $file): ?>
                        <?php $position = $downloadPositions === null ? (string)($index + 1) : ($downloadPositions[$file['id']] ?? ''); ?>
                        <li data-download-bytes="<?= $file['bytes'] ?>" data-download-name="<?= e($file['name']) ?>">
                            <span class="campaign-download__name"><?= e($file['name']) ?></span>
                            <label>
                                <span>Position <span class="sr-only">for <?= e($file['name']) ?></span></span>
                                <select name="download_existing[<?= e($file['id']) ?>]">
                                    <option value="" <?= $position === '' ? 'selected' : '' ?>>Remove</option>
                                    <?php for ($number = 1; $number <= \App\Services\CampaignDownloadService::MAX_FILES; $number++): ?>
                                        <option value="<?= $number ?>" <?= $position === (string)$number ? 'selected' : '' ?>><?= $number ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <small>Choose a different position for each retained file. New files go after them.</small>
            <?php endif; ?>
            <label for="campaign-download-new">Add files</label>
            <input type="file" id="campaign-download-new" name="download_files[]" multiple
                accept=".pdf,.zip,.txt,.png,.jpg,.jpeg,.webp" aria-describedby="campaign-download-formats">
            <small id="campaign-download-formats">PDF, ZIP, TXT, PNG, JPG and WebP. Select files again after previewing or reopening a draft.</small>
            <p id="campaign-download-total" role="status" aria-atomic="true"></p>
            <?php if (!empty($downloadNotice)): ?><p role="status"><?= e($downloadNotice) ?></p><?php endif; ?>
        </fieldset>
        <label>Visibility <select name="status">
            <option value="draft">Draft</option>
            <option value="published" <?= ($form['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        </select></label>
        <div class="admin-card__actions">
            <button class="btn btn-outline" name="action" value="preview" formaction="/admin/pages/save#page-preview">Preview</button>
            <button class="btn btn-primary" name="action" value="save">Save page</button>
        </div>
        </div>
    </form>
    <?php if ($editing && empty($error) && !isset($preview) && ($form['status'] ?? '') === 'published'): ?>
        <p><a href="/pages/<?= e(rawurlencode($form['slug'])) ?>">Open published page</a></p>
    <?php endif; ?>
    <?php if ($editing && empty($error) && !isset($preview)): ?>
        <details><summary>Delete page</summary>
            <form class="admin-form" method="post" action="/admin/pages/delete">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="slug" value="<?= e($form['slug']) ?>">
                <input type="hidden" name="expected_revision" value="<?= e($form['expected_revision']) ?>">
                <label><input type="checkbox" required name="confirm_delete" value="yes"> Permanently delete this page and its content</label>
                <button class="btn btn-outline" type="submit">Delete this page</button>
            </form>
        </details>
    <?php endif; ?>
</section>
<?php if (isset($preview)): ?>
    <section id="page-preview" class="admin-card" tabindex="-1" aria-labelledby="page-preview-title">
        <h2 id="page-preview-title">Unsaved preview</h2>
        <?php
        $page = $preview['page'];
        $html = $preview['html'];
        $authoredPreview = true;
        require app_path('views/public/authored-page.php');
        ?>
    </section>
<?php endif; ?>

<script src="/assets/page-draft.js?v=<?= filemtime(app_path('assets/page-draft.js')) ?>" defer></script>
