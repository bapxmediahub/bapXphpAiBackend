<div class="admin-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg)">
    <h2 style="margin:0"><?= e($title ?? 'Blog') ?></h2>
    <button class="btn btn-primary" type="button" onclick="toggleForm()">+ New Post</button>
</div>

<?php if(!empty($recoveredDraft['_save_failed'])): ?>
<section class="admin-card" role="alert" data-blog-save-conflict>
    <h3>Your edit was not saved</h3><p>Your submitted text is still in the editor below. Download it before reloading; there is no automatic overwrite of a newer article.</p>
    <button type="button" class="btn btn-outline" onclick="downloadBlogDraft()">Download unsaved Markdown</button>
    <?php $reloadSlug=(string)($recoveredDraft['original_slug']??$recoveredDraft['slug']??''); if($reloadSlug!==''): ?><a class="btn btn-ghost" href="/admin/blog?reload=<?= e(rawurlencode($reloadSlug)) ?>">Reload current article</a><?php endif; ?>
</section>
<?php endif; ?>
<form method="post" action="/admin/blog/save" id="blog-form" class="admin-blog-editor"
      data-draft-owner="<?= e($blogDraftOwner ?? '') ?>" data-saved-draft="<?= e($savedDraftToken ?? '') ?>" hidden>
    <section id="be-recovery" class="be-recovery" aria-label="Browser draft recovery" hidden>
        <p id="be-recovery-message"></p>
        <div class="be-recovery__actions">
        <button class="btn btn-outline" type="button" id="be-restore">Restore draft</button>
        <button class="btn btn-ghost" type="button" id="be-discard">Discard browser copy</button>
        </div>
    </section>
    <p id="be-draft-status" class="be-hint" role="status"></p>
    <input type="hidden" name="browser_draft_token" id="be-draft-token">
    <div id="be-edit-fields">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="slug" id="edit-slug" value="">
    <input type="hidden" name="expected_revision" id="edit-revision" value="new">
    <input type="hidden" name="original_slug" id="edit-original-slug" value="">
    <input type="hidden" name="type" id="edit-type" value="blog">
    <label>Publication
        <select name="published" id="edit-published"><option value="0">Draft — not public</option><option value="1">Publish when the date is due</option></select>
    </label>
    <div class="admin-blog-editor__grid">
        <div class="be-title-field">
            <div class="be-title-field__label">
                <label for="edit-title">Title</label>
                <button type="button" class="btn btn-sm btn-outline" id="enhance-title">Enhance title</button>
            </div>
            <input type="text" name="title" id="edit-title" required>
        </div>
        <label>Category
            <select name="category" id="edit-category" style="width:100%">
                <option value="">Uncategorized</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['slug'] ?? '') ?>"><?= e($cat['name'] ?? $cat['slug'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <details class="be-settings">
        <summary>Post settings <span>slug, image, excerpt, SEO — date controls scheduling in Asia/Kolkata</span></summary>
        <div class="admin-blog-editor__grid">
        <label>Slug <input type="text" id="edit-slug-display" placeholder="auto-from-title" style="width:100%"></label>
        <label>Article template
            <select name="template" id="edit-template" style="width:100%">
                <option value="editorial">Editorial story</option>
                <option value="product">Product guide</option>
                <option value="tool">Tool or feature guide</option>
                <option value="help">Customer task guide</option>
            </select>
        </label>
        <label class="admin-blog-editor__wide">Thumbnail and article image <input type="text" name="og_image" id="edit-image" placeholder="/assets/images/blog/article.webp" style="width:100%"><small>Use the same cropped 16:9 image on the blog card and article page.</small></label>
        <label class="admin-blog-editor__wide">Image description <input type="text" name="image_alt" id="edit-image-alt" placeholder="Describe what is visible in the screenshot" style="width:100%"></label>
        <label class="admin-blog-editor__wide">Source page URL <input type="url" name="source_url" id="edit-source-url" placeholder="https://example.com/login" style="width:100%"><small>For UI documentation, link the exact page represented by the screenshot.</small></label>
        <label>Summary <textarea name="summary" id="edit-summary" rows="2" style="width:100%"></textarea></label>
        <label>Display order <input type="number" name="order" id="edit-order" min="0" step="1" style="width:100%"></label>
        <label class="admin-blog-editor__wide">Excerpt <textarea name="excerpt" id="edit-excerpt" rows="2" style="width:100%"></textarea></label>
        <label class="admin-blog-editor__wide">SEO Keywords <input type="text" name="keywords" id="edit-keywords" placeholder="rudraksha, pooja items, temple guidance" style="width:100%"><small>Comma-separated keywords for search engine indexing.</small></label>
        <?php // Both are filled in on save from today's date and the signed-in admin.
              // They stay here for backdating a post or crediting someone else. ?>
        <label>Published At <input type="date" name="published_at" id="edit-date" style="width:100%"><small>Leave blank for today. Future dates become public at midnight Asia/Kolkata; no automatic newsletter is scheduled.</small></label>
        <label>Author <input type="text" name="author" id="edit-author" placeholder="<?= e($currentAuthor ?? 'Admin') ?>" style="width:100%"><small>Leave blank to credit <?= e($currentAuthor ?? 'Admin') ?>.</small></label>
        </div>
    </details>

    <div class="admin-blog-editor__grid">
        <div class="admin-blog-editor__wide">
            <div class="be-tabs" role="group" aria-label="Article editing mode">
                <button type="button" class="be-tab is-active" data-mode="document" aria-pressed="true">Edit as document</button>
                <button type="button" class="be-tab" data-mode="markdown" aria-pressed="false">Edit Markdown</button>
                <button type="button" class="be-tab" data-mode="preview" aria-pressed="false">Preview</button>
                <button type="button" class="btn btn-sm btn-outline be-enhance" id="enhance-content">Enhance content</button>
            </div>

            <div class="be-toolbar" id="be-toolbar">
                <select id="be-block" title="Text style">
                    <option value="p">Paragraph</option>
                    <option value="h1">Heading 1</option>
                    <option value="h2">Heading 2</option>
                    <option value="h3">Heading 3</option>
                </select>
                <span class="be-sep"></span>
                <button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>
                <button type="button" data-cmd="italic" title="Italic"><em>I</em></button>
                <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">&bull; List</button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                <button type="button" data-cmd="formatBlock" data-arg="blockquote" title="Quote">&ldquo; Quote</button>
                <button type="button" data-cmd="formatBlock" data-arg="pre" title="Code">Code</button>
                <span class="be-sep"></span>
                <button type="button" id="be-link" title="Link">Link</button>
                <button type="button" id="be-image" title="Image">Image</button>
                <button type="button" id="be-table" title="Table">Table</button>
            </div>

            <!-- Document mode. Edits HTML for familiarity, but only Markdown is ever saved. -->
            <div id="be-document" class="be-surface" contenteditable="true" role="textbox" aria-label="Article content" aria-multiline="true"></div>

            <!-- Markdown mode. This is the exact text written to content/blog/posts/{slug}.md -->
            <textarea name="content" id="edit-content" aria-label="Article Markdown" class="be-surface be-code" rows="18" hidden></textarea>

            <!-- Preview mode. Same renderer and article styles as the public page. -->
            <div id="be-preview" class="be-surface be-preview blog-post__body" hidden></div>

            <p class="be-hint">Saved as Markdown in <code>content/blog/posts/</code>. Switching modes keeps your content.</p>
        </div>

        <div class="admin-blog-editor__wide be-media">
            <strong>Article image</strong>
            <div class="be-media__row">
                <input type="file" id="be-upload" accept="image/png,image/jpeg,image/webp,image/gif">
                <button type="button" class="btn btn-sm btn-outline" id="be-pick">Choose from uploads</button>
            </div>
            <div class="be-media__grid" id="be-library" hidden>
                <?php foreach (($mediaFiles ?? []) as $__m): $__p = $__m['url'] ?? $__m['path'] ?? ''; if (!$__p) continue; ?>
                    <button type="button" class="be-media__item" data-path="<?= e($__p) ?>" title="<?= e(basename($__p)) ?>">
                        <img src="<?= e($__p) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
                <?php if (empty($mediaFiles)): ?><p class="be-hint">No uploads yet. Use the file picker above.</p><?php endif; ?>
            </div>
        </div>

        <label class="admin-blog-editor__wide" style="display:flex;align-items:center;gap:8px;font-weight:600">
            <input type="checkbox" name="notify_subscribers" value="1" style="width:16px;height:16px;margin:0">
            Email this article to registered customers
            <small style="font-weight:400;color:var(--color-text-muted)">Only on a new post, never on an edit.</small>
        </label>
        <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap">
            <button class="btn btn-primary" type="submit">Save</button>
            <button class="btn btn-outline" type="button" onclick="downloadBlogDraft()">Download unsaved Markdown</button>
            <button class="btn btn-outline" type="button" onclick="closeBlogForm()">Close editor</button>
        </div>
    </div>
    </div>
