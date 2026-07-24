<?php
require_once 'includes/header.php';

$errors = [];
$editCategory = null;

// 1. Fetch Category for Editing (if edit_id parameter is present)
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $editStmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $editStmt->execute([':id' => $editId]);
    $editCategory = $editStmt->fetch();

    if (!$editCategory) {
        setFlash('error', 'Requested category does not exist.');
        redirect('categories.php');
    }
}

// 2. Handle Delete Category
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];

    // Check if products exist in this category before deleting
    $chkStmt = $pdo->prepare("SELECT category_name FROM categories WHERE id = :id LIMIT 1");
    $chkStmt->execute([':id' => $delId]);
    $catName = $chkStmt->fetchColumn();

    if ($catName) {
        $prodChk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE product_category = :cat");
        $prodChk->execute([':cat' => $catName]);
        $prodCount = (int)$prodChk->fetchColumn();

        if ($prodCount > 0) {
            setFlash('error', "Cannot delete category '{$catName}'. It contains {$prodCount} active product(s).");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $delStmt->execute([':id' => $delId]);
            setFlash('success', "Category '{$catName}' deleted successfully.");
        }
    }
    redirect('categories.php');
}

// 3. Handle Add / Edit Category Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = sanitize($_POST['category_name'] ?? '');
    $cat_id        = (int)($_POST['cat_id'] ?? 0);

    if (empty($category_name)) {
        $errors[] = "Category name is required.";
    }

    if (empty($errors)) {
        if ($cat_id > 0) {
            // Update Existing Category
            // Get old category name to update references in products table if necessary
            $oldStmt = $pdo->prepare("SELECT category_name FROM categories WHERE id = :id LIMIT 1");
            $oldStmt->execute([':id' => $cat_id]);
            $oldName = $oldStmt->fetchColumn();

            // Check duplicate name
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = :name AND id != :id");
            $dupStmt->execute([':name' => $category_name, ':id' => $cat_id]);

            if ((int)$dupStmt->fetchColumn() > 0) {
                $errors[] = "A category with this name already exists.";
            } else {
                $pdo->beginTransaction();

                $uStmt = $pdo->prepare("UPDATE categories SET category_name = :name WHERE id = :id");
                $uStmt->execute([':name' => $category_name, ':id' => $cat_id]);

                // Update category name in products table if modified
                if ($oldName && $oldName !== $category_name) {
                    $uProd = $pdo->prepare("UPDATE products SET product_category = :newName WHERE product_category = :oldName");
                    $uProd->execute([':newName' => $category_name, ':oldName' => $oldName]);
                }

                $pdo->commit();
                setFlash('success', 'Category updated successfully.');
                redirect('categories.php');
            }
        } else {
            // Insert New Category
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = :name");
            $dupStmt->execute([':name' => $category_name]);

            if ((int)$dupStmt->fetchColumn() > 0) {
                $errors[] = "A category with this name already exists.";
            } else {
                $insStmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (:name)");
                $insStmt->execute([':name' => $category_name]);

                setFlash('success', 'New category added successfully.');
                redirect('categories.php');
            }
        }
    }
}

// Fetch All Categories
$allCategories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-list me-2"></i>Category Management</h2>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm rounded-3">
        <ul class="mb-0 fw-semibold"><?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Add / Edit Form Panel -->
    <div class="col-lg-4 col-md-5">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span><?= $editCategory ? 'Edit Category' : 'Add Category'; ?></span>
                <?php if ($editCategory): ?>
                    <a href="categories.php" class="btn btn-sm btn-outline-light py-0">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="categories.php">
                    <input type="hidden" name="cat_id" value="<?= $editCategory['id'] ?? 0; ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Category Name *</label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. SPARKLERS" value="<?= sanitize($editCategory['category_name'] ?? ''); ?>" required autofocus>
                    </div>

                    <?php if ($editCategory): ?>
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Update Category
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fa-solid fa-plus me-1"></i> Add Category
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Data Table -->
    <div class="col-lg-8 col-md-7">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Category Name</th>
                                <th>Created At</th>
                                <th class="text-end pe-3" style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allCategories)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No categories found.</td>
                                </tr>
                            <?php else: ?>
                                <?php $index = 1; foreach ($allCategories as $cat): ?>
                                    <tr class="<?= ($editCategory && $editCategory['id'] == $cat['id']) ? 'table-warning' : ''; ?>">
                                        <td class="fw-bold"><?= $index++; ?></td>
                                        <td class="fw-bold text-dark"><?= sanitize($cat['category_name']); ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($cat['created_at'] ?? 'now')); ?></td>
                                        <td class="text-end pe-3">
                                            <!-- Edit Action -->
                                            <a href="categories.php?edit_id=<?= $cat['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <!-- Delete Action -->
                                            <a href="categories.php?action=delete&id=<?= $cat['id']; ?>" class="btn btn-sm btn-danger" title="Delete Category" onclick="return confirm('Are you sure you want to delete \'<?= sanitize($cat['category_name']); ?>\'?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>