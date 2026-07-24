<?php
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['flash_error'] = "Product not found.";
    redirect("products");
}

// Fetch Active Categories for Dropdown
$catStmt = $pdo->query("SELECT category_name FROM categories WHERE status = 1 ORDER BY category_name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// -------------------------------------------------------------
// 1. HANDLE INSTANT REMOVE IMAGE ACTION
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_image'])) {
    if (!empty($product['image'])) {
        $imagePath = __DIR__ . '/../uploads/products/' . $product['image'];
        
        // Delete physical file from uploads folder if it exists
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }

        // Clear image column in database
        $updateStmt = $pdo->prepare("UPDATE products SET image = NULL WHERE id = :id");
        $updateStmt->execute([':id' => $productId]);

        $_SESSION['flash_success'] = "Product image removed successfully!";
        redirect("product-edit?id=" . $productId);
    }
}

// -------------------------------------------------------------
// 2. HANDLE PRODUCT FORM UPDATE
// -------------------------------------------------------------
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_name     = sanitize($_POST['product_name'] ?? '');
    $product_category = sanitize($_POST['product_category'] ?? '');
    $sku              = sanitize($_POST['sku'] ?? '');
    $price            = (float)($_POST['price'] ?? 0);
    $description      = sanitize($_POST['description'] ?? '');
    $status           = isset($_POST['status']) ? 1 : 0;

    if (empty($product_name)) $errors[] = "Product Name is required.";
    if (empty($product_category)) $errors[] = "Category is required.";
    if (empty($sku)) $errors[] = "SKU is required.";
    if ($price <= 0) $errors[] = "Valid Price is required.";

    // Unique SKU validation (excluding current product ID)
    $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
    $skuCheck->execute([':sku' => $sku, ':id' => $productId]);
    if ($skuCheck->fetch()) {
        $errors[] = "SKU already exists. Please use a unique SKU.";
    }

    $imageName = $product['image'];

    // Handle File Upload if a new file is chosen
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp    = $_FILES['image']['tmp_name'];
        $fileName   = $_FILES['image']['name'];
        $fileExt    = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($fileExt, $allowedExt)) {
            $errors[] = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
        } else {
            // Unlink existing image if replacing
            if (!empty($product['image'])) {
                $oldImagePath = __DIR__ . '/../uploads/products/' . $product['image'];
                if (file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }

            // Save new unique image
            $imageName   = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $destination = __DIR__ . '/../uploads/products/' . $imageName;
            
            if (!move_uploaded_file($fileTmp, $destination)) {
                $errors[] = "Failed to upload new image.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $updateSql = $pdo->prepare("
                UPDATE products 
                SET product_name = :name,
                    product_category = :category,
                    sku = :sku,
                    price = :price,
                    description = :description,
                    status = :status,
                    image = :image
                WHERE id = :id
            ");

            $updateSql->execute([
                ':name'        => $product_name,
                ':category'    => $product_category,
                ':sku'         => $sku,
                ':price'       => $price,
                ':description' => $description,
                ':status'      => $status,
                ':image'       => $imageName,
                ':id'          => $productId
            ]);

            $_SESSION['flash_success'] = "Product updated successfully!";
            redirect("products");

        } catch (Exception $e) {
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Product</h2>
    <a href="products" class="btn btn-secondary fw-bold">
        <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
        <ul class="mb-0 fw-semibold">
            <?php foreach ($errors as $err): ?>
                <li><?= $err; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <form action="product-edit?id=<?= $productId; ?>" method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                
                <!-- Product Name -->
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Product Name *</label>
                    <input type="text" name="product_name" class="form-control" value="<?= sanitize($product['product_name']); ?>" required>
                </div>

                <!-- Category Dropdown -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Category *</label>
                    <select name="product_category" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= sanitize($cat); ?>" <?= ($product['product_category'] === $cat) ? 'selected' : ''; ?>>
                                <?= sanitize($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- SKU -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">SKU *</label>
                    <input type="text" name="sku" class="form-control" value="<?= sanitize($product['sku']); ?>" required>
                </div>

                <!-- Price -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Price (₹) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?= number_format($product['price'], 2, '.', ''); ?>" required>
                </div>

                <!-- Replace Image File Upload -->
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-muted">Replace Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <!-- Status Toggle -->
                <div class="col-md-3 d-flex align-items-center pt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="statusSwitch" value="1" <?= ($product['status'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="statusSwitch">Active Status</label>
                    </div>
                </div>

                <!-- Description -->
                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= sanitize($product['description'] ?? ''); ?></textarea>
                </div>

                <!-- Current Image Preview & Remove Button -->
                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted d-block">Current Image Preview</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="border rounded-3 p-2 bg-light text-center" style="width: 140px; height: 140px;">
                            <?php if (!empty($product['image'])): ?>
                                <img src="../uploads/products/<?= sanitize($product['image']); ?>" alt="Product Preview" class="img-fluid h-100 rounded" style="object-fit: cover;">
                            <?php else: ?>
                                <img src="../assets/image/no-image.jpg" alt="No Image Available" class="img-fluid h-100 rounded" style="object-fit: cover;">
                            <?php endif; ?>
                        </div>

                        <!-- Displays if an image filename exists in DB -->
                        <?php if (!empty($product['image'])): ?>
                            <div>
                                <button type="submit" name="remove_image" value="1" class="btn btn-outline-danger btn-sm fw-bold" onclick="return confirm('Are you sure you want to delete this product image?');">
                                    <i class="fa-solid fa-trash-can me-1"></i> Remove Image
                                </button>
                                <div class="form-text mt-2 text-danger small">
                                    <i class="fa-solid fa-circle-info me-1"></i> Deletes the file from disk and updates database immediately.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="mt-4 pt-2 border-top">
                <button type="submit" name="update_product" class="btn btn-primary fw-bold px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>