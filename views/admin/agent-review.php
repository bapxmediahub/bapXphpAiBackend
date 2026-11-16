<?php
use App\Services\AgentConversationService;
$turn=$detail['turn'];$reviews=(array)($turn['reviews']??[]);
$pretty=static fn(mixed $value):string=>json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
?>
<section class="agent-review" aria-labelledby="answer-review-title">
    <a class="agent-review__back" href="/admin/agent/monitoring">← Agent Monitoring</a>
    <header class="agent-review__intro">
        <span class="eyebrow">INTERNAL QUALITY REVIEW · <?= e($detail['surface']==='admin'?'ENTERPRISE':'SUPPORT') ?></span>
        <h2 id="answer-review-title">Review this answer against its evidence</h2>
        <p>Your decision is recorded separately. It does not change the original answer, automated checks, customer feedback or ticket status.</p>
    </header>
    <form action="/admin/agent/review/<?= e($detail['conversation_id']) ?>" method="get" class="agent-review__turn-picker">
        <label for="review-turn-picker">Conversation turn</label>
        <select id="review-turn-picker" name="turn">
            <?php foreach($detail['turn_choices'] as $choice): ?>
                <option value="<?= e($choice['turn_id']) ?>"<?= $choice['turn_id']===$turn['turn_id']?' selected':'' ?>><?= e($choice['at'].' · '.$choice['preview']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Open turn</button>
    </form>
    <?php if($detail['pending_turns']): ?><p class="agent-review__warning" role="status">This conversation has <?= (int)$detail['pending_turns'] ?> running or interrupted requests. Reviewing a completed answer does not authorize replaying an unfinished action.</p><?php endif; ?>
    <div class="agent-review__layout">
        <div class="agent-review__evidence">
            <section class="admin-card" aria-labelledby="review-recorded-request">
                <h3 id="review-recorded-request">Recorded request</h3><p class="agent-review__text" data-review-question><?= e((string)$turn['question']) ?></p>
                <h3>Recorded answer</h3><div class="agent-review__text" data-review-answer><?= e((string)$turn['answer']) ?></div>
                <dl class="agent-review__metadata"><div><dt>Produced at</dt><dd><?= e((string)($turn['at']??'')) ?></dd></div>
                    <div><dt>Response route</dt><dd><?= e((string)($turn['outcome']??'unknown')) ?></dd></div>
                    <div><dt>Customer state</dt><dd><?= e(str_replace('_',' ',$detail['resolution_status'])) ?></dd></div></dl>
                <p class="agent-review__caption">Unrated is not resolved. A staff label cannot create a customer-confirmed outcome.</p>
            </section>
            <section class="admin-card" aria-labelledby="review-recorded-evidence">
                <h3 id="review-recorded-evidence">Recorded source and execution evidence</h3>
                <?php foreach((array)($turn['retrieval']??[]) as $i=>$retrieval): ?>
                    <details><summary>Retrieval <?= $i+1 ?> · <?= e((string)($retrieval['intent']??'unknown')) ?></summary><pre><?= e($pretty($retrieval)) ?></pre></details>
                <?php endforeach; ?>
                <?php foreach((array)($turn['tools']??[]) as $i=>$tool): ?>
                    <details><summary>Tool <?= $i+1 ?> · <?= e((string)($tool['name']??'unknown')) ?> · <?= e((string)($tool['status']??'unknown')) ?></summary><pre><?= e($pretty($tool)) ?></pre></details>
                <?php endforeach; ?>
                <?php if(empty($turn['retrieval'])&&empty($turn['tools'])): ?><p>No tool or retrieval evidence was recorded for this answer. Do not certify factual support from the reply alone.</p><?php endif; ?>
                <details><summary>Automated guardrails — not human accuracy</summary><pre><?= e($pretty($turn['evaluation']??[])) ?></pre></details>
                <?php if(!empty($turn['trace']['instruction_manifest'])): ?><details><summary>Runtime instruction manifest</summary><pre><?= e($pretty($turn['trace']['instruction_manifest'])) ?></pre></details><?php endif; ?>
                <p class="agent-review__caption">Evidence snapshot <code><?= e($detail['evidence_sha256']) ?></code>. Changed evidence or review revision requires a reload before saving.</p>
            </section>
        </div>
        <form class="admin-card agent-review__form" action="/admin/agent/review/<?= e($detail['conversation_id']) ?>" method="post" data-answer-review-form>
            <h3>Record your assessment</h3>
            <input type="hidden" name="_csrf" value="<?= e((string)($_SESSION['csrf_token']??'')) ?>">
            <input type="hidden" name="turn_id" value="<?= e($turn['turn_id']) ?>">
            <input type="hidden" name="review_revision" value="<?= (int)$detail['review_revision'] ?>">
            <input type="hidden" name="evidence_sha256" value="<?= e($detail['evidence_sha256']) ?>">
            <p>Review revision <?= (int)$detail['review_revision'] ?>. Choose <strong>Not assessed</strong> when evidence is insufficient. Factual/source passes require selected retrieval or tool evidence.</p>
            <?php foreach(AgentConversationService::REVIEW_CRITERIA as $key=>$label): ?>
                <label class="agent-review__field" for="review-<?= e($key) ?>"><?= e($label) ?>
                    <select id="review-<?= e($key) ?>" name="rubric[<?= e($key) ?>]" required>
                        <option value="not_assessed">Not assessed / evidence insufficient</option><option value="pass">Pass — checked against evidence</option><option value="fail">Fail — correction needed</option>
                    </select>
                </label>
            <?php endforeach; ?>
            <fieldset><legend>Evidence used</legend><p>Select only what you actually checked.</p>
                <?php foreach($detail['evidence_options'] as $key=>$label): ?><label class="agent-review__check"><input type="checkbox" name="evidence_refs[]" value="<?= e($key) ?>"> <span><?= e($label) ?></span></label><?php endforeach; ?>
            </fieldset>
            <fieldset><legend>Problem or missing-evidence reasons</legend>
                <?php foreach(AgentConversationService::REVIEW_REASONS as $key=>$label): ?><label class="agent-review__check"><input type="checkbox" name="reasons[]" value="<?= e($key) ?>"> <span><?= e($label) ?></span></label><?php endforeach; ?>
            </fieldset>
            <label class="agent-review__field" for="review-note">Decision rationale <span class="agent-review__caption">15–1,500 characters; do not include passwords or private customer data.</span>
                <textarea id="review-note" name="note" minlength="15" maxlength="1500" rows="5" required></textarea>
            </label>
            <label class="agent-review__field" for="review-mode">How was this assessment authored?
                <select id="review-mode" name="authoring_mode" required><option value="">Choose provenance</option>
                    <option value="human">Personally checked by a staff member</option><option value="agent_assisted">AI-assisted / not an independent human judgment</option><option value="verification_test">Synthetic verification test — exclude from human quality rate</option>
                </select>
            </label>
            <label class="agent-review__check"><input type="checkbox" name="human_attestation" value="yes"> <span>I personally compared this answer with its sources. Required only for the staff-authored human option; never select for an automated test.</span></label>
            <button type="submit" class="btn btn-primary">Save answer review</button>
            <p class="agent-review__caption">This appends to the original private chat JSON. It does not publish, retrain, alter feedback or close a ticket.</p>
        </form>
    </div>
    <section class="admin-card" aria-labelledby="answer-review-history">
        <h3 id="answer-review-history">Decision history</h3>
        <?php if($reviews===[]): ?><p>No staff assessment is recorded for this answer.</p><?php else: ?>
        <ol class="agent-review__history">
        <?php foreach(array_reverse($reviews) as $review): ?>
            <li data-review-history-entry><strong>Revision <?= (int)$review['revision'] ?> · <?= e(str_replace('_',' ',(string)$review['verdict'])) ?></strong>
                <p><?= e((string)$review['at']) ?> · <?= e(str_replace('_',' ',(string)$review['authoring_mode'])) ?> · reviewer <?= e((string)($review['reviewer']['id']??'')) ?></p>
                <p class="agent-review__text"><?= e((string)$review['note']) ?></p>
                <details><summary>Assessment and evidence references</summary><pre><?= e($pretty(array_intersect_key($review,array_flip(['rubric','reasons','evidence_refs','evidence_sha256'])))) ?></pre></details>
            </li>
        <?php endforeach; ?></ol>
        <?php endif; ?>
    </section>
</section>
