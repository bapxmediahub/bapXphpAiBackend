<?php if(empty($product)): ?>
    <h1>Product not found</h1>
    <p>The item you're looking for is unavailable.</p>
<?php else: ?>
    <h1><?= e($product['name']) ?></h1>
    <?php if(!empty($product['image_url'])): ?><img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?>
    <p><?= e($product['description'] ?? '') ?></p>
    <?php if(!empty($product['category'])): ?><p><strong>Category:</strong> <?= e($product['category']) ?></p><?php endif; ?>
    <p><strong>Price:</strong> ₹<?= e((string)($product['offer_price'] ?: $product['price'] ?: 0)) ?></p>
    <form method="post" action="/cart/add">
        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
        <input type="hidden" name="qty" value="1">
        <button>Add to cart</button>
    </form>
<?php endif; ?>