</form>

<div class="table-wrap admin-blog-list" role="region" aria-label="Blog posts" tabindex="0">
<table style="width:100%;background:var(--color-white);border-radius:var(--radius-md);border:1px solid var(--color-border)">
    <thead><tr><th>Title</th><th>Category</th><th>Published</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $post): ?>
    <tr>
        <td><a href="/blog/<?= e($post['slug'] ?? '') ?>" target="_blank"><?= e($post['title'] ?? 'Untitled') ?></a></td>
        <td><?= e($post['category'] ?? '') ?></td>
        <td><?= e(ucfirst(str_replace('_',' ',(string)($post['_publication_state']??'hidden')))) ?></td>
        <td><?= e($post['published_at'] ?? '') ?></td>
        <td>
            <button class="btn btn-sm btn-ghost" onclick="editPost(<?= e(json_encode($post)) ?>)">Edit</button>
            <?php $__live = !empty($post['published']); ?>
            <form method="post" action="/admin/blog/toggle" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="slug" value="<?= e($post['slug'] ?? '') ?>">
                <input type="hidden" name="expected_revision" value="<?= e($post['_revision'] ?? '') ?>">
                <button class="btn btn-sm btn-ghost" title="<?= $__live ? 'Hide from the site' : 'Publish to the site' ?>"
                        aria-label="<?= $__live ? 'Hide post' : 'Publish post' ?>">
                    <?php if ($__live): ?>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?php else: ?>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    <?php endif; ?>
                </button>
            </form>
            <form method="post" action="/admin/blog/delete" style="display:inline" onsubmit="return confirm('Delete this post?')">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="slug" value="<?= e($post['slug'] ?? '') ?>">
                <input type="hidden" name="expected_revision" value="<?= e($post['_revision'] ?? '') ?>">
                <button class="btn btn-sm btn-ghost" style="color:var(--color-error)">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
