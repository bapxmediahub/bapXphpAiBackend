<?php $report=$channelReport??null; ?>
<section class="merchant-workspace" aria-labelledby="merchant-title">
    <header class="merchant-workspace__intro"><span class="eyebrow">SALES CHANNELS · YOUR EXISTING CATALOGUE</span>
        <h2 id="merchant-title">Google &amp; YouTube</h2>
        <p>Prepare the product data already on your website. Google account approval and YouTube channel access are separate steps—not automatic results of generating a feed.</p>
    </header>
    <div class="merchant-steps" aria-label="Channel connection stages">
        <div><strong>1 · Website catalogue</strong><span><?= $report!==null?'Feed available; review data below':'Preflight unavailable' ?></span></div>
        <div><strong>2 · Google Merchant Center</strong><span>Account, fetch and approval not verified</span></div>
        <div><strong>3 · YouTube Shopping</strong><span>Eligibility and store linking required</span></div>
    </div>
    <?php if(empty($shopEnabled)): ?><div class="admin-card"><p role="status">The shop module is disabled. No public product feed is served.</p><a href="/admin/settings">Review site settings</a></div>
    <?php elseif($report===null): ?><div class="admin-card"><p role="status">Catalogue preflight is temporarily unavailable. This is not an empty catalogue or a successful Google sync.</p><a href="/admin/google-merchant">Retry preflight</a></div>
    <?php else: ?>
    <section class="admin-card" aria-labelledby="merchant-feed-title">
        <h3 id="merchant-feed-title">Your product data source</h3>
        <p>Use this URL when adding a product file with scheduled fetch in Merchant Center. It refreshes from the existing product records whenever fetched.</p>
        <label class="merchant-feed-url" for="merchant-feed-url">Feed URL<input id="merchant-feed-url" readonly value="<?= e($report['feed_url']) ?>" spellcheck="false"></label>
        <div class="merchant-actions"><a class="btn btn-primary" data-merchant-feed href="/feeds/google-products.xml" target="_blank" rel="noopener">Open XML feed</a><a class="btn btn-outline" href="https://merchants.google.com/" target="_blank" rel="noopener noreferrer">Open Merchant Center ↗</a><a class="btn btn-ghost" href="/admin/google-merchant">Refresh checks</a></div>
        <div class="admin-stats merchant-counts">
            <?php foreach(['record_count'=>'Catalogue records','exported_count'=>'Core fields exportable','excluded_count'=>'Excluded records','warning_count'=>'Records needing review'] as $key=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value" data-merchant-count="<?= e($key) ?>"><?= (int)$report[$key] ?></div><div class="admin-stat__label"><?= e($label) ?></div></div>
            <?php endforeach; ?>
        </div>
        <p><strong>Exportable does not mean approved.</strong> This screen has not accessed your Google account. Review brand/identifiers, condition, image suitability, country/language, shipping and returns before submitting. It never invents a GTIN or claims unknown products have no identifiers.</p>
    </section>
    <section class="admin-card" aria-labelledby="merchant-api-preview-title">
        <h3 id="merchant-api-preview-title">Merchant API compatibility preview</h3>
        <p>Download the same valid catalogue as typed Merchant API v1 product input records. Use the actual language and feed label from your Google data source. This is a local preview, not an upload or a connected account.</p>
        <form action="/admin/google-merchant" method="get" class="admin-form" aria-label="Merchant API product input preview">
            <input type="hidden" name="format" value="product-inputs">
            <div class="admin-form__row">
                <label>Content language (two letters)
                    <input name="language" required pattern="[a-z]{2}" maxlength="2" placeholder="en" autocomplete="off">
                </label>
                <label>Merchant feed label
                    <input name="feed_label" required pattern="[A-Z0-9_\-]{1,20}" maxlength="20" placeholder="IN" autocomplete="off">
                </label>
            </div>
            <button type="submit" class="btn btn-outline">Download product input JSON</button>
        </form>
        <p>Developer diagnostics can read the selected Merchant data source and processed YouTube Shopping item statuses with separately authorized Merchant API access. They cannot create a store link.</p>
    </section>
    <section class="admin-card" aria-labelledby="merchant-records-title"><h3 id="merchant-records-title">Product preflight</h3>
        <p>The first 100 records are shown below. The feed contains all valid public records, including accurate out-of-stock items. Hidden, draft and invalid records are excluded.</p>
        <?php if($report['rows']===[]): ?><p>No catalogue records found.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Current price</th><th>Feed state</th><th>Checks to address</th><th>Actions</th></tr></thead><tbody>
        <?php foreach(array_slice($report['rows'],0,100) as $row): ?><tr>
            <td><?= e($row['title']?:'Unnamed product') ?></td><td><?= e($row['price']) ?></td>
            <td><?= $row['state']==='exported'?'Exported':'Excluded' ?><br><small><?= e(str_replace('_',' ',$row['availability'])) ?></small></td>
            <td><?php foreach(array_merge($row['errors'],$row['warnings']) as $reason): ?><div><?= e(ucfirst(str_replace('_',' ',$reason))) ?></div><?php endforeach; ?><?php if($row['errors']===[]&&$row['warnings']===[]): ?>Basic fields pass; Google review still required.<?php endif; ?></td>
            <td><a href="/admin/products?edit=<?= e(rawurlencode($row['id'])) ?>">Edit product</a><?php if($row['state']==='exported'): ?><br><a href="<?= e($row['link']) ?>" target="_blank" rel="noopener">View listing</a><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="admin-card merchant-setup" aria-labelledby="merchant-setup-title"><h3 id="merchant-setup-title">Complete the Google setup</h3>
        <ol><li>Use the business's Merchant Center account. Verify and claim the website domain; confirm selling country and data-source language.</li>
            <li>Add the XML URL as a product file with scheduled fetch. Inspect Google's processing results—not just this site's feed counts.</li>
            <li>Set accurate shipping, returns and business information. Confirm genuine brand, GTIN/MPN or a legitimate identifier exception; review image policy and current size requirements.</li>
            <li>Fix Merchant Center item issues and confirm eligible destinations. A successful fetch or API insertion is not an approval.</li></ol>
        <p>For faster programmatic inventory updates, use the current <strong>Merchant API</strong> after authorized account setup—not the retired Content API. The website supplies a scheduled-fetch file and a typed JSON preview; automatic API synchronization is not enabled.</p>
        <a href="https://support.google.com/merchants/answer/12631822" target="_blank" rel="noopener noreferrer">Google product-file setup ↗</a>
    </section>
    <section class="admin-card" aria-labelledby="youtube-readiness-title"><h3 id="youtube-readiness-title">Then connect YouTube Shopping</h3>
        <p>Google documents direct Merchant Center onboarding for <strong>eligible, invited creators</strong>, including Analytics and channel/data-source linking. Standard YouTube Studio store connection otherwise depends on a supported platform or retailer. A custom PHP feed does not bypass these requirements.</p>
        <p>If your business runs several podcast or video channels, confirm each channel's own Shopping access and authorized Google account. The same merchant catalogue can serve multiple channels where Google has enabled that relationship; no channel names or IDs are assumed.</p>
        <p>WooCommerce's integration uses an authorized partner connection and separate store-link receipt. This native store does not imitate that identity or mark a channel connected without Google evidence.</p>
        <p>Confirm the channel's Shopping access and direct-onboarding eligibility with YouTube before promising product tagging. This page does not set the YouTube destination or claim that your channel is linked.</p>
        <div class="merchant-actions"><a href="https://support.google.com/merchants/answer/12375531" target="_blank" rel="noopener noreferrer">Direct creator onboarding ↗</a><a href="https://studio.youtube.com/" target="_blank" rel="noopener noreferrer">YouTube Studio ↗</a><a href="https://support.google.com/youtube/answer/12258186" target="_blank" rel="noopener noreferrer">Supported store connections ↗</a></div>
    </section>
    <section class="admin-card"><h3>Analytics and Search Console are separate</h3><p>The current Google integration places tracking and verification tags. Those IDs do not establish reporting OAuth, Google Merchant approval or YouTube access. Use <a href="/admin/integrations">Integrations</a> for the existing tag configuration; do not treat it as a completed Site Kit reporting connection.</p></section>
</section>
