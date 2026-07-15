<div class="admin-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg)">
    <h2 style="margin:0"><?= e($title ?? 'Blog') ?></h2>
    <button class="btn btn-primary" type="button" onclick="toggleForm()">+ New Post</button>
</div>

<form method="post" action="/admin/blog/save" id="blog-form" class="admin-blog-editor" hidden>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="slug" id="edit-slug" value="">
    <div class="admin-blog-editor__grid">
        <label>Title <input type="text" name="title" id="edit-title" required style="width:100%"></label>
        <label>Slug <input type="text" name="slug" id="edit-slug-display" placeholder="auto-from-title" style="width:100%"></label>
        <label>Category
            <select name="category" id="edit-category" style="width:100%">
                <option value="">Uncategorized</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['slug'] ?? '') ?>"><?= e($cat['name'] ?? $cat['slug'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
        <label class="admin-blog-editor__wide">SEO Keywords <input type="text" name="keywords" id="edit-keywords" placeholder="astrology, spirituality, vedic astrology" style="width:100%"><small>Comma-separated keywords for search engine indexing.</small></label>
        <label>Published At <input type="date" name="published_at" id="edit-date" style="width:100%"></label>
        <label>Author <input type="text" name="author" id="edit-author" value="Admin" style="width:100%"></label>
        <label class="admin-blog-editor__wide">Content (Markdown) <textarea name="content" id="edit-content" rows="16" style="width:100%;font-family:monospace"></textarea></label>
        <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap">
            <button class="btn btn-primary" type="submit">Save</button>
            <button class="btn btn-outline" type="button" onclick="previewArticle()">Preview</button>
            <button class="btn btn-outline" type="button" onclick="generateDraft()">AI Draft</button>
            <button class="btn btn-outline" type="button" onclick="toggleForm()">Cancel</button>
        </div>
    </div>
</form>

<table class="table-wrap" style="width:100%;background:var(--color-white);border-radius:var(--radius-md);border:1px solid var(--color-border)">
    <thead><tr><th>Title</th><th>Category</th><th>Published</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $post): ?>
    <tr>
        <td><a href="/blog/<?= e($post['slug'] ?? '') ?>" target="_blank"><?= e($post['title'] ?? 'Untitled') ?></a></td>
        <td><?= e($post['category'] ?? '') ?></td>
        <td><?= !empty($post['published']) ? '✅' : '❌' ?></td>
        <td><?= e($post['published_at'] ?? '') ?></td>
        <td>
            <button class="btn btn-sm btn-ghost" onclick="editPost(<?= e(json_encode($post)) ?>)">Edit</button>
            <form method="post" action="/admin/blog/delete" style="display:inline" onsubmit="return confirm('Delete this post?')">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="slug" value="<?= e($post['slug'] ?? '') ?>">
                <button class="btn btn-sm btn-ghost" style="color:var(--color-error)">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
function toggleForm(){var f=document.getElementById('blog-form');f.hidden=!f.hidden;if(f.hidden)f.reset();}
function editPost(post){document.getElementById('edit-slug').value=post.slug||'';document.getElementById('edit-slug-display').value=post.slug||'';document.getElementById('edit-title').value=post.title||'';document.getElementById('edit-category').value=post.category||'';document.getElementById('edit-template').value=post.template||'editorial';document.getElementById('edit-image').value=post.og_image||post.image||'';document.getElementById('edit-image-alt').value=post.image_alt||'';document.getElementById('edit-source-url').value=post.source_url||'';document.getElementById('edit-summary').value=post.summary||post.excerpt||'';document.getElementById('edit-order').value=post.order||'';document.getElementById('edit-excerpt').value=post.excerpt||'';document.getElementById('edit-date').value=post.published_at||'';document.getElementById('edit-author').value=post.author||'Admin';document.getElementById('edit-content').value=post.content||'';document.getElementById('blog-form').hidden=false;}
document.getElementById('edit-title').addEventListener('input',function(){var slug=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('edit-slug').value=slug;document.getElementById('edit-slug-display').value=slug;});
function previewArticle(){var f=document.getElementById('blog-form');var fd=new FormData(f);fetch('/admin/blog/preview',{method:'POST',body:fd}).then(function(r){return r.text();}).then(function(html){var w=window.open('','_blank');w.document.write(html);w.document.close();});}
function generateDraft(){var f=document.getElementById('blog-form');var fd=new FormData(f);fd.set('content','');fetch('/admin/blog/ai-draft',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.content)document.getElementById('edit-content').value=d.content;});}
</script>