function toggleForm(){var f=document.getElementById('blog-form');if(!canLeaveBlogForm())return;f.reset();if(window.beLoadMarkdown)window.beLoadMarkdown('');document.getElementById('edit-slug-display').readOnly=false;document.getElementById('edit-revision').value='new';document.getElementById('edit-original-slug').value='';f.hidden=false;document.getElementById('edit-title').focus();f.dispatchEvent(new Event('blog:open'));}
function editPost(post){if(!canLeaveBlogForm())return;document.getElementById('edit-published').value=(post.published===true||post.published===1||post.published==='1'||post.published==='true')?'1':'0';document.getElementById('edit-revision').value=post.expected_revision||post._revision||'new';document.getElementById('edit-original-slug').value=post.original_slug||(post._revision?post.slug:'')||'';document.getElementById('edit-slug-display').readOnly=!!document.getElementById('edit-original-slug').value;document.getElementById('edit-slug').value=post.slug||'';document.getElementById('edit-slug-display').value=post.slug||'';document.getElementById('edit-type').value=post.type||'blog';document.getElementById('edit-title').value=post.title||'';document.getElementById('edit-category').value=post.category||'';document.getElementById('edit-template').value=post.template||'editorial';document.getElementById('edit-image').value=post.og_image||post.image||'';document.getElementById('edit-image-alt').value=post.image_alt||'';document.getElementById('edit-source-url').value=post.source_url||'';document.getElementById('edit-summary').value=post.summary||post.excerpt||'';document.getElementById('edit-order').value=post.order||'';document.getElementById('edit-excerpt').value=post.excerpt||'';document.getElementById('edit-keywords').value=post.keywords||'';document.getElementById('edit-date').value=post.published_at||'';document.getElementById('edit-author').value=post.author||'Admin';document.getElementById('edit-content').value=post.content||'';if(window.beLoadMarkdown)window.beLoadMarkdown(post.content||'');document.getElementById('blog-form').hidden=false;document.getElementById('blog-form').scrollIntoView({behavior:'smooth',block:'start'});document.getElementById('edit-title').focus();document.getElementById('blog-form').dispatchEvent(new Event('blog:open'));}
document.getElementById('edit-title').addEventListener('input',function(){if(document.getElementById('edit-original-slug').value)return;var slug=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('edit-slug').value=slug;document.getElementById('edit-slug-display').value=slug;});
function previewArticle(){var f=document.getElementById('blog-form');var fd=new FormData(f);fetch('/admin/blog/preview',{method:'POST',body:fd}).then(function(r){return r.text();}).then(function(html){var w=window.open('','_blank');w.document.write(html);w.document.close();});}
function generateDraft(){var f=document.getElementById('blog-form');var fd=new FormData(f);fd.set('content','');fetch('/admin/blog/ai-draft',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.content)document.getElementById('edit-content').value=d.content;});}
</script>

