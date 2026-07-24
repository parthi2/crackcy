<?php
require_once 'includes/header.php';

$errors = [];
$name = $category = $sku = $description = $price = '';
$status = 1;

// Fetch categories for dropdown
$categoriesStmt = $pdo->query("SELECT category_name FROM categories ORDER BY category_name ASC");
$categoryOptions = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['product_name'] ?? '');
    $category = sanitize($_POST['product_category'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) $errors[] = "Product name is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if (empty($sku)) $errors[] = "SKU is required.";
    if ($price <= 0) $errors[] = "Valid price is required.";
    $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
    $skuCheck->execute([':sku' => $sku]);
    if ($skuCheck->fetch()) {
        $errors[] = "The entered SKU already exists.";
    }

    $imageFilename = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $imageFilename = uniqid('prod_') . '.' . $ext;
            $uploadDir = '../uploads/products/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            move_uploaded_file($fileTmp, $uploadDir . $imageFilename);
        } else {
            $errors[] = "Invalid image extension. Allowed: JPG, JPEG, PNG, WEBP.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO products (product_name, product_category, sku, description, price, image, status)
            VALUES (:name, :category, :sku, :description, :price, :image, :status)
        ");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':sku' => $sku,
            ':description' => $description,
            ':price' => $price,
            ':image' => $imageFilename,
            ':status' => $status
        ]);

        setFlash('success', 'New product created successfully.');
        redirect('products.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Add New Product</h2>
    <a href="products.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?></ul></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="POST" action="product-add.php" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="product_name" class="form-control" value="<?= $name; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category *</label>
<select name="product_category" class="form-select" required>
    <option value="">-- Select Category --</option>
    <?php foreach ($categoryOptions as $catOpt): ?>
        <option value="<?= sanitize($catOpt); ?>" <?= ($category === $catOpt) ? 'selected' : ''; ?>>
            <?= sanitize($catOpt); ?>
        </option>
    <?php endforeach; ?>
</select>                </div>
                <div class="col-md-3">
                    <label class="form-label">SKU *</label>
                    <input type="text" name="sku" class="form-control" value="<?= $sku; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?= $price; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">
                </div>
                <div class="col-md-4 d-flex align-items-center pt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?= $status ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="status">Active Status</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= $description; ?></textarea>
                </div>
                <div class="col-12">
                    <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail d-none" style="max-height: 150px;">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
