<div class="admin-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg)">
    <h2 style="margin:0"><?= e($title ?? 'Blog') ?></h2>
    <button class="btn btn-primary" type="button" onclick="toggleForm()">+ New Post</button>
</div>

<form method="post" action="/admin/blog/save" id="blog-form" style="display:none;background:var(--color-white);padding:var(--space-lg);border-radius:var(--radius-md);margin-bottom:var(--space-lg);border:1px solid var(--color-border)">
    <input type="hidden" name="slug" id="edit-slug" value="">
    <div style="display:grid;gap:var(--space-sm);max-width:720px">
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
        <label>Excerpt <textarea name="excerpt" id="edit-excerpt" rows="2" style="width:100%"></textarea></label>
        <label>Published At <input type="date" name="published_at" id="edit-date" style="width:100%"></label>
        <label>Author <input type="text" name="author" id="edit-author" value="Admin" style="width:100%"></label>
        <label>Content (Markdown) <textarea name="content" id="edit-content" rows="12" style="width:100%;font-family:monospace"></textarea></label>
        <div style="display:flex;gap:var(--space-sm)">
            <button class="btn btn-primary" type="submit">Save</button>
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
                <input type="hidden" name="slug" value="<?= e($post['slug'] ?? '') ?>">
                <button class="btn btn-sm btn-ghost" style="color:var(--color-error)">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
function toggleForm(){var f=document.getElementById('blog-form');f.style.display=f.style.display==='none'?'block':'none';if(f.style.display==='none')f.reset();}
function editPost(post){document.getElementById('edit-slug').value=post.slug||'';document.getElementById('edit-slug-display').value=post.slug||'';document.getElementById('edit-title').value=post.title||'';document.getElementById('edit-category').value=post.category||'';document.getElementById('edit-excerpt').value=post.excerpt||'';document.getElementById('edit-date').value=post.published_at||'';document.getElementById('edit-author').value=post.author||'Admin';document.getElementById('edit-content').value=post.content||'';var f=document.getElementById('blog-form');f.style.display='block';}
document.getElementById('edit-title').addEventListener('input',function(){var slug=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('edit-slug').value=slug;document.getElementById('edit-slug-display').value=slug;});
</script>
