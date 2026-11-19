<section class="admin-card" aria-labelledby="import-title">
    <a href="/admin/products#product-list">Back to products</a>
    <h1 id="import-title">Import products</h1>
    <p>Upload up to 100 products in a UTF-8 CSV file (64 KiB). New products start hidden and out of stock. Existing slugs are skipped.</p>
    <a href="/assets/product-import-sample.csv" download>Download sample CSV</a>
    <?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
    <form class="admin-form" action="/admin/products/import" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="step" value="preview">
        <label>Product CSV<input type="file" name="catalogue" accept=".csv,text/csv" required></label>
        <button class="btn btn-primary">Preview import</button>
    </form>
</section>
<?php if ($records): ?>
<section class="admin-card" aria-labelledby="review-title">
    <h2 id="review-title">Review import</h2>
    <p><?= count($records) - count(array_unique($existing)) ?> to add · <?= count(array_unique($existing)) ?> to skip</p>
    <div class="table-wrap" data-no-table-search tabindex="0" role="region" aria-label="Import preview">
        <table><thead><tr><th>Action</th><th>Product</th><th>Slug</th><th>Price</th><th>Category</th><th>Description</th></tr></thead><tbody>
        <?php foreach ($records as $product): ?>
            <tr><td><?= in_array($product['slug'], $existing, true) ? 'Skip existing' : 'Add hidden' ?></td>
                <td><?= e($product['name']) ?></td><td><?= e($product['slug']) ?></td><td>₹<?= number_format($product['price'], 2) ?></td>
                <td><?= e($product['category']) ?></td><td><?= e($product['description']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php if (count($records) > count(array_unique($existing))): ?>
    <form action="/admin/products/import" method="post" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="step" value="confirm">
        <input type="hidden" name="reviewed_csv" value="<?= e(base64_encode($csv)) ?>">
        <button class="btn btn-primary">Confirm import</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
