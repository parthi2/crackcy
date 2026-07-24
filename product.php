<?php
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 1 LIMIT 1");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found or unavailable.');
    redirect('index.php');
}

$imagePath = !empty($product['image']) && file_exists('uploads/products/' . $product['image']) 
    ? 'uploads/products/' . $product['image'] 
    : 'https://via.placeholder.com/500x400?text=No+Image';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Shop</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= sanitize($product['product_name']); ?></li>
  </ol>
</nav>

<div class="card shadow-sm border-0 overflow-hidden">
    <div class="row g-0">
        <div class="col-md-6 bg-white text-center p-4">
            <img src="<?= $imagePath; ?>" class="img-fluid rounded object-fit-contain" style="max-height: 400px;" alt="<?= sanitize($product['product_name']); ?>">
        </div>
        <div class="col-md-6 p-4 d-flex flex-column">
            <span class="badge bg-secondary align-self-start mb-2"><?= sanitize($product['product_category']); ?></span>
            <h2 class="fw-bold"><?= sanitize($product['product_name']); ?></h2>
            <p class="text-muted small">SKU: <?= sanitize($product['sku']); ?></p>
            
            <h3 class="text-primary fw-bold my-3">₹<?= number_format($product['price'], 2); ?></h3>
            
            <div class="mb-4">
                <h6 class="fw-bold">Description</h6>
                <p class="text-secondary"><?= nl2br(sanitize($product['description'] ?? 'No description available.')); ?></p>
            </div>

            <form method="POST" action="cart.php" class="mt-auto">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                <div class="row g-2 align-items-center mb-3">
                    <div class="col-auto">
                        <label for="quantity" class="col-form-label fw-semibold">Quantity:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="quantity" name="quantity" class="form-control text-center" value="1" min="1" max="10" style="width: 80px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fa-solid fa-cart-plus me-2"></i> Add to Cart
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
