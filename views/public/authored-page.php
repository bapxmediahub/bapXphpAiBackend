<?php
$authoredPreview ??= false;
$headerUrl = $page['header_image_url'] ?? '';
$campaignDefinition = \App\Services\CampaignLeadService::pageDefinition($page);
$campaignValues = $authoredPreview ? [] : ($campaignState['values'] ?? []);
$campaignErrors = $authoredPreview ? [] : ($campaignState['errors'] ?? []);
$campaignReceived = !$authoredPreview && !empty($campaignState['received']);
$campaignWithdrawn = $campaignDefinition === null && isset($campaignErrors['form']);
?>
<section class="section authored-page<?= $authoredPreview ? ' authored-page--preview' : '' ?>">
    <article class="container document-page__content">
        <header>
            <?php if ($authoredPreview): ?><h3 class="authored-page__title"><?= e($page['title']) ?></h3>
            <?php else: ?><h1 class="authored-page__title"><?= e($page['title']) ?></h1><?php endif; ?>
            <?php if (\App\Services\MediaService::isPageHeaderPath($headerUrl)): ?>
                <img class="authored-page__image" src="<?= e($headerUrl) ?>"
                    alt="<?= e(is_string($page['header_image_alt'] ?? null) ? $page['header_image_alt'] : '') ?>" decoding="async">
            <?php endif; ?>
        </header>
        <div class="blog-post__body"><?= $html ?></div>
        <?php if ($campaignReceived || $campaignDefinition !== null || $campaignWithdrawn): ?>
            <section class="contact-form-card campaign-enquiry" id="campaign-enquiry" aria-labelledby="campaign-enquiry-title" tabindex="-1"
                <?= $campaignReceived || $campaignErrors ? 'autofocus' : '' ?>>
                <?php if ($campaignWithdrawn): ?>
                    <h2 class="campaign-enquiry__title" id="campaign-enquiry-title">Enquiry unavailable</h2>
                    <p role="alert"><?= e($campaignErrors['form']) ?></p>
                <?php elseif ($campaignReceived): ?>
                    <h2 class="campaign-enquiry__title" id="campaign-enquiry-title">Enquiry received</h2>
                    <p>Thank you for getting in touch. Your enquiry is saved for the page owner.</p>
                <?php else: ?>
                    <?php $campaignFields = \App\Services\CampaignLeadService::publicFields($campaignDefinition); ?>
                    <?php if ($authoredPreview): ?>
                        <h4 class="campaign-enquiry__title" id="campaign-enquiry-title">Send an enquiry</h4>
                        <p>Preview only. This form is disabled.</p>
                    <?php else: ?>
                        <h2 class="campaign-enquiry__title" id="campaign-enquiry-title">Send an enquiry</h2>
                    <?php endif; ?>
                    <?php if ($campaignErrors): ?>
                        <div class="contact-form__errors" role="alert">
                            <?php if (isset($campaignErrors['form'])): ?>
                                <p><?= e($campaignErrors['form']) ?></p>
                            <?php else: ?>
                                <p>Check the highlighted fields and try again.</p>
                            <?php endif; ?>
                            <?php if (array_diff_key($campaignErrors, ['form'=>true])): ?>
                                <ul>
                                    <?php foreach ($campaignFields as $field): ?>
                                        <?php if (isset($campaignErrors[$field['id']])): ?>
                                            <li><a href="#campaign-field-<?= e($field['id']) ?>"><?= e($field['label']) ?></a></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$authoredPreview): ?>
                        <form class="contact-form" method="post" action="/pages/<?= e(rawurlencode($page['slug'])) ?>/enquiry#campaign-enquiry">
                            <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf_token'] ??= bin2hex(random_bytes(16))) ?>">
                            <input type="hidden" name="revision" value="<?= e($page['revision']) ?>">
                            <input type="hidden" name="submission_token" value="<?= e($campaignState['token'] ?? '') ?>">
                    <?php endif; ?>
                    <fieldset class="campaign-enquiry__fields contact-form" <?= $authoredPreview ? 'disabled' : '' ?>>
                        <legend class="sr-only">Enquiry details</legend>
                        <?php foreach ($campaignFields as $field): ?>
                            <?php
                            $id = $field['id'];
                            $inputId = 'campaign-field-' . $id;
                            $value = $campaignValues[$id] ?? '';
                            $fieldError = $campaignErrors[$id] ?? null;
                            $autocomplete = match (true) {
                                $id === 'name' => 'name',
                                $id === 'email' => 'email',
                                $field['type'] === 'tel' => 'tel',
                                default => null,
                            };
                            ?>
                            <div class="form-group campaign-enquiry__field<?= str_starts_with($id, 'detail_') ? ' campaign-enquiry__field--wide' : '' ?>">
                                <label for="<?= e($inputId) ?>">
                                    <?= e($field['label']) ?> <span class="campaign-enquiry__optional">(<?= $field['required'] ? 'required' : 'optional' ?>)</span>
                                </label>
                                <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea id="<?= e($inputId) ?>" name="answers[<?= e($id) ?>]" rows="4" maxlength="<?= (int)$field['limit'] ?>"
                                        <?= $field['required'] ? 'required' : '' ?><?php if ($fieldError !== null): ?> aria-invalid="true" aria-describedby="<?= e($inputId) ?>-error"<?php endif; ?>><?= e($value) ?></textarea>
                                <?php else: ?>
                                    <input id="<?= e($inputId) ?>" name="answers[<?= e($id) ?>]" type="<?= e($field['type']) ?>"
                                        maxlength="<?= (int)$field['limit'] ?>" value="<?= e($value) ?>"<?= $field['required'] ? ' required' : '' ?>
                                        <?php if ($autocomplete !== null): ?>autocomplete="<?= e($autocomplete) ?>"<?php endif; ?>
                                        <?php if ($fieldError !== null): ?>aria-invalid="true" aria-describedby="<?= e($inputId) ?>-error"<?php endif; ?>>
                                <?php endif; ?>
                                <?php if ($fieldError !== null): ?><p id="<?= e($inputId) ?>-error" class="error"><?= e($fieldError) ?></p><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn btn-primary campaign-enquiry__submit" type="submit">Send enquiry</button>
                    </fieldset>
                    <?php if (!$authoredPreview): ?></form><?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php
        $downloadManifest = \App\Services\CampaignDownloadService::manifest($page);
        if (($downloadManifest['enabled'] ?? false) && $downloadManifest['files']):
        ?>
            <section id="campaign-downloads" class="contact-form-card campaign-downloads" aria-labelledby="campaign-downloads-title">
                <?php if ($authoredPreview): ?>
                    <h4 id="campaign-downloads-title">Downloads</h4>
                <?php else: ?>
                    <h2 id="campaign-downloads-title">Downloads</h2>
                <?php endif; ?>
                <?php if ($authoredPreview || !empty($campaignDownloadAccess)): ?>
                    <ul>
                        <?php foreach ($downloadManifest['files'] as $download): ?>
                            <li>
                                <?php if ($authoredPreview): ?>
                                    <span class="campaign-download__name"><?= e($download['name']) ?></span>
                                <?php else: ?>
                                    <a class="campaign-download__name" href="/pages/<?= e($page['slug']) ?>/downloads/<?= e($page['revision']) ?>/<?= e($download['id']) ?>"><?= e($download['name']) ?></a>
                                <?php endif; ?>
                                <span class="campaign-download__size"><?= e(number_format($download['bytes'])) ?> bytes</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($authoredPreview): ?><p>Download links appear on the published page when access is available.</p><?php endif; ?>
                <?php elseif ($campaignDefinition !== null): ?>
                    <p>Send an enquiry to unlock the downloads.</p>
                    <a class="btn btn-outline" href="#campaign-enquiry">Go to enquiry form</a>
                <?php else: ?>
                    <p>Downloads are currently unavailable.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </article>
</section>