<style>
.be-settings{margin:0 0 14px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-white)}
.be-settings>summary{padding:10px 14px;cursor:pointer;font-weight:600;font-size:.88rem;list-style:none;display:flex;align-items:center;gap:8px}
.be-settings>summary::-webkit-details-marker{display:none}
.be-settings>summary::before{content:'▸';color:var(--color-text-muted);transition:transform .15s}
.be-settings[open]>summary::before{transform:rotate(90deg)}
.be-settings>summary span{font-weight:400;font-size:.78rem;color:var(--color-text-muted)}
.be-settings .admin-blog-editor__grid{padding:0 14px 14px}
.be-recovery{padding:12px 16px;margin-bottom:12px;border:1px solid var(--color-border);border-inline-start:4px solid var(--color-gold);border-radius:8px;background:var(--color-white)}
.be-recovery p{margin:0 0 8px}
.be-recovery__actions,.be-title-field__label{display:flex;align-items:center;flex-wrap:wrap;gap:8px}
.be-title-field__label{justify-content:space-between;margin-bottom:4px}
#blog-form{scroll-margin-block-start:150px}
#blog-form input:not([type=hidden],[type=checkbox],[type=file]),#blog-form select:not(#be-block){display:block;box-sizing:border-box;width:100%;min-height:44px;margin-top:4px;padding:8px 10px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-white);font:inherit}
#blog-form :focus-visible{outline:2px solid var(--color-maroon);outline-offset:2px}
@media(max-width:600px){.be-settings>summary{align-items:baseline;flex-wrap:wrap}.be-settings>summary span{flex-basis:100%;padding-inline-start:14px}}
#blog-form [hidden]{display:none!important}
.be-tabs button,.be-toolbar button,.be-toolbar select,.be-recovery button,#blog-form #enhance-title{min-height:44px}
.be-tabs{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
.be-tab{padding:7px 14px;border:1px solid var(--color-border);background:var(--color-white);border-radius:999px;font-size:.82rem;cursor:pointer;font-family:inherit;color:var(--color-text-muted)}
.be-tab.is-active{background:var(--color-maroon);color:var(--color-gold);border-color:var(--color-gold)}
.be-enhance{margin-left:auto}
.be-toolbar{display:flex;gap:4px;align-items:center;flex-wrap:wrap;padding:6px;border:1px solid var(--color-border);border-bottom:0;border-radius:8px 8px 0 0;background:var(--color-bg-alt)}
.be-toolbar button{padding:5px 9px;border:1px solid transparent;background:transparent;border-radius:6px;cursor:pointer;font-size:.8rem;font-family:inherit;color:var(--color-ink)}
.be-toolbar button:hover{background:var(--color-white);border-color:var(--color-border)}
.be-toolbar select{padding:5px 8px;border:1px solid var(--color-border);border-radius:6px;font-size:.8rem;font-family:inherit;background:var(--color-white)}
.be-sep{width:1px;height:20px;background:var(--color-border);margin:0 4px}
.be-surface{width:100%;min-height:340px;padding:16px 18px;border:1px solid var(--color-border);border-radius:0 0 8px 8px;background:var(--color-white);font-size:.94rem;line-height:1.7;overflow-y:auto;box-sizing:border-box}
.be-surface:focus{outline:2px solid var(--color-gold);outline-offset:-2px}
.be-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.85rem;white-space:pre;resize:vertical}
.be-preview{background:var(--color-bg-alt)}
#be-document h1,#be-preview h1{font-size:1.6rem;margin:.6em 0 .3em}
#be-document h2,#be-preview h2{font-size:1.3rem;margin:.6em 0 .3em}
#be-document h3,#be-preview h3{font-size:1.1rem;margin:.6em 0 .3em}
#be-document blockquote,#be-preview blockquote{border-left:3px solid var(--color-gold);margin:.6em 0;padding-left:14px;color:var(--color-text-muted)}
#be-document pre,#be-preview pre{background:var(--color-bg-alt);padding:10px 12px;border-radius:6px;overflow-x:auto;font-size:.85rem}
#be-document table,#be-preview table{border-collapse:collapse;margin:.6em 0}
#be-document th,#be-document td,#be-preview th,#be-preview td{border:1px solid var(--color-border);padding:6px 10px}
#be-document img,#be-preview img{max-width:100%;height:auto;border-radius:6px}
.be-hint{font-size:.75rem;color:var(--color-text-muted);margin:6px 0 0}
.be-media{margin-top:14px;padding:12px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-white)}
.be-media__row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}
.be-media__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px;margin-top:10px;max-height:230px;overflow-y:auto}
.be-media__item{padding:0;border:2px solid transparent;border-radius:6px;background:none;cursor:pointer;overflow:hidden}
.be-media__item img{width:100%;height:78px;object-fit:cover;display:block}
.be-media__item:hover{border-color:var(--color-gold)}
</style>
<script>
(function () {
    var doc = document.getElementById('be-document');
    var md = document.getElementById('edit-content');
    var pv = document.getElementById('be-preview');
    var toolbar = document.getElementById('be-toolbar');
    if (!doc || !md) return;
    var form = document.getElementById('blog-form');
    var editorGeneration = 0;
    form.addEventListener('blog:open', function(){ editorGeneration++; });
    function editorIsCurrent(generation) { return generation === editorGeneration && !form.hidden; }
    var mode = 'document';
    var documentDirty = false;
    doc.addEventListener('input', function(){ documentDirty = true; });

    // ---- Markdown <-> HTML -------------------------------------------------
    // Markdown is the source of truth: it is what gets saved. Document mode is a
    // view over it, so every switch converts rather than keeping a second copy.
    function esc(t){return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

    function escAttr(text) { return esc(text).replace(/"/g, '&quot;'); }
    function safeEditorUrl(value) {
        try { return ['http:', 'https:'].includes(new URL(value, location.href).protocol); }
        catch { return false; }
    }

    function mdToHtml(src) {
        var lines = String(src).replace(/\r\n/g,'\n').split('\n');
        var out = [], i = 0;
        function inline(text, allowLinks = true) {
            var tokens = /(!?)\[([^\]]*)\]\(([^)\s]+)\)|\*\*([^*]+)\*\*|\*([^*\n]+)\*|`([^`]+)`/g;
            var output = '', offset = 0;
            for (var match of text.matchAll(tokens)) {
                output += esc(text.slice(offset, match.index));
                if (match[3] !== undefined && allowLinks && safeEditorUrl(match[3])) {
                    var url = escAttr(match[3]);
                    output += match[1] ? '<img src="' + url + '" alt="' + escAttr(match[2]) + '">'
                        : '<a href="' + url + '">' + inline(match[2], false) + '</a>';
                } else if (match[4] !== undefined) { output += '<strong>' + inline(match[4], allowLinks) + '</strong>'; }
                else if (match[5] !== undefined) { output += '<em>' + inline(match[5], allowLinks) + '</em>'; }
                else if (match[6] !== undefined) { output += '<code>' + esc(match[6]) + '</code>'; }
                else { output += esc(match[0]); }
                offset = match.index + match[0].length;
            }
            return output + esc(text.slice(offset));
        }
        while (i < lines.length) {
            var l = lines[i];
            if (/^```/.test(l)) { var buf=[]; i++; while(i<lines.length && !/^```/.test(lines[i])) buf.push(lines[i++]); i++; out.push('<pre>'+esc(buf.join('\n'))+'</pre>'); continue; }
            var h = l.match(/^(#{1,3})\s+(.*)$/);
            if (h) { var n=h[1].length; out.push('<h'+n+'>'+inline(h[2])+'</h'+n+'>'); i++; continue; }
            if (/^>\s?/.test(l)) { var q=[]; while(i<lines.length && /^>\s?/.test(lines[i])) q.push(lines[i++].replace(/^>\s?/,'')); out.push('<blockquote>'+inline(q.join(' '))+'</blockquote>'); continue; }
            if (/^\s*[-*]\s+/.test(l)) { var ul=[]; while(i<lines.length && /^\s*[-*]\s+/.test(lines[i])) ul.push('<li>'+inline(lines[i++].replace(/^\s*[-*]\s+/,''))+'</li>'); out.push('<ul>'+ul.join('')+'</ul>'); continue; }
            if (/^\s*\d+\.\s+/.test(l)) { var ol=[]; while(i<lines.length && /^\s*\d+\.\s+/.test(lines[i])) ol.push('<li>'+inline(lines[i++].replace(/^\s*\d+\.\s+/,''))+'</li>'); out.push('<ol>'+ol.join('')+'</ol>'); continue; }
            if (l.indexOf('|')>-1 && i+1<lines.length && /^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/.test(lines[i+1].trim())) {
                var cells=function(r){return r.trim().replace(/^\||\|$/g,'').split('|').map(function(c){return c.trim();});};
                var head=cells(l); i+=2; var rows=[];
                while(i<lines.length && lines[i].trim()!=='' && lines[i].indexOf('|')>-1) rows.push(cells(lines[i++]));
                var t='<table><thead><tr>'+head.map(function(c){return '<th>'+inline(c)+'</th>';}).join('')+'</tr></thead><tbody>';
                rows.forEach(function(r){ t+='<tr>'+head.map(function(_,x){return '<td>'+inline(r[x]||'')+'</td>';}).join('')+'</tr>'; });
                out.push(t+'</tbody></table>'); continue;
            }
            if (l.trim()==='') { i++; continue; }
            var para=[]; while(i<lines.length && lines[i].trim()!=='' && !/^(#{1,3}\s|>|\s*[-*]\s|\s*\d+\.\s|```)/.test(lines[i])) para.push(lines[i++]);
            out.push('<p>'+inline(para.join(' '))+'</p>');
        }
        return out.join('\n');
    }

    function htmlToMd(root) {
        function inline(node) {
            var s='';
            node.childNodes.forEach(function(c){
                if (c.nodeType===3) { s+=c.nodeValue; return; }
                var tag=c.nodeName.toLowerCase();
                if (tag==='strong'||tag==='b') s+='**'+inline(c)+'**';
                else if (tag==='em'||tag==='i') s+='*'+inline(c)+'*';
                else if (tag==='code') s+='`'+c.textContent+'`';
                else if (tag==='a') s+='['+inline(c)+']('+(c.getAttribute('href')||'')+')';
                else if (tag==='img') s+='!['+(c.getAttribute('alt')||'')+']('+(c.getAttribute('src')||'')+')';
                else if (tag==='br') s+='\n';
                else s+=inline(c);
            });
            return s;
        }
        // Renders one node, rather than its children. inline() walks childNodes, so a
        // node that carries everything in its attributes and has no children — an <img>
        // is the case that bit us — converted to an empty string and vanished on the
        // next mode switch. That is why an image picked from the media library
        // disappeared as soon as Preview was opened.
        function one(node) {
            if (node.nodeType===3) return node.nodeValue;
            var t = node.nodeName.toLowerCase();
            if (t==='img') return '!['+(node.getAttribute('alt')||'')+']('+(node.getAttribute('src')||'')+')';
            if (t==='br') return '\n';
            var wrap = document.createElement('div');
            wrap.appendChild(node.cloneNode(true));
            return inline(wrap);
        }
        var VOIDISH = /^(img|br|hr|input)$/;

        var out=[];
        root.childNodes.forEach(function(n){
            if (n.nodeType===3) { var t=n.nodeValue.trim(); if(t) out.push(t); return; }
            var tag=n.nodeName.toLowerCase();
            if (VOIDISH.test(tag)) { var v=one(n).trim(); if(v){out.push(v);out.push('');} return; }
            if (/^h[1-3]$/.test(tag)) out.push('#'.repeat(+tag[1])+' '+inline(n).trim());
            else if (tag==='blockquote') out.push('> '+inline(n).trim());
            else if (tag==='pre') out.push('```\n'+n.textContent.replace(/\n$/,'')+'\n```');
            else if (tag==='ul') n.querySelectorAll(':scope > li').forEach(function(li){ out.push('- '+inline(li).trim()); });
            else if (tag==='ol') n.querySelectorAll(':scope > li').forEach(function(li,x){ out.push((x+1)+'. '+inline(li).trim()); });
            else if (tag==='table') {
                var hs=[].map.call(n.querySelectorAll('thead th'), function(th){return inline(th).trim();});
                if (!hs.length) hs=[].map.call(n.querySelectorAll('tr:first-child td,tr:first-child th'), function(td){return inline(td).trim();});
                if (hs.length) {
                    out.push('| '+hs.join(' | ')+' |');
                    out.push('|'+hs.map(function(){return '---';}).join('|')+'|');
                    n.querySelectorAll('tbody tr').forEach(function(tr){
                        out.push('| '+[].map.call(tr.children,function(td){return inline(td).trim();}).join(' | ')+' |');
                    });
                }
            }
            else if (tag==='hr') out.push('---');
            else { var t2=inline(n).trim(); if(t2) out.push(t2); }
            out.push('');
        });
        return out.join('\n').replace(/\n{3,}/g,'\n\n').trim();
    }

    // ---- mode switching ----------------------------------------------------
    function sync(from) {
        if (from === 'document' && documentDirty) {
            md.value = htmlToMd(doc);
            documentDirty = false;
        } else if (from === 'markdown') {
            doc.innerHTML = mdToHtml(md.value);
        }
    }
    function setMode(next) {
        if (next === mode) return;
        sync(mode);                       // never lose content on switch
        mode = next;
        doc.hidden = next !== 'document';
        md.hidden  = next !== 'markdown';
        pv.hidden  = next !== 'preview';
        toolbar.style.display = next === 'document' ? '' : 'none';
        if (next === 'document') doc.innerHTML = mdToHtml(md.value);
        if (next === 'preview') pv.innerHTML = mdToHtml(md.value);
        document.querySelectorAll('.be-tab').forEach(function(t){ var active = t.dataset.mode === next; t.classList.toggle('is-active', active); t.setAttribute('aria-pressed', String(active)); });
    }
    document.querySelectorAll('.be-tab').forEach(function(t){ t.addEventListener('click', function(){ setMode(t.dataset.mode); }); });

    // Markdown is what gets posted, so convert before the form submits.
    document.getElementById('blog-form').addEventListener('submit', function(){ sync(mode); });

    // ---- toolbar -----------------------------------------------------------
    toolbar.addEventListener('click', function(e){
        var b = e.target.closest('button[data-cmd]');
        if (!b) return;
        e.preventDefault(); doc.focus();
        document.execCommand(b.dataset.cmd, false, b.dataset.arg || null);
    });
    document.getElementById('be-block').addEventListener('change', function(){
        doc.focus(); document.execCommand('formatBlock', false, this.value);
    });
    document.getElementById('be-link').addEventListener('click', function(){
        var u = prompt('Link URL'); if (u && safeEditorUrl(u)) { doc.focus(); document.execCommand('createLink', false, u); }
    });
    document.getElementById('be-table').addEventListener('click', function(){
        doc.focus();
        document.execCommand('insertHTML', false,
          '<table><thead><tr><th>Heading</th><th>Heading</th></tr></thead><tbody><tr><td>Cell</td><td>Cell</td></tr></tbody></table><p><br></p>');
    });
    function insertImage(path) {
        if (!safeEditorUrl(path)) return;
        if (mode === 'markdown') { md.value += '\n\n![](' + path + ')\n'; }
        else { setMode('document'); doc.focus(); document.execCommand('insertHTML', false, '<img src="'+escAttr(path)+'" alt="">'); }
        var f = document.getElementById('edit-image');
        if (f && !f.value) f.value = path;
        md.dispatchEvent(new Event('input', {bubbles: true}));
    }
    document.getElementById('be-image').addEventListener('click', function(){
        var lib = document.getElementById('be-library'); if (lib) lib.hidden = false;
        var u = prompt('Image path or URL (or pick one from the library below)');
        if (u) insertImage(u);
    });
    document.getElementById('be-pick').addEventListener('click', function(){
        var lib = document.getElementById('be-library'); lib.hidden = !lib.hidden;
    });
    document.querySelectorAll('.be-media__item').forEach(function(b){
        b.addEventListener('click', function(){ insertImage(b.dataset.path); });
    });
    document.getElementById('be-upload').addEventListener('change', async function(){
        var generation = editorGeneration;
        var file = this.files && this.files[0]; if (!file) return;
        var fd = new FormData();
        fd.append('media_files[]', file);
        fd.append('context', 'blog');
        fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
        try {
            var r = await fetch('/admin/media/upload', {method:'POST', body:fd, credentials:'same-origin'});
            var d = await r.json().catch(function(){return {};});
            if (!editorIsCurrent(generation)) return;
            var path = (d.files && d.files[0] && (d.files[0].url || d.files[0].path)) || d.url || '';
            if (path) { insertImage(path); showToast && showToast('Image uploaded.','success'); }
            else showToast && showToast('Upload finished but no path was returned.','error');
        } catch (err) { showToast && showToast('Upload failed.','error'); }
    });

    // ---- enhance (separate per field) --------------------------------------
    async function enhance(url, payload, btn) {
        var label = btn.textContent; btn.disabled = true; btn.textContent = 'Working…';
        try {
            payload._csrf = document.querySelector('input[name="_csrf"]').value;
            var r = await fetch(url, {method:'POST', body:new URLSearchParams(payload), credentials:'same-origin'});
            var d = await r.json().catch(function(){return {error:'Unreadable response.'};});
            if (d.error) { showToast && showToast(d.error,'error'); return null; }
            return d;
        } catch {
            showToast('Could not reach the enhancement service. Your draft is still in the editor.', 'error');
            return null;
        } finally { btn.disabled = false; btn.textContent = label; }
    }
    document.getElementById('enhance-title').addEventListener('click', async function(){
        var t = document.getElementById('edit-title');
        var generation = editorGeneration, title = t.value;
        var d = await enhance('/admin/blog/enhance-title', {title: title}, this);
        if (editorIsCurrent(generation) && t.value === title && d && d.title) { t.value = d.title; t.dispatchEvent(new Event('input', {bubbles: true})); }
    });
    document.getElementById('enhance-content').addEventListener('click', async function(){
        sync(mode);
        var generation = editorGeneration, content = md.value;
        var d = await enhance('/admin/blog/enhance-content', {content: content}, this);
        if (!editorIsCurrent(generation)) return;
        sync(mode);
        if (md.value === content && d && d.content) {
            md.value = d.content;
            documentDirty = false;
            md.dispatchEvent(new Event('input', {bubbles: true}));
            if (mode === 'document') doc.innerHTML = mdToHtml(md.value);
            if (mode === 'preview') pv.innerHTML = mdToHtml(md.value);
        }
    });

    // Seed document mode when an existing post is loaded into the form.
    window.beLoadMarkdown = function (markdown) {
        documentDirty = false;
        md.value = markdown || '';
        doc.innerHTML = mdToHtml(md.value);
        if (mode === 'preview') pv.innerHTML = doc.innerHTML;
    };
    window.beSyncMarkdown = function () { if (mode === 'document') sync(mode); };
    doc.innerHTML = mdToHtml(md.value);
})();
</script>

