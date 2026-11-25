<?php
$authoredPreview ??= false;
$headerUrl = $page['header_image_url'] ?? '';
$campaignDefinition = \App\Services\CampaignLeadService::pageDefinition($page);
$campaignValues = $authoredPreview ? [] : ($campaignState['values'] ?? []);
$campaignErrors = $authoredPreview ? [] : ($campaignState['errors'] ?? []);
$campaignReceived = !$authoredPreview && !empty($campaignState['received']);
$campaignWithdrawn = $campaignDefinition === null && isset($campaignErrors['form']);
?>
<section class="section document-page<?= $authoredPreview ? ' authored-page--preview' : '' ?>">
    <div class="container container--narrow">
        <header class="document-page__header">
            <?php if ($authoredPreview): ?><h3 class="section-title"><?= e($page['title']) ?></h3>
            <?php else: ?><h1 class="section-title"><?= e($page['title']) ?></h1><?php endif; ?>
            <?php if (\App\Services\MediaService::isPageHeaderPath($headerUrl)): ?>
                <img class="authored-page__image" src="<?= e($headerUrl) ?>"
                    alt="<?= e(is_string($page['header_image_alt'] ?? null) ? $page['header_image_alt'] : '') ?>" decoding="async">
            <?php endif; ?>
        </header>
        <article class="document-page__content"><?= $html ?></article>
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
                        <form class="contact-form" method="post" action="/pages/<?= e(rawurlencode($page['slug'])) ?>/enquiry">
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
    </div>
</section>