<script src="/assets/blog-draft.js?v=<?= filemtime(app_path('assets/blog-draft.js')) ?>"></script>
<script>
document.getElementById('edit-slug-display').addEventListener('input',function(){if(!document.getElementById('edit-original-slug').value)document.getElementById('edit-slug').value=this.value;});
function canLeaveBlogForm() {
    var form = document.getElementById('blog-form');
    return form.dispatchEvent(new Event('blog:close', {cancelable:true}))
        || confirm('The latest edits could not be stored in this browser. Leave the editor and discard them?');
}
function closeBlogForm() {
    if (!canLeaveBlogForm()) return;
    document.getElementById('blog-form').hidden = true;
    document.querySelector('.admin-bar button').focus();
}
function downloadBlogDraft(){window.beSyncMarkdown();window.beDownloadMarkdown(document.getElementById('edit-content').value);}
window.beDownloadMarkdown=function(content){var a=document.createElement('a'),url=URL.createObjectURL(new Blob([content],{type:'text/markdown;charset=utf-8'}));a.href=url;a.download='unsaved-article.md';a.click();setTimeout(function(){URL.revokeObjectURL(url)},1000);};
<?php if(is_array($recoveredDraft??null)): ?>
editPost(<?= json_encode($recoveredDraft,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?>);
<?php endif; ?>
</script>
